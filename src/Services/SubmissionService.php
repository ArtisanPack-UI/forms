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

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Support\SubmissionRules;
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
     * values for the notifications and webhooks of this request only. An
     * email field's confirmation (`{name}_confirmation`) and the token of a
     * type that stores no value (such as a CAPTCHA) are dropped before the
     * filter runs.
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form being submitted.
     * @param  array<string, mixed>  $formData  The submitted form data.
     * @param  array<string, UploadedFile|null>  $files  Uploaded files keyed by field name.
     * @param  array<string, mixed>  $metadata  Additional metadata (ip, user_agent, etc.).
     *
     * @throws RuntimeException If the submission cannot be created.
     *
     * @return FormSubmission The created submission.
     */
    public function create( Form $form, array $formData, array $files = [], array $metadata = [] ): FormSubmission
    {
        $fields = $form->fields()->get()->keyBy( 'name' );

        // Confirmation inputs and challenge tokens are checked, never kept
        $formData = $this->withoutTransientInputs( $fields, $formData );

        // Apply filter hook to allow modifying submission data before saving
        $formData = applyFilters( 'ap.forms.submissionData', $formData, $form );

        // Read-only fields keep their own value whatever the client sent
        $formData = $this->applyReadOnlyValues( $fields, $formData, true );

        // Administrative fields are never filled in by the public form
        $formData = array_diff_key( $formData, $fields->filter( fn ( FormField $field ): bool => $field->isAdministrative() )->all() );

        $submission = DB::transaction( function () use ( $form, $fields, $formData, $files, $metadata ): FormSubmission {
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

                if ( ! $field ) {
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

            // Set before the transaction commits, so the FormSubmitted listeners
            // (dispatched after commit) see the values that are not stored.
            if ( [] !== $transientValues ) {
                $submission->withTransientValues( $transientValues, $fields );
            }

            // Handle file uploads
            foreach ( $files as $fieldName => $file ) {
                if ( ! $file instanceof UploadedFile ) {
                    continue;
                }

                $field = $fields->get( $fieldName );

                if ( ! $field || ! $field->is_file_field ) {
                    continue;
                }

                $this->handleFileUpload( $submission, $field, $file );
            }

            return $submission;
        } );

        // Send notifications after transaction completes (outside transaction to ensure submission is persisted)
        $this->sendNotifications( $submission );

        return $submission;
    }

    /**
     * Removes the submitted inputs that are checked but never stored.
     *
     * An email field's confirmation input (unless a field has that name) and
     * the value of any field whose type definition sets `stores_value` to
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

            if ( false === ( $field->fieldType()->definition()['stores_value'] ?? true ) ) {
                unset( $formData[ $field->name ] );
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
            $elapsed = time() - $formLoadedAt;
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
     * submission being edited.
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
            if ( ! $field->isAdministrative() ) {
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
                if ( ! $field->isAdministrative() || ! $field->storesValues() || ! array_key_exists( $field->name, $values ) ) {
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
     * The field type's handler normalizes the value first.
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
        return FormSubmissionValue::create( $this->buildValueAttributes( $submission, $field, $value ) );
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
        $disk      = config( 'artisanpack.forms.uploads.disk', 'local' );
        $directory = config( 'artisanpack.forms.uploads.directory', 'form-uploads' );

        // Validate extension against allowlist
        $extension = $this->getValidatedExtension( $file );

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

        // Fallback: get size from stored file if original size was 0 or false
        if ( ! $fileSize ) {
            $fileSize = Storage::disk( $disk )->size( $path );
        }

        // Create the upload record
        $upload = FormUpload::create( [
            'submission_id' => $submission->id,
            'field_id'      => $field->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => $storedName,
            'disk'          => $disk,
            'path'          => $path,
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'size'          => $fileSize ?: 0,
        ] );

        // Create submission value linking to the upload
        FormSubmissionValue::create( [
            'submission_id'     => $submission->id,
            'field_id'          => $field->id,
            'field_name'        => $field->name,
            'field_label'       => $field->label,
            'field_admin_label' => $field->getStoredAdminLabel(),
            'field_type'        => $field->type,
            'upload_id'         => $upload->id,
            'created_at'        => now(),
        ] );

        return $upload;
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
