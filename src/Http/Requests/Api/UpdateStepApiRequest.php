<?php

/**
 * Update step API request.
 *
 * Handles validation for updating a step via the API.
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

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update step API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class UpdateStepApiRequest extends FormRequest
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
        return $this->user()?->can( 'update', $this->route( 'form' ) ) ?? false;
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
            'title'            => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'next_button_text' => ['nullable', 'string', 'max:100'],
            'prev_button_text' => ['nullable', 'string', 'max:100'],
        ];
    }
}
