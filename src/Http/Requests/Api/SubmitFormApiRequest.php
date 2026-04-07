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

        $conditionalLogicService = app( ConditionalLogicService::class );
        $submissionService       = app( SubmissionService::class );

        $hiddenFields = $conditionalLogicService->getHiddenFields(
            $form->fields,
            $this->input( 'data', [] ),
        );

        $rules = [];

        foreach ( $form->fields as $field ) {
            if ( isset( $hiddenFields[ $field->name ] ) && $hiddenFields[ $field->name ] ) {
                continue;
            }

            if ( in_array( $field->type, ['heading', 'paragraph', 'divider', 'html'] ) ) {
                continue;
            }

            $fieldRules = $field->buildValidationRules();

            if ( ! empty( $fieldRules ) ) {
                $rules[ "data.{$field->name}" ] = $fieldRules;
            }
        }

        return $rules;
    }
}
