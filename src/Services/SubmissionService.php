<?php

/**
 * Submission service.
 *
 * Business logic layer for handling form submissions including
 * validation, file uploads, spam protection, and notification triggering.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\FieldTypes\CalculationFieldType;
use ArtisanPackUI\Forms\FieldTypes\ColorFieldType;
use ArtisanPackUI\Forms\FieldTypes\DateTimeFieldType;
use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\FieldTypes\PhoneFieldType;
use ArtisanPackUI\Forms\FieldTypes\SignatureFieldType;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Support\MediaLibraryStorage;
use ArtisanPackUI\Forms\Support\NumberFormat;
use ArtisanPackUI\Forms\Support\RichText;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use ArtisanPackUI\Forms\Support\TransientUploads;
use ArtisanPackUI\Forms\Support\UrlSchemes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Submission service class.
 *
 * Business logic layer for handling form submissions including
 * validation, file uploads, spam protection, and notification triggering.
 *
 *
 * @since      1.0.0
 */
class SubmissionService
{
    /**
     * The minimum time in seconds between form load and submission.
     *
     * @since 1.0.0
     */
    protected const MIN_SUBMISSION_TIME = 3;

    /**
     * The maximum submissions per IP per minute.
     *
     * @since 1.0.0
     */
    protected const RATE_LIMIT_MAX_ATTEMPTS = 5;

    /**
     * The notification service instance.
     *
     * @since 1.0.0
     */
    protected NotificationService $notificationService;

    /**
     * The files stored by the submission being created, removed again if its
     * transaction rolls back. Cleared when the transaction commits.
     *
     * @since 1.6.0
     *
     * @var list<array{disk: string, path: string, media_id: int|null}>
     */
    protected array $storedFiles = [];

    /**
     * Creates a new submission service instance.
     *
     * @since 1.0.0
     *
     * @param  NotificationService  $notificationService  The notification service.
     */
    public function __construct( NotificationService $notificationService )
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Creates a new submission for a form.
     *
     * Applies the 'ap.forms.submissionData' filter hook to allow
     * third-party packages to modify submission data before saving. Read-only
     * fields are resolved after the filter, so a value it sets for one is
     * replaced; use the 'ap.forms.readOnlyValue' filter to supply those.
     * Values for administrative fields are dropped, since admins fill those in
     * on the submission (see updateAdministrativeValues()). Values of "Do not
     * store" fields are not saved; they are added to the submission's loaded
     * values for the notifications and webhooks of this request only; a "Do
     * not store" file field's files are stored until those have been sent
     * (see TransientUploads). An
     * email field's confirmation (`{name}_confirmation`) and the token of a
     * type that stores no value (such as a CAPTCHA) are dropped before the
     * filter runs. Phone numbers checked by format are converted to E.164
     * first, reading the country the visitor selected (`{name}_country`),
     * which is then dropped too. A multi-file field's files are each stored
     * as an upload, with one submission value listing them, and a drawn
     * signature is stored as a PNG upload (see saveSignature()). Calculation
     * fields are worked out again from the submitted values, whatever the
     * client sent for them (see applyCalculations()).
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form being submitted.
     * @param  array<string, mixed>  $formData  The submitted form data.
     * @param  array<string, array<array-key, UploadedFile>|UploadedFile|null>  $files  Uploaded files keyed by field name; a list for a multi-file field.
     * @param  array<string, mixed>  $metadata  Additional metadata (ip, user_agent, etc.).
     *
     * @throws RuntimeException If the submission cannot be created.
     *
     * @return FormSubmission The created submission.
     */
    public function create( Form $form, array $formData, array $files = [], array $metadata = [] ): FormSubmission
    {
        $fields = $form->fields()->get()->keyBy( 'name' );

        // Phone numbers are stored in E.164 form, read in the selected country
        $formData = $this->normalizePhoneNumbers( $fields, $formData );

        // Confirmation inputs, phone countries and challenge tokens are checked, never kept
        $formData = $this->withoutTransientInputs( $fields, $formData );

        // Apply filter hook to allow modifying submission data before saving
        $formData = applyFilters( 'ap.forms.submissionData', $formData, $form );

        // Read-only fields keep their own value whatever the client sent
        $formData = $this->applyReadOnlyValues( $fields, $formData, true );

        // Calculations are worked out on the server, never taken from the client
        $formData = $this->applyCalculations( $fields, $formData, true );

        // Administrative fields are never filled in by the public form
        $formData = array_diff_key( $formData, $fields->filter( fn ( FormField $field ): bool => $field->isAdministrative() )->all() );

        $this->storedFiles = [];

        try {
            $submission = $this->createInTransaction( $form, $fields, $formData, $files, $metadata );
        } catch ( Throwable $exception ) {
            $this->deleteStoredFiles();

            throw $exception;
        } finally {
            $this->storedFiles = [];
        }

        try {
            // Send notifications after transaction completes (outside transaction to ensure submission is persisted)
            $this->sendNotifications( $submission );
        } finally {
            // Every job that needs a "Do not store" file now holds it, so the
            // submission's own hold is released (after any outer transaction
            // commits, once the FormSubmitted listeners have dispatched theirs)
            $transientUploadIds = TransientUploads::idsIn( $submission->getTransientValues() );

            DB::afterCommit( fn () => TransientUploads::release( $transientUploadIds ) );
        }

        return $submission;
    }

    /**
     * Removes the submitted inputs that are checked but never stored.
     *
     * An email field's confirmation input and a phone field's country (unless
     * a field has that name), and the value of any field whose type definition sets `stores_value` to
     * false (such as a CAPTCHA token).
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     *
     * @return array<string, mixed> The values without those inputs.
     */
    public function withoutTransientInputs( iterable $fields, array $formData ): array
    {
        $names = [];

        foreach ( $fields as $field ) {
            $names[ $field->name ] = true;
        }

        foreach ( $fields as $field ) {
            if ( 'email' === $field->type && ! isset( $names[ $field->getConfirmationKey() ] ) ) {
                unset( $formData[ $field->getConfirmationKey() ] );
            }

            if ( 'phone' === $field->type && ! isset( $names[ $field->getPhoneCountryKey() ] ) ) {
                unset( $formData[ $field->getPhoneCountryKey() ] );
            }

            if ( false === ( $field->fieldType()->definition()['stores_value'] ?? true ) ) {
                unset( $formData[ $field->name ] );
            }
        }

        return $formData;
    }

