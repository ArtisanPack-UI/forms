<?php

/**
 * Reorder steps API request.
 *
 * Handles validation for reordering form steps via the API.
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
use Illuminate\Validation\Rule;

/**
 * Reorder steps API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class ReorderStepsApiRequest extends FormRequest
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
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function rules(): array
    {
        $formId = $this->route( 'form' )?->id;

        return [
            'ordered_ids'   => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct', Rule::exists( 'form_steps', 'id' )->where( 'form_id', $formId )],
        ];
    }
}
