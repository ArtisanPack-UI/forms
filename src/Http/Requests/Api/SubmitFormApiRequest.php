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
            'data' => ['present', 'array'],
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
}