    /**
     * Converts phone numbers checked by format to E.164 form.
     *
     * Each number is read in the country submitted for it as `{name}_country`
     * (the Livewire country selector), falling back to the field's default
     * country. Numbers that do not parse are left as they are.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     *
     * @return array<string, mixed> The values with phone numbers converted.
     */
    public function normalizePhoneNumbers( iterable $fields, array $formData ): array
    {
        foreach ( $fields as $field ) {
            if ( 'phone' !== $field->type || ! array_key_exists( $field->name, $formData ) ) {
                continue;
            }

            $country = $formData[ $field->getPhoneCountryKey() ] ?? null;

            $formData[ $field->name ] = PhoneFieldType::toE164( $field, $formData[ $field->name ], is_string( $country ) ? $country : null );
        }

        return $formData;
    }

    /**
     * Converts numbers typed in a number field's format to plain decimals.
     *
     * Runs before validation, so the `numeric`, `integer` and limit rules
     * see the plain number (see NumberFormat).
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     *
     * @return array<string, mixed> The values with formatted numbers converted.
     */
    public function normalizeFormattedNumbers( iterable $fields, array $formData ): array
    {
        foreach ( $fields as $field ) {
            $format = 'number' === $field->type ? $field->getNumberFormat() : null;

            if ( null !== $format && array_key_exists( $field->name, $formData ) ) {
                $formData[ $field->name ] = NumberFormat::normalize( $formData[ $field->name ], $format );
            }
        }

        return $formData;
    }

    /**
     * Normalizes submitted values that are read differently from how they are typed, before validation.
     *
     * Numbers typed in a number field's format become plain decimals (see
     * normalizeFormattedNumbers()), URLs typed without a scheme get
     * `https://` (see UrlSchemes) and rich text that holds no text, such as
     * an emptied editor's `<p><br></p>`, becomes blank so `required` sees it.
     * A `datetime-local` value becomes `Y-m-d H:i`, and a list keeps only its
     * filled rows, under their own keys, so an error names the row the
     * visitor sees. A color typed as `FF8800` or `#F80` becomes `#ff8800`
     * (see ColorFieldType::normalizeValue()).
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     *
     * @return array<string, mixed> The normalized values.
     */
    public function normalizeSubmittedValues( iterable $fields, array $formData ): array
    {
        $formData = $this->normalizeFormattedNumbers( $fields, $formData );

        foreach ( $fields as $field ) {
            if ( ! array_key_exists( $field->name, $formData ) ) {
                continue;
            }

            if ( 'url' === $field->type ) {
                $formData[ $field->name ] = UrlSchemes::withScheme( $formData[ $field->name ], $field->getAllowedUrlSchemes() );
            } elseif ( $field->usesRichText() ) {
                $formData[ $field->name ] = RichText::blankIfEmpty( $formData[ $field->name ] );
            } elseif ( DateTimeFieldType::TYPE === $field->type ) {
                $formData[ $field->name ] = DateTimeFieldType::normalizeValue( $formData[ $field->name ] );
            } elseif ( ListFieldType::TYPE === $field->type ) {
                $formData[ $field->name ] = ListFieldType::filledRows( $field, $formData[ $field->name ], false );
            } elseif ( ColorFieldType::TYPE === $field->type ) {
                $formData[ $field->name ] = ColorFieldType::normalizeValue( $formData[ $field->name ] );
            }
        }

        return $formData;
    }

