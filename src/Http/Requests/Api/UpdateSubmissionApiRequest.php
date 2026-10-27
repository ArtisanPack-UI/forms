<?php

/**
 * Update submission API request.
 *
 * Handles validation for updating submission metadata via the API.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Requests\Api;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update submission API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class UpdateSubmissionApiRequest extends FormRequest
{
    use FiresValidationHooks;

    /**
     * Determines if the user is authorized to make this request.
     *
     * @since 1.1.0
     *
     * @return bool True if authorized.
     */
    public function authorize(): bool
    {
        return $this->user()?->can( 'update', $this->route( 'submission' ) ) ?? false;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * `admin_values` holds the form's administrative fields keyed by name,
     * validated with each field's rules when sent (since 1.6.0). Since
     * 1.6.0 too, a multipart request may send `admin_files[{name}]` (a list
     * for a multi-file field) to replace an administrative file field's
     * files, checked with the field's file rules whenever `admin_values` or
     * `admin_files` is sent, and `remove_admin_uploads` lists the IDs of
     * administrative files to delete.
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function rules(): array
    {
        $rules = [
            'is_read'                => ['nullable', 'boolean'],
            'is_starred'             => ['nullable', 'boolean'],
            'is_spam'                => ['nullable', 'boolean'],
            'admin_notes'            => ['nullable', 'string', 'max:5000'],
            'admin_values'           => ['sometimes', 'array'],
            'admin_files'            => ['sometimes', 'array'],
            'remove_admin_uploads'   => ['sometimes', 'array'],
            'remove_admin_uploads.*' => ['integer', $this->removableUploadRule()],
        ];

        $submission = $this->route( 'submission' );

        if ( ! $submission instanceof FormSubmission ) {
            return $rules;
        }

        $service = app( SubmissionService::class );

        if ( $this->has( 'admin_values' ) ) {
            $rules = array_merge( $rules, $service->buildAdministrativeValidationRules( $this->editableAdministrativeFields(), $submission, 'admin_values' ) );
        }

        if ( $this->has( 'admin_values' ) || $this->has( 'admin_files' ) ) {
            $rules = array_merge( $rules, $service->buildAdministrativeFileValidationRules( $this->editableAdministrativeFields(), $submission, 'admin_files' ) );
        }

        return $rules;
    }

    /**
     * Gets the validated files for the administrative file fields the user may edit.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The files keyed by field name; empty when none were sent.
     */
    public function administrativeFiles(): array
    {
        $files = $this->validated( 'admin_files' );

        if ( ! is_array( $files ) ) {
            return [];
        }

        $fields = $this->editableAdministrativeFields()->filter( fn ( FormField $field ): bool => $field->is_file_field );

        return array_intersect_key( $files, $fields->keyBy( 'name' )->all() );
    }

    /**
     * Gets the IDs of the administrative files to delete.
     *
     * @since 1.6.0
     *
     * @return list<int> The upload IDs.
     */
    public function uploadsToRemove(): array
    {
        $ids = $this->validated( 'remove_admin_uploads' );

        return is_array( $ids ) ? array_values( array_unique( array_map( 'intval', $ids ) ) ) : [];
    }

    /**
     * Gets the validated administrative field values the user may edit.
     *
     * Values for other fields, and for fields masked from the user, are dropped.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed>|null The values keyed by field name, or null when none were sent.
     */
    public function administrativeValues(): ?array
    {
        $values = $this->validated( 'admin_values' );

        if ( ! is_array( $values ) ) {
            return null;
        }

        $fields = $this->editableAdministrativeFields()->reject( fn ( FormField $field ): bool => $field->is_file_field );

        return array_intersect_key( $values, $fields->keyBy( 'name' )->all() );
    }

    /**
     * Gets the validation messages for the administrative fields.
     *
     * @since 1.6.0
     *
     * @return array<string, string> The messages.
     */
    public function messages(): array
    {
        $messages = [];

        foreach ( $this->editableAdministrativeFields() as $field ) {
            $prefix = $field->is_file_field ? 'admin_files' : 'admin_values';

            foreach ( $field->buildValidationMessages() as $key => $message ) {
                $messages[ "{$prefix}.{$field->name}.{$key}" ] = $message;
            }
        }

        $messages['remove_admin_uploads.*'] = __( 'That file cannot be removed from this submission.' );

        return $messages;
    }

    /**
     * Gets the rule that limits removable uploads to the administrative files the user may edit.
     *
     * @since 1.6.0
     *
     * @return Closure The rule.
     */
    protected function removableUploadRule(): Closure
    {
        return function ( string $attribute, mixed $value, Closure $fail ): void {
            $submission = $this->route( 'submission' );
            $fieldIds   = $this->editableAdministrativeFields()->filter( fn ( FormField $field ): bool => $field->is_file_field )->modelKeys();

            $removable = $submission instanceof FormSubmission && is_numeric( $value ) && FormUpload::query()
                ->where( 'submission_id', $submission->getKey() )
                ->whereKey( (int) $value )
                ->whereIn( 'field_id', $fieldIds )
                ->stored()
                ->exists();

            if ( ! $removable ) {
                $fail( __( 'That file cannot be removed from this submission.' ) );
            }
        };
    }

    /**
     * Gets the submission's administrative fields the user may edit.
     *
     * Fields masked in the admin are left out unless the user may reveal
     * them, matching the fields the submission resource returns.
     *
     * @since 1.6.0
     *
     * @return Collection<int, FormField> The fields.
     */
    protected function editableAdministrativeFields(): Collection
    {
        $submission = $this->route( 'submission' );

        if ( ! $submission instanceof FormSubmission || null === $submission->form ) {
            return new Collection();
        }

        $canReveal = FieldPrivacy::canReveal( $this->user() );

        return $submission->form->fields
            ->filter( fn ( FormField $field ): bool => $field->isAdministrative() && ( $canReveal || ! $field->isMaskedInAdmin() ) )
            ->values();
    }
}
