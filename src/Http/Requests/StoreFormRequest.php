<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Requests;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * StoreFormRequest
 *
 * Handles validation for creating a new form.
 *
 * @since 1.0.0
 */
class StoreFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * If a 'create' ability is defined for Form in the consuming app's
     * AuthServiceProvider, it will be used. Otherwise, defaults to true
     * allowing any authenticated user to create forms.
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
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
     * Get the validation error messages.
     *
     * @return array<string, string>
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert empty slug to null so it gets auto-generated
        if ( $this->has( 'slug' ) && '' === $this->slug ) {
            $this->merge( ['slug' => null] );
        }
    }
}
