<?php

/**
 * Store form API request.
 *
 * Handles validation for creating a new form via the API.
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
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store form API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class StoreFormApiRequest extends FormRequest
{
    /**
     * Determines if the user is authorized to make this request.
     *
     * @since 1.1.0
     *
     * @return bool True if authorized.
     */
    public function authorize(): bool
    {
        return $this->user()?->can( 'create', Form::class ) ?? false;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, string>> The validation rules.
     */
    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'slug'                  => ['nullable', 'string', 'max:255', 'unique:forms,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'submit_button_text'    => ['nullable', 'string', 'max:100'],
            'success_message'       => ['nullable', 'string', 'max:2000'],
            'redirect_url'          => ['nullable', 'url', 'max:255'],
            'is_active'             => ['nullable', 'boolean'],
            'is_multi_step'         => ['nullable', 'boolean'],
            'show_progress_bar'     => ['nullable', 'boolean'],
            'allow_step_navigation' => ['nullable', 'boolean'],
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
        if ( $this->has( 'slug' ) && '' === $this->slug ) {
            $this->merge( ['slug' => null] );
        }
    }
}
