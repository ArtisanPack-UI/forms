<?php

/**
 * Store form request.
 *
 * Handles validation for creating a new form.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Requests;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Store form request class.
 *
 * Handles validation for creating a new form.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class StoreFormRequest extends FormRequest
{
    use FiresValidationHooks;

    /**
     * Determines if the user is authorized to make this request.
     *
     * If a 'create' ability is defined for Form in the consuming app's
     * AuthServiceProvider, it will be used. Otherwise, defaults to true
     * allowing any authenticated user to create forms.
     *
     * @since 1.0.0
     *
     * @return bool True if authorized to create forms.
     */
    public function authorize(): bool
    {
        // Check if a policy/gate is defined for creating forms
        if ( Gate::has( 'create-form' ) || Gate::getPolicyFor( Form::class ) ) {
            return $this->user()?->can( 'create', Form::class ) ?? false;
        }

        // Default: allow authenticated users (auth middleware handles unauthenticated)
        return true;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 1.0.0
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
     * Gets the validation error messages.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required'    => 'The form name is required.',
            'name.max'         => 'The form name cannot exceed 255 characters.',
            'slug.unique'      => 'This slug is already in use by another form.',
            'slug.regex'       => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'description.max'  => 'The description cannot exceed 1000 characters.',
            'redirect_url.url' => 'Please enter a valid URL for the redirect.',
        ];
    }

    /**
     * Prepares the data for validation.
     *
     * Converts empty slug to null so it gets auto-generated.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->fireBeforeValidate();

        // Convert empty slug to null so it gets auto-generated
        if ( $this->has( 'slug' ) && '' === $this->slug ) {
            $this->merge( ['slug' => null] );
        }
    }
}
