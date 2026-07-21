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
     * respecting conditional logic for hidden fields.
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

            if ( $field->isLayoutField() ) {
                continue;
            }

            $fieldRules = $field->buildValidationRules();

            if ( ! empty( $fieldRules ) ) {
                $prefix                              = 'file' === $field->type ? 'files' : 'data';
                $rules[ "{$prefix}.{$field->name}" ] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Gets the sanitized form data with hidden fields removed.
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
}
