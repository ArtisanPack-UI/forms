<?php

/**
 * Submit form API request.
 *
 * Handles validation for public form submission via the API.
 * Dynamically builds validation rules based on the form's fields.
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

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Submit form API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class SubmitFormApiRequest extends FormRequest
{
    use FiresValidationHooks;

    /**
     * Determines if the user is authorized to make this request.
     *
     * Public form submission — always authorized if the form is active.
     *
     * @since 1.1.0
     *
     * @return bool True if authorized.
     */
    public function authorize(): bool
    {
        $form = $this->route( 'form' );

        return $form instanceof Form && $form->is_active;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * Dynamically builds rules from the form's field definitions,
     * respecting conditional logic for hidden fields. Administrative fields
     * are not validated, since the public form does not show them.
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, string>> The validation rules.
     */
    public function rules(): array
    {
        $form = $this->route( 'form' );

        if ( ! $form instanceof Form ) {
            return [];
        }

        $form->load( 'fields' );

        $data = $this->input( 'data', [] );

        if ( ! is_array( $data ) ) {
            $data = [];
        }

        $conditionalLogicService = app( ConditionalLogicService::class );

        $hiddenFields = $conditionalLogicService->getHiddenFields(
            $form->fields,
            $data,
        );

        $rules = [
            'data'  => ['present', 'array'],
            'files' => ['sometimes', 'array'],
        ];

        foreach ( $form->fields as $field ) {
            if ( isset( $hiddenFields[ $field->name ] ) && $hiddenFields[ $field->name ] ) {
                continue;
            }

            // Layout fields collect nothing; administrative fields are not on the public form
            if ( $field->isLayoutField() || $field->isAdministrative() ) {
                continue;
            }

            $prefix = $field->is_file_field ? 'files' : 'data';

            foreach ( $field->buildKeyedValidationRules() as $inputKey => $fieldRules ) {
                $key           = '' === $inputKey ? "{$prefix}.{$field->name}" : "{$prefix}.{$field->name}.{$inputKey}";
                $rules[ $key ] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Gets the validation messages for the form's fields.
     *
     * The field types' messages, with each field's custom error and required
     * messages applied (see FormField::buildValidationMessages()).
     *
     * @since 1.6.0
     *
     * @return array<string, string> The messages.
     */
    public function messages(): array
    {
        $form = $this->route( 'form' );

        if ( ! $form instanceof Form ) {
            return [];
        }

        $form->loadMissing( 'fields' );

        $messages = [];

        foreach ( $form->fields as $field ) {
            $prefix = ( $field->is_file_field ? 'files.' : 'data.' ) . $field->name;

            foreach ( $field->buildValidationMessages() as $key => $message ) {
                $messages[ "{$prefix}.{$key}" ] = $message;
            }
        }

        return $messages;
    }

    /**
     * Gets the sanitized form data with hidden, administrative and layout fields removed.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed> The sanitized form data.
     */
    public function sanitizedData(): array
    {
        $form = $this->route( 'form' );

        if ( ! $form instanceof Form ) {
            return [];
        }

        $form->loadMissing( 'fields' );

        $data = $this->input( 'data', [] );

        if ( ! is_array( $data ) ) {
            return [];
        }

        $conditionalLogicService = app( ConditionalLogicService::class );
        $hiddenFields            = $conditionalLogicService->getHiddenFields(
            $form->fields,
            $data,
        );

        // Strip hidden field values from the data
        foreach ( $hiddenFields as $fieldName => $isHidden ) {
            if ( $isHidden ) {
                unset( $data[ $fieldName ] );
            }
        }

        // Administrative fields are filled in by admins on the submission,
        // and layout fields collect nothing (since 1.6.0)
        foreach ( $form->fields as $field ) {
            if ( $field->isAdministrative() || $field->isLayoutField() ) {
                unset( $data[ $field->name ] );
            }
        }

        return $data;
    }

    /**
     * Gets the sanitized files with hidden fields removed.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed> The sanitized files.
     */
    public function sanitizedFiles(): array
    {
        $form = $this->route( 'form' );

        if ( ! $form instanceof Form ) {
            return [];
        }

        $form->loadMissing( 'fields' );

        $files = $this->allFiles();
        $files = $files['files'] ?? [];

        $data = $this->input( 'data', [] );

        if ( ! is_array( $data ) ) {
            $data = [];
        }

        $conditionalLogicService = app( ConditionalLogicService::class );
        $hiddenFields            = $conditionalLogicService->getHiddenFields(
            $form->fields,
            $data,
        );

        // Strip hidden field files
        foreach ( $hiddenFields as $fieldName => $isHidden ) {
            if ( $isHidden ) {
                unset( $files[ $fieldName ] );
            }
        }

        return $files;
    }

    /**
     * Fires the before-validate hook and replaces submitted read-only field values.
     *
     * A read-only field keeps its own value whatever the client sent, so that
     * value is what gets validated and stored. Numbers typed in a number
     * field's format are converted to plain decimals, URLs typed without a
     * scheme get one and emptied rich text is blanked (since 1.6.0; see
     * SubmissionService::normalizeSubmittedValues()). Calculations are
     * worked out on the server, so conditional logic reads their real results
     * (see SubmissionService::applyVisibleCalculations()).
     *
     * @since 1.6.0
     */
    protected function prepareForValidation(): void
    {
        $this->fireBeforeValidate();

        $form = $this->route( 'form' );
        $data = $this->input( 'data' );

        if ( ! $form instanceof Form || ! is_array( $data ) ) {
            return;
        }

        $form->loadMissing( 'fields' );

        $submissionService = app( SubmissionService::class );

        $data = $submissionService->normalizeSubmittedValues(
            $form->fields,
            $submissionService->applyReadOnlyValues( $form->fields, $data ),
        );

        $this->merge( [
            'data' => $submissionService->applyVisibleCalculations( $form->fields, $data ),
        ] );
    }
}