    /**
     * Checks if the submission appears to be spam.
     *
     * Logs security events when spam is detected for monitoring and analysis.
     *
     * @since 1.0.0
     *
     * @param  string|null  $honeypot  The honeypot field value (should be empty).
     * @param  int|null  $formLoadedAt  Timestamp when form was loaded.
     * @param  int|null  $formId  The form ID for logging context.
     * @param  string|null  $ipAddress  The IP address for logging context.
     *
     * @return bool True if the submission appears to be spam.
     */
    public function isSpam( ?string $honeypot, ?int $formLoadedAt, ?int $formId = null, ?string $ipAddress = null ): bool
    {
        // Check honeypot - if filled, it's a bot
        if ( ! empty( $honeypot ) ) {
            $this->logSecurityEvent( 'honeypot_triggered', [
                'form_id'        => $formId,
                'ip_address'     => $ipAddress,
                'honeypot_value' => Str::limit( $honeypot, 50 ),
            ] );

            return true;
        }

        // Check submission time - if too fast, it's a bot
        if ( null !== $formLoadedAt ) {
            $elapsed = now()->getTimestamp() - $formLoadedAt;
            if ( $elapsed < self::MIN_SUBMISSION_TIME ) {
                $this->logSecurityEvent( 'submission_too_fast', [
                    'form_id'         => $formId,
                    'ip_address'      => $ipAddress,
                    'elapsed_seconds' => $elapsed,
                    'min_required'    => self::MIN_SUBMISSION_TIME,
                ] );

                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the IP address is rate limited.
     *
     * Logs security events when rate limiting is triggered.
     *
     * @since 1.0.0
     *
     * @param  string  $ipAddress  The IP address to check.
     * @param  int  $formId  The form ID.
     * @param  bool  $logEvent  Whether to log the rate limit event.
     *
     * @return bool True if the IP is rate limited.
     */
    public function isRateLimited( string $ipAddress, int $formId, bool $logEvent = true ): bool
    {
        $key         = "form-submission:{$formId}:{$ipAddress}";
        $maxAttempts = $this->resolveRateLimitMaxAttempts();
        $isLimited   = RateLimiter::tooManyAttempts( $key, $maxAttempts );

        if ( $isLimited && $logEvent ) {
            $this->logSecurityEvent( 'rate_limit_exceeded', [
                'form_id'      => $formId,
                'ip_address'   => $ipAddress,
                'max_attempts' => $maxAttempts,
            ] );
        }

        return $isLimited;
    }

    /**
     * Records a submission attempt for rate limiting.
     *
     * @since 1.0.0
     *
     * @param  string  $ipAddress  The IP address making the attempt.
     * @param  int  $formId  The form ID.
     */
    public function recordAttempt( string $ipAddress, int $formId ): void
    {
        $key = "form-submission:{$formId}:{$ipAddress}";

        RateLimiter::hit( $key, $this->resolveRateLimitDecay() );
    }

    /**
     * Builds validation rules for all visible fields.
     *
     * Rules for inputs nested under a field (see FieldType::rules()) are keyed
     * below the field's own validation key. Administrative fields are left
     * out (see buildAdministrativeValidationRules()).
     *
     * @since 1.0.0
     *
     * @param  Collection<int, FormField>  $fields  The form fields.
     * @param  array<string, bool>  $hiddenFields  Fields hidden by conditional logic.
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function buildValidationRules( $fields, array $hiddenFields = [] ): array
    {
        $rules = [];

        foreach ( $fields as $field ) {
            // Skip fields hidden by conditional logic
            if ( isset( $hiddenFields[ $field->name ] ) && $hiddenFields[ $field->name ] ) {
                continue;
            }

            // Administrative fields are not on the public form
            if ( $field->isAdministrative() ) {
                continue;
            }

            foreach ( $field->buildKeyedValidationRules() as $inputKey => $fieldRules ) {
                $rules[ $this->validationKey( $field, (string) $inputKey ) ] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Builds custom validation messages for fields.
     *
     * The field type's messages, with the field's custom error and required
     * messages applied (see FormField::buildValidationMessages()).
     *
     * @since 1.0.0
     *
     * @param  Collection<int, FormField>  $fields  The form fields.
     *
     * @return array<string, string> The validation messages.
     */
    public function buildValidationMessages( $fields ): array
    {
        $messages = [];

        foreach ( $fields as $field ) {
            $fieldKey = $this->validationKey( $field );

            foreach ( $field->buildValidationMessages() as $rule => $message ) {
                $messages[ "{$fieldKey}.{$rule}" ] = $message;
            }
        }

        return $messages;
    }

    /**
     * Replaces the submitted values of read-only fields with their own values.
     *
     * A read-only field is shown but not editable, so whatever the client sent
     * for it is ignored in favour of FormField::getReadOnlyValue().
     *
     * With `$onlySubmitted`, fields missing from the data are left out rather
     * than added, so a field the request layer dropped (for example because
     * conditional logic hides it) is not stored.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     * @param  bool  $onlySubmitted  Whether to replace only the fields present in the data.
     *
     * @return array<string, mixed> The values with read-only fields resolved.
     */
    public function applyReadOnlyValues( iterable $fields, array $formData, bool $onlySubmitted = false ): array
    {
        foreach ( $fields as $field ) {
            if ( $onlySubmitted && ! array_key_exists( $field->name, $formData ) ) {
                continue;
            }

            if ( $field->isReadOnly() ) {
                $formData[ $field->name ] = $field->getReadOnlyValue();
            }
        }

        return $formData;
    }

    /**
     * Works out each calculation field's value from the other values.
     *
     * The value a client sent for a calculation is replaced. A calculation
     * may use another calculation's result; one that refers back to itself,
     * directly or through others, has no result. Administrative fields read
     * as blank, whatever a client sent for them. Numbers typed in a number
     * field's format are read as plain decimals (see
     * normalizeFormattedNumbers()).
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The values keyed by field name.
     * @param  bool  $onlySubmitted  Whether to work out only the calculations present in the data, leaving out those conditional logic hid.
     *
     * @return array<string, mixed> The values with calculations worked out.
     */
    public function applyCalculations( iterable $fields, array $formData, bool $onlySubmitted = false ): array
    {
        $calculations = [];

        foreach ( $fields as $field ) {
            if ( CalculationFieldType::TYPE === $field->type ) {
                $calculations[ $field->name ] = $field;
            }
        }

        if ( [] === $calculations ) {
            return $formData;
        }

        $operands = $this->normalizeFormattedNumbers( $fields, $formData );
        $results  = [];

        // Administrative fields are never filled in by the public form, so a formula reads them as blank
        foreach ( $fields as $field ) {
            if ( $field->isAdministrative() ) {
                unset( $operands[ $field->name ] );
            }
        }

        $stack    = [];
        $cyclic   = [];

        $resolve = function ( string $name ) use ( &$resolve, &$results, &$stack, &$cyclic, $calculations, $operands ): mixed {
            if ( ! isset( $calculations[ $name ] ) ) {
                return $operands[ $name ] ?? null;
            }

            if ( array_key_exists( $name, $results ) ) {
                return $results[ $name ];
            }

            // A calculation reached again while it is being worked out is in a cycle
            if ( false !== ( $index = array_search( $name, $stack, true ) ) ) {
                foreach ( array_slice( $stack, (int) $index ) as $member ) {
                    $cyclic[ $member ] = true;
                }

                return null;
            }

            $stack[] = $name;
            $result  = CalculationFieldType::compute( $calculations[ $name ], $resolve );
            array_pop( $stack );

            return $results[ $name ] = isset( $cyclic[ $name ] ) ? '' : $result;
        };

        foreach ( $calculations as $name => $field ) {
            if ( $onlySubmitted && ! array_key_exists( $name, $formData ) ) {
                continue;
            }

            $formData[ $name ] = $resolve( (string) $name );
        }

        return $formData;
    }

    /**
     * Works out each calculation from the values of the fields conditional logic shows.
     *
     * Calculations are worked out from every value first, so conditions can
     * use their results, then again without the values of the fields those
     * conditions hide, since hidden values are not submitted. The submit API
     * runs this before deciding which fields are hidden, so a client cannot
     * show or hide a field by sending its own calculation result.
     *
     * @since 1.6.0
     *
     * @param  Collection<int, FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The values keyed by field name.
     *
     * @return array<string, mixed> The values with calculations worked out.
     */
    public function applyVisibleCalculations( Collection $fields, array $formData ): array
    {
        if ( ! $fields->contains( 'type', CalculationFieldType::TYPE ) ) {
            return $formData;
        }

        $formData = $this->applyCalculations( $fields, $formData );
        $hidden   = array_filter( app( ConditionalLogicService::class )->getHiddenFields( $fields, $formData ) );

        if ( [] === $hidden ) {
            return $formData;
        }

        $visible = $this->applyCalculations( $fields, array_diff_key( $formData, $hidden ) );

        foreach ( $fields->where( 'type', CalculationFieldType::TYPE ) as $field ) {
            $formData[ $field->name ] = $visible[ $field->name ];
        }

        return $formData;
    }

    /**
     * Replaces each selected "Other" choice with the text typed for it.
     *
     * Renderers keep the "Other" choice selected as FormField::OTHER_CHOICE_VALUE
     * and its text separately. A blank text leaves the placeholder in place,
     * which the `forms_other_choice` rule then rejects.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     * @param  array<string, mixed>  $otherValues  The "Other" texts keyed by field name.
     *
     * @return array<string, mixed> The values with "Other" choices resolved.
     */
    public function resolveOtherChoices( iterable $fields, array $formData, array $otherValues ): array
    {
        foreach ( $fields as $field ) {
            if ( ! $field->allowsOtherChoice() || ! array_key_exists( $field->name, $formData ) ) {
                continue;
            }

            $text = $otherValues[ $field->name ] ?? '';
            $text = is_scalar( $text ) && ! is_bool( $text ) ? trim( (string) $text ) : '';

            if ( '' === $text ) {
                continue;
            }

            $value = $formData[ $field->name ];

            if ( is_array( $value ) ) {
                $resolved = array_map( fn ( mixed $item ): mixed => FormField::OTHER_CHOICE_VALUE === $item ? $text : $item, $value );

                $formData[ $field->name ] = array_values( array_unique( $resolved, SORT_REGULAR ) );
            } elseif ( FormField::OTHER_CHOICE_VALUE === $value ) {
                $formData[ $field->name ] = $text;
            }
        }

        return $formData;
    }

    /**
     * Builds the validation rules for the administrative fields an admin edits on a submission.
     *
     * Keyed like buildValidationRules(). A "No duplicates" check skips the
     * submission being edited. File fields are left out, since their files
     * are sent apart from the values (see buildAdministrativeFileValidationRules()).
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields; only administrative ones are used.
     * @param  FormSubmission  $submission  The submission being edited.
     * @param  string  $prefix  The key the values are nested under.
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function buildAdministrativeValidationRules( iterable $fields, FormSubmission $submission, string $prefix = 'formData' ): array
    {
        $rules = [];

        foreach ( $fields as $field ) {
            if ( ! $field->isAdministrative() || $field->is_file_field ) {
                continue;
            }

            foreach ( $field->buildKeyedValidationRules() as $inputKey => $fieldRules ) {
                $key           = '' === $inputKey ? "{$prefix}.{$field->name}" : "{$prefix}.{$field->name}.{$inputKey}";
                $rules[ $key ] = SubmissionRules::ignoringSubmission( $fieldRules, (int) $submission->getKey() );
            }
        }

        return $rules;
    }

    /**
     * Builds the validation rules for the files an admin uploads to a submission's administrative file fields.
     *
     * Keyed like buildAdministrativeValidationRules(), with each field's own
     * file rules (allowed types, size and image limits). A required field
     * only needs a file while the submission has none for it, since a file
     * that is already stored is kept unless it is replaced or removed.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields; only administrative file fields that store values are used.
     * @param  FormSubmission  $submission  The submission being edited.
     * @param  string  $prefix  The key the files are nested under.
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function buildAdministrativeFileValidationRules( iterable $fields, FormSubmission $submission, string $prefix = 'adminFiles' ): array
    {
        $rules = [];

        foreach ( $fields as $field ) {
            if ( ! $field->isAdministrative() || ! $field->is_file_field || ! $field->storesValues() ) {
                continue;
            }

            $hasFile = $this->administrativeFileValue( $submission, $field ) instanceof FormSubmissionValue;

            foreach ( $field->buildKeyedValidationRules() as $inputKey => $fieldRules ) {
                if ( '' === $inputKey && $hasFile ) {
                    $fieldRules = array_map( fn ( mixed $rule ): mixed => 'required' === $rule ? 'nullable' : $rule, $fieldRules );
                }

                $key           = '' === $inputKey ? "{$prefix}.{$field->name}" : "{$prefix}.{$field->name}.{$inputKey}";
                $rules[ $key ] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Replaces the files of a submission's administrative file fields with the ones an admin uploaded.
     *
     * Only administrative file fields that store values are written, and only
     * those a file was uploaded for. The field's earlier files and upload
     * records are deleted once the new ones are saved. Fires the submission's
     * updated event once.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The submission.
     * @param  array<string, mixed>  $files  The uploaded file, or a list for a multi-file field, keyed by field name.
     *
     * @throws RuntimeException If a file cannot be stored.
     *
     * @return FormSubmission The submission, with its `values` and `uploads` relations unloaded.
     */
    public function updateAdministrativeFiles( FormSubmission $submission, array $files ): FormSubmission
    {
        // Nothing to replace, so the submission isn't touched and no updated event fires
        if ( [] === array_filter( $files, fn ( mixed $file ): bool => null !== $file && [] !== $file ) ) {
            return $submission;
        }

        $submission->loadMissing( 'form.fields' );

        $replaced          = [];
        $this->storedFiles = [];

        try {
            DB::transaction( function () use ( $submission, $files, &$replaced ): void {
                foreach ( $submission->form->fields as $field ) {
                    if ( ! $field->isAdministrative() || ! $field->is_file_field || ! $field->storesValues() ) {
                        continue;
                    }

                    $uploaded = $files[ $field->name ] ?? null;
                    $uploaded = $field->allowsMultipleFiles()
                        ? array_values( array_filter( is_array( $uploaded ) ? $uploaded : [$uploaded], fn ( mixed $file ): bool => $file instanceof UploadedFile ) )
                        : ( $uploaded instanceof UploadedFile ? $uploaded : null );

                    if ( null === $uploaded || [] === $uploaded ) {
                        continue;
                    }

                    $current = $this->administrativeFileValue( $submission, $field );

                    if ( null !== $current ) {
                        array_push( $replaced, ...$current->getUploads() );
                    }

                    // A redacted value is replaced along with the current one
                    FormSubmissionValue::query()
                        ->where( 'submission_id', $submission->getKey() )
                        ->where( 'field_id', $field->getKey() )
                        ->delete();

                    is_array( $uploaded )
                        ? $this->handleMultipleFileUploads( $submission, $field, $uploaded )
                        : $this->handleFileUpload( $submission, $field, $uploaded );
                }

                $submission->touch();
            } );
        } catch ( Throwable $exception ) {
            $this->deleteStoredFiles();

            throw $exception;
        } finally {
            $this->storedFiles = [];
        }

        // The old files are only deleted once the new ones are saved
        foreach ( $replaced as $upload ) {
            $upload->delete();
        }

        return $submission->unsetRelation( 'values' )->unsetRelation( 'uploads' );
    }

    /**
     * Removes one file from a submission's administrative file field.
     *
     * Deletes the stored file and its upload record. A multi-file value keeps
     * its other files; a value left with none is deleted. Fires the
     * submission's updated event.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The submission.
     * @param  FormUpload  $upload  The upload to remove.
     *
     * @return bool True when the upload belonged to one of the submission's administrative file fields and was removed.
     */
    public function removeAdministrativeUpload( FormSubmission $submission, FormUpload $upload ): bool
    {
        $submission->loadMissing( 'form.fields' );

        $field = $submission->form?->fields->firstWhere( 'id', $upload->field_id );

        if ( (int) $upload->submission_id !== (int) $submission->getKey() || ! $field instanceof FormField || ! $field->isAdministrative() || ! $field->is_file_field || $upload->isTransient() ) {
            return false;
        }

        DB::transaction( function () use ( $submission, $field, $upload ): void {
            $value = $this->administrativeFileValue( $submission, $field );

            if ( null !== $value ) {
                $remaining = array_values( array_filter(
                    array_map( fn ( FormUpload $item ): int => (int) $item->id, $value->getUploads() ),
                    fn ( int $id ): bool => $id !== (int) $upload->id,
                ) );

                if ( [] === $remaining ) {
                    $value->delete();
                } else {
                    $value->forceFill( [
                        'upload_id'   => $remaining[0],
                        'value_array' => $field->allowsMultipleFiles() ? $remaining : null,
                    ] )->save();
                }
            }

            $submission->touch();
        } );

        $upload->delete();

        $submission->unsetRelation( 'values' )->unsetRelation( 'uploads' );

        return true;
    }

    /**
     * Saves the values an admin entered for a submission's administrative fields.
     *
     * Only administrative fields that store values are written; a blank value
     * removes the stored one. Fires the submission's updated event once.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The submission.
     * @param  array<string, mixed>  $values  The values keyed by field name.
     *
     * @return FormSubmission The submission, with its `values` relation unloaded.
     */
    public function updateAdministrativeValues( FormSubmission $submission, array $values ): FormSubmission
    {
        $submission->loadMissing( 'form.fields' );

        DB::transaction( function () use ( $submission, $values ): void {
            foreach ( $submission->form->fields as $field ) {
                // A file field's files are saved apart (see updateAdministrativeFiles())
                if ( ! $field->isAdministrative() || $field->is_file_field || ! $field->storesValues() || ! array_key_exists( $field->name, $values ) ) {
                    continue;
                }

                $value = $values[ $field->name ];

                FormSubmissionValue::query()
                    ->where( 'submission_id', $submission->getKey() )
                    ->where( 'field_id', $field->getKey() )
                    ->delete();

                if ( null === $value || '' === $value || [] === $value ) {
                    continue;
                }

                $this->saveFieldValue( $submission, $field, $value );
            }

            $submission->touch();
        } );

        return $submission->unsetRelation( 'values' );
    }

    /**
     * Gets a submission's stored value for an administrative file field.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The submission.
     * @param  FormField  $field  The file field.
     *
     * @return FormSubmissionValue|null The value, or null when the submission has no file for the field.
     */
    public function administrativeFileValue( FormSubmission $submission, FormField $field ): ?FormSubmissionValue
    {
        return FormSubmissionValue::query()
            ->where( 'submission_id', $submission->getKey() )
            ->where( 'field_id', $field->getKey() )
            ->whereNull( 'redacted_at' )
            ->first();
    }

    /**
     * Gets the Livewire renderer property path a field's value is validated at.
     *
     * File inputs bind to `files.{name}`; every other input binds to
     * `formData.{name}`. An input nested under the field adds its sub-key.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The form field.
     * @param  string  $inputKey  The nested input's key, or `''` for the field's own value.
     *
     * @return string The validation key.
     */
    public function validationKey( FormField $field, string $inputKey = '' ): string
    {
        $key = ( $field->is_file_field ? 'files.' : 'formData.' ) . $field->name;

        return '' === $inputKey ? $key : "{$key}.{$inputKey}";
    }

    /**
     * Gets metadata from the current request.
     *
     * Respects privacy configuration settings for IP address collection
     * and anonymization.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request.
     *
     * @return array<string, string|null> The request metadata.
     */
    public function getRequestMetadata( Request $request ): array
    {
        $ipAddress = null;

        // Check if IP logging is enabled (nested under submission settings)
        if ( config( 'artisanpack.forms.privacy.submission.include_ip', true ) ) {
            $ipAddress = $request->ip();

            // Anonymize IP if configured
            if ( null !== $ipAddress && config( 'artisanpack.forms.privacy.submission.anonymize_ip', false ) ) {
                $ipAddress = $this->anonymizeIpAddress( $ipAddress );
            }
        }

        $userAgent = null;
        if ( config( 'artisanpack.forms.privacy.submission.include_user_agent', true ) ) {
            $userAgent = $request->userAgent();
        }

        return [
            'page_url'     => $request->fullUrl(),
            'referrer_url' => $request->header( 'referer' ),
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
        ];
    }

    /**
     * Saves a submission, its values and its uploads in one transaction.
     *
     * @since 1.6.0
     *
     * @param  Form  $form  The form being submitted.
     * @param  Collection<string, FormField>  $fields  The form's fields keyed by name.
     * @param  array<string, mixed>  $formData  The form data to store.
     * @param  array<string, array<array-key, UploadedFile>|UploadedFile|null>  $files  Uploaded files keyed by field name.
     * @param  array<string, mixed>  $metadata  Additional metadata (ip, user_agent, etc.).
     *
     * @return FormSubmission The created submission.
     */
    protected function createInTransaction( Form $form, Collection $fields, array $formData, array $files, array $metadata ): FormSubmission
    {
        return DB::transaction( function () use ( $form, $fields, $formData, $files, $metadata ): FormSubmission {
            // Registered before the first write, so once the transaction commits
            // the files are kept even if a FormSubmitted listener then throws.
            DB::afterCommit( function (): void {
                $this->storedFiles = [];
            } );

            // Create the submission record
            $submission = FormSubmission::create( [
                'form_id'      => $form->id,
                'page_url'     => $metadata['page_url'] ?? null,
                'referrer_url' => $metadata['referrer_url'] ?? null,
                'ip_address'   => $metadata['ip_address'] ?? null,
                'user_agent'   => $metadata['user_agent'] ?? null,
                'is_read'      => false,
                'is_spam'      => false,
                'is_starred'   => false,
            ] );

            $transientValues = [];

            // Save each field value (except file fields which are handled separately)
            foreach ( $formData as $fieldName => $value ) {
                $field = $fields->get( $fieldName );

                // Layout fields (heading, paragraph, divider, html) collect
                // nothing and have no rules, so a value sent for one is dropped
                if ( ! $field || $field->isLayoutField() ) {
                    continue;
                }

                // Skip file fields - they're handled separately in handleFileUpload()
                if ( $field->is_file_field ) {
                    continue;
                }

                if ( ! $field->storesValues() ) {
                    $transientValues[] = $this->buildValueAttributes( $submission, $field, $value );

                    continue;
                }

                $this->saveFieldValue( $submission, $field, $value );
            }

            // Handle file uploads
            foreach ( $files as $fieldName => $file ) {
                $field = $fields->get( $fieldName );

                if ( ! $field || ! $field->is_file_field || $field->isAdministrative() ) {
                    continue;
                }

                // A "Do not store" field's files are kept only for this request's deliveries
                if ( ! $field->storesValues() ) {
                    if ( null !== ( $attributes = $this->storeTransientUploads( $submission, $field, $file ) ) ) {
                        $transientValues[] = $attributes;
                    }

                    continue;
                }

                if ( is_array( $file ) && $field->allowsMultipleFiles() ) {
                    $this->handleMultipleFileUploads( $submission, $field, $file );

                    continue;
                }

                if ( $file instanceof UploadedFile ) {
                    $this->handleFileUpload( $submission, $field, $file );
                }
            }

            // Set before the transaction commits, so the FormSubmitted listeners
            // (dispatched after commit) see the values that are not stored.
            if ( [] !== $transientValues ) {
                $submission->withTransientValues( $transientValues, $fields );
            }

            return $submission;
        } );
    }

    /**
     * Removes the files stored by a submission whose transaction rolled back,
     * so none is left on a disk with no upload record pointing at it.
     *
     * @since 1.6.0
     */
    protected function deleteStoredFiles(): void
    {
        foreach ( $this->storedFiles as $stored ) {
            try {
                FormUpload::deleteStoredFile( $stored['disk'], $stored['path'], $stored['media_id'] );
            } catch ( Throwable $exception ) {
                Log::warning( 'ArtisanPackUI Forms: stored file not removed after a failed submission', [
                    'disk'     => $stored['disk'],
                    'path'     => $stored['path'],
                    'media_id' => $stored['media_id'],
                    'error'    => $exception::class,
                ] );
            }
        }
    }

    /**
     * Resolves the configured max attempts, falling back to the
     * hardcoded default if the config is missing or malformed.
     *
     * @since 1.1.2
     */
    protected function resolveRateLimitMaxAttempts(): int
    {
        $configured = config( 'artisanpack.forms.spam_protection.rate_limit.attempts' );

        return is_numeric( $configured ) && (int) $configured > 0
            ? (int) $configured
            : self::RATE_LIMIT_MAX_ATTEMPTS;
    }

    /**
     * Resolves the configured rate-limit decay window in seconds,
     * falling back to 60 if the config is missing or malformed.
     *
     * @since 1.1.2
     */
    protected function resolveRateLimitDecay(): int
    {
        $configured = config( 'artisanpack.forms.spam_protection.rate_limit.decay' );

        return is_numeric( $configured ) && (int) $configured > 0
            ? (int) $configured
            : 60;
    }

    /**
     * Sends notifications for a form submission.
     *
     * Queues all active notifications that pass their conditional logic checks.
     *
     * @since 1.0.0
     *
     * @param  FormSubmission  $submission  The form submission.
     *
     * @return int Number of notifications queued.
     */
    protected function sendNotifications( FormSubmission $submission ): int
    {
        return $this->notificationService->sendNotifications( $submission );
    }

    /**
     * Saves a field value to the submission.
     *
     * The field type's handler normalizes the value first. A drawn signature
     * is stored as an upload instead (see saveSignature()); one that is not a
     * valid PNG is stored empty.
     *
     * @since 1.0.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The form field.
     * @param  mixed  $value  The field value.
     *
     * @return FormSubmissionValue The created submission value.
     */
    protected function saveFieldValue( FormSubmission $submission, FormField $field, mixed $value ): FormSubmissionValue
    {
        if ( SignatureFieldType::TYPE === $field->type && SignatureFieldType::isDrawn( $value ) ) {
            $png = SignatureFieldType::decode( $value );

            if ( null !== $png ) {
                return $this->saveSignature( $submission, $field, $png );
            }

            $value = '';
        }

        return FormSubmissionValue::create( $this->buildValueAttributes( $submission, $field, $value ) );
    }

    /**
     * Stores a drawn signature as a PNG upload, with a submission value pointing at it.
     *
     * The file goes to the forms uploads disk, like other uploads, and is
     * removed again if the submission's transaction rolls back.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The signature field.
     * @param  string  $png  The PNG bytes.
     *
     * @throws RuntimeException If the file cannot be stored.
     *
     * @return FormSubmissionValue The created submission value.
     */
    protected function saveSignature( FormSubmission $submission, FormField $field, string $png ): FormSubmissionValue
    {
        $disk       = config( 'artisanpack.forms.uploads.disk', 'local' );
        $directory  = config( 'artisanpack.forms.uploads.directory', 'form-uploads' );
        $storedName = Str::uuid()->toString() . '.png';
        $path       = $directory . '/' . $submission->form_id . '/' . $storedName;

        if ( ! Storage::disk( $disk )->put( $path, $png ) ) {
            throw new RuntimeException( __( 'Unable to store uploaded file.' ) );
        }

        $this->storedFiles[] = ['disk' => $disk, 'path' => $path, 'media_id' => null];

        $upload = FormUpload::create( [
            'submission_id' => $submission->id,
            'field_id'      => $field->id,
            'original_name' => SignatureFieldType::UPLOAD_NAME,
            'stored_name'   => $storedName,
            'disk'          => $disk,
            'path'          => $path,
            'mime_type'     => 'image/png',
            'size'          => strlen( $png ),
        ] );

        return FormSubmissionValue::create( $this->uploadValueAttributes( $submission, $field, $upload ) );
    }

    /**
     * Builds the attributes a field value is stored with.
     *
     * The field type's handler normalizes the value first.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The form field.
     * @param  mixed  $value  The field value.
     *
     * @return array<string, mixed> The FormSubmissionValue attributes.
     */
    protected function buildValueAttributes( FormSubmission $submission, FormField $field, mixed $value ): array
    {
        $data = [
            'submission_id'     => $submission->id,
            'field_id'          => $field->id,
            'field_name'        => $field->name,
            'field_label'       => $field->label,
            'field_admin_label' => $field->getStoredAdminLabel(),
            'field_type'        => $field->type,
            'is_masked'         => $field->isMaskedInAdmin(),
            'redact_after_days' => $field->getRedactAfterDays(),
            'created_at'        => now(),
        ];

        $value = $field->fieldType()->normalize( $field, $value );

        // Handle array values (checkbox groups, multi-select)
        if ( is_array( $value ) ) {
            $data['value_array'] = $value;
            $data['value']       = null;
        } else {
            $data['value']       = (string) $value;
            $data['value_array'] = null;
        }

        return $data;
    }

    /**
     * Handles a file upload for a submission.
     *
     * @since 1.0.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The file field.
     * @param  UploadedFile  $file  The uploaded file.
     *
     * @throws InvalidArgumentException If the file extension is not allowed.
     *
     * @return FormUpload The created upload record.
     */
    protected function handleFileUpload( FormSubmission $submission, FormField $field, UploadedFile $file ): FormUpload
    {
        $upload = $this->storeUpload( $submission, $field, $file );

        // Create submission value linking to the upload
        FormSubmissionValue::create( $this->uploadValueAttributes( $submission, $field, $upload ) );

        return $upload;
    }

    /**
     * Stores each file of a multi-file field, with one submission value listing them all.
     *
     * The value points at the first upload in `upload_id` and lists every
     * upload ID, in the order the files were added, in `value_array`.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The file field.
     * @param  array<array-key, mixed>  $files  The uploaded files.
     *
     * @throws InvalidArgumentException If a file extension is not allowed.
     *
     * @return list<FormUpload> The created upload records.
     */
    protected function handleMultipleFileUploads( FormSubmission $submission, FormField $field, array $files ): array
    {
        $uploads = [];

        foreach ( $files as $file ) {
            if ( $file instanceof UploadedFile ) {
                $uploads[] = $this->storeUpload( $submission, $field, $file );
            }
        }

        if ( [] === $uploads ) {
            return [];
        }

        FormSubmissionValue::create( array_merge( $this->uploadValueAttributes( $submission, $field, $uploads[0] ), [
            'value_array' => array_map( fn ( FormUpload $upload ): int => (int) $upload->id, $uploads ),
        ] ) );

        return $uploads;
    }

    /**
     * Stores the files of a "Do not store" file field until the submission's deliveries are sent.
     *
     * The uploads start with the submission's own hold (see
     * TransientUploads). No submission value is saved; the attributes it
     * would have are returned for the submission's transient values.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The file field.
     * @param  mixed  $files  The uploaded file, or a list of them for a multi-file field.
     *
     * @throws InvalidArgumentException If a file extension is not allowed.
     *
     * @return array<string, mixed>|null The FormSubmissionValue attributes, or null when no file was uploaded.
     */
    protected function storeTransientUploads( FormSubmission $submission, FormField $field, mixed $files ): ?array
    {
        $files   = is_array( $files ) && $field->allowsMultipleFiles() ? $files : [$files];
        $uploads = [];

        foreach ( $files as $file ) {
            if ( $file instanceof UploadedFile ) {
                $uploads[] = $this->storeUpload( $submission, $field, $file, true );
            }
        }

        if ( [] === $uploads ) {
            return null;
        }

        $attributes = $this->uploadValueAttributes( $submission, $field, $uploads[0] );

        if ( $field->allowsMultipleFiles() ) {
            $attributes['value_array'] = array_map( fn ( FormUpload $upload ): int => (int) $upload->id, $uploads );
        }

        return $attributes;
    }

    /**
     * Builds the attributes of the submission value that points at an upload.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The file field.
     * @param  FormUpload  $upload  The (first) upload.
     *
     * @return array<string, mixed> The FormSubmissionValue attributes.
     */
    protected function uploadValueAttributes( FormSubmission $submission, FormField $field, FormUpload $upload ): array
    {
        return [
            'submission_id'     => $submission->id,
            'field_id'          => $field->id,
            'field_name'        => $field->name,
            'field_label'       => $field->label,
            'field_admin_label' => $field->getStoredAdminLabel(),
            'field_type'        => $field->type,
            'upload_id'         => $upload->id,
            'is_masked'         => $field->isMaskedInAdmin(),
            'redact_after_days' => $field->getRedactAfterDays(),
            'created_at'        => now(),
        ];
    }

    /**
     * Stores an uploaded file and records it as an upload of the submission.
     *
     * A field with Media Library storage saves the file as a media item and
     * records the item's file; when the media library does not take the file,
     * it is stored on the forms disk as usual (see MediaLibraryStorage).
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The form submission.
     * @param  FormField  $field  The file field.
     * @param  UploadedFile  $file  The uploaded file.
     * @param  bool  $transient  Whether the file is kept only until the submission's deliveries are sent (see TransientUploads).
     *
     * @throws InvalidArgumentException If the file extension is not allowed.
     *
     * @return FormUpload The created upload record.
     */
    protected function storeUpload( FormSubmission $submission, FormField $field, UploadedFile $file, bool $transient = false ): FormUpload
    {
        // Validate extension against allowlist, whichever storage takes the file
        $extension = $this->getValidatedExtension( $file );

        if ( $field->usesMediaLibraryStorage() && null !== ( $media = app( MediaLibraryStorage::class )->store( $file, $field, $extension ) ) ) {
            $this->storedFiles[] = ['disk' => $media['disk'], 'path' => $media['path'], 'media_id' => $media['media_id']];

            return FormUpload::create( [
                'submission_id'      => $submission->id,
                'field_id'           => $field->id,
                'media_id'           => $media['media_id'],
                'original_name'      => $file->getClientOriginalName(),
                'stored_name'        => basename( $media['path'] ),
                'disk'               => $media['disk'],
                'path'               => $media['path'],
                'mime_type'          => $media['mime_type'],
                'size'               => $media['size'],
                'pending_deliveries' => $transient ? 1 : null,
                'is_masked'          => $field->isMaskedInAdmin(),
            ] );
        }

        $disk      = config( 'artisanpack.forms.uploads.disk', 'local' );
        $directory = config( 'artisanpack.forms.uploads.directory', 'form-uploads' );

        // Generate a unique filename with validated extension
        $storedName  = Str::uuid()->toString() . '.' . $extension;
        $storagePath = $directory . '/' . $submission->form_id;

        // Get file size before storing (more reliable for Livewire temp files)
        $fileSize = $file->getSize();

        // Store the file using storeAs (works with both regular UploadedFile and Livewire's TemporaryUploadedFile)
        $path = $file->storeAs( $storagePath, $storedName, $disk );

        if ( false === $path ) {
            throw new RuntimeException( __( 'Unable to store uploaded file.' ) );
        }

        $this->storedFiles[] = ['disk' => $disk, 'path' => $path, 'media_id' => null];

        // Fallback: get size from stored file if original size was 0 or false
        if ( ! $fileSize ) {
            $fileSize = Storage::disk( $disk )->size( $path );
        }

        // Create the upload record
        return FormUpload::create( [
            'submission_id'      => $submission->id,
            'field_id'           => $field->id,
            'original_name'      => $file->getClientOriginalName(),
            'stored_name'        => $storedName,
            'disk'               => $disk,
            'path'               => $path,
            'mime_type'          => $file->getMimeType() ?? 'application/octet-stream',
            'size'               => $fileSize ?: 0,
            'pending_deliveries' => $transient ? 1 : null,
            'is_masked'          => $field->isMaskedInAdmin(),
        ] );
    }

    /**
     * Gets a validated file extension from the uploaded file.
     *
     * Uses server-detected extension when available, falls back to client extension,
     * and validates against configured allowlist.
     *
     * @since 1.0.0
     *
     * @param  UploadedFile  $file  The uploaded file.
     *
     * @throws InvalidArgumentException If the extension is not allowed.
     *
     * @return string The validated file extension.
     */
    protected function getValidatedExtension( UploadedFile $file ): string
    {
        // Prefer server-detected extension based on MIME type
        $extension = $file->guessExtension();

        // Fall back to client-provided extension if server detection fails
        if ( null === $extension || '' === $extension ) {
            $extension = strtolower( $file->getClientOriginalExtension() );
        }

        // Sanitize extension (remove any non-alphanumeric characters)
        $extension = preg_replace( '/[^a-z0-9]/', '', strtolower( $extension ) );

        if ( null === $extension || '' === $extension ) {
            throw new InvalidArgumentException( __( 'Unable to determine file extension.' ) );
        }

        // Get allowed extensions from config
        $allowedExtensions = config( 'artisanpack.forms.uploads.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar',
        ] );

        // Require explicit null/false to allow any extension (empty array = use defaults)
        if ( null === $allowedExtensions || false === $allowedExtensions ) {
            return $extension;
        }

        // If empty array, something is misconfigured - fail safely
        if ( empty( $allowedExtensions ) ) {
            throw new InvalidArgumentException(
                __( 'No allowed file extensions configured. Please set artisanpack.forms.uploads.allowed_extensions.' ),
            );
        }

        // Validate against allowlist
        $allowedExtensions = array_map( 'strtolower', $allowedExtensions );
        if ( ! in_array( $extension, $allowedExtensions, true ) ) {
            $this->logSecurityEvent( 'invalid_file_extension', [
                'attempted_extension' => $extension,
                'original_filename'   => $file->getClientOriginalName(),
                'mime_type'           => $file->getMimeType(),
                'ip_address'          => request()->ip(),
            ] );

            throw new InvalidArgumentException(
                __( "File extension ':extension' is not allowed. Allowed extensions: :allowed", ['extension' => $extension, 'allowed' => implode( ', ', $allowedExtensions )] ),
            );
        }

        return $extension;
    }

    /**
     * Logs a security-related event.
     *
     * Security events are logged at the warning level with a consistent format
     * for easy filtering and analysis.
     *
     * @since 1.0.0
     *
     * @param  string  $eventType  The type of security event.
     * @param  array<string, mixed>  $context  Additional context data.
     */
    protected function logSecurityEvent( string $eventType, array $context = [] ): void
    {
        if ( ! config( 'artisanpack.forms.security.logging_enabled', true ) ) {
            return;
        }

        Log::warning( "[Forms Security] {$eventType}", array_merge( [
            'event_type' => $eventType,
            'timestamp'  => now()->toIso8601String(),
        ], $context ) );
    }

    /**
     * Anonymizes an IP address by masking the last octet (IPv4) or last 80 bits (IPv6).
     *
     * This provides privacy while still allowing for general geographic analysis.
     * Uses inet_pton/inet_ntop for proper handling of compressed IPv6 addresses.
     *
     * @since 1.0.0
     *
     * @param  string  $ipAddress  The IP address to anonymize.
     *
     * @return string The anonymized IP address.
     */
    protected function anonymizeIpAddress( string $ipAddress ): string
    {
        // Handle IPv4
        if ( filter_var( $ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return preg_replace( '/\.\d+$/', '.0', $ipAddress ) ?? $ipAddress;
        }

        // Handle IPv6 - mask the last 80 bits (10 bytes)
        if ( filter_var( $ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // Convert to binary representation (16 bytes)
            $binary = @inet_pton( $ipAddress );

            if ( false === $binary ) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            // Zero out the last 10 bytes (80 bits) - keep first 6 bytes (48 bits / 3 groups)
            $anonymized = substr( $binary, 0, 6 ) . str_repeat( "\x00", 10 );

            // Convert back to string representation
            $result = @inet_ntop( $anonymized );

            if ( false === $result ) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            return $result;
        }

        return $ipAddress;
    }
}
