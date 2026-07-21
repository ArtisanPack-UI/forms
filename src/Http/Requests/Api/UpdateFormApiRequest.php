<?php

/**
 * Update form API request.
 *
 * Handles validation for updating a form via the API.
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
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update form API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class UpdateFormApiRequest extends FormRequest
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
        $form = $this->route( 'form' );

        if ( ! $form instanceof Form ) {
            return false;
        }

        return $this->user()?->can( 'update', $form ) ?? false;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function rules(): array
    {
        $formId = $this->route( 'form' )?->id;

        // PATCH-style partial updates: each field is `sometimes` so the
        // auto-save can send only the keys the user actually changed
        // without tripping `required` on untouched fields.
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique( 'forms', 'slug' )->ignore( $formId ),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'description'           => ['sometimes', 'nullable', 'string', 'max:1000'],
            'submit_button_text'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'success_message'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'redirect_url'          => ['sometimes', 'nullable', 'url', 'max:255'],
            'is_active'             => ['sometimes', 'nullable', 'boolean'],
            'is_multi_step'         => ['sometimes', 'nullable', 'boolean'],
            'show_progress_bar'     => ['sometimes', 'nullable', 'boolean'],
            'allow_step_navigation' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Prepares the data for validation.
     *
     * @since 1.1.0
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->fireBeforeValidate();

        if ( $this->has( 'slug' ) && '' === $this->slug ) {
            $this->merge( ['slug' => null] );
        }
    }
}
