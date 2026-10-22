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
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
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
     * validated with each field's rules when sent (since 1.6.0).
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function rules(): array
    {
        $rules = [
            'is_read'      => ['nullable', 'boolean'],
            'is_starred'   => ['nullable', 'boolean'],
            'is_spam'      => ['nullable', 'boolean'],
            'admin_notes'  => ['nullable', 'string', 'max:5000'],
            'admin_values' => ['sometimes', 'array'],
        ];

        $submission = $this->route( 'submission' );

        if ( ! $this->has( 'admin_values' ) || ! $submission instanceof FormSubmission ) {
            return $rules;
        }

        return array_merge(
            $rules,
            app( SubmissionService::class )->buildAdministrativeValidationRules( $this->editableAdministrativeFields(), $submission, 'admin_values' ),
        );
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

        return array_intersect_key( $values, $this->editableAdministrativeFields()->keyBy( 'name' )->all() );
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
            foreach ( $field->buildValidationMessages() as $key => $message ) {
                $messages[ "admin_values.{$field->name}.{$key}" ] = $message;
            }
        }

        return $messages;
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
