<?php

/**
 * Store field API request.
 *
 * Handles validation for creating a new field via the API.
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

use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store field API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class StoreFieldApiRequest extends FormRequest
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
            'type'              => ['required', 'string', 'max:50'],
            'step_id'           => ['nullable', 'integer', Rule::exists( 'form_steps', 'id' )->where( 'form_id', $formId )],
            'label'             => ['nullable', 'string', 'max:255'],
            'placeholder'       => ['nullable', 'string', 'max:255'],
            'help_text'         => ['nullable', 'string', 'max:1000'],
            'is_required'       => ['nullable', 'boolean'],
            'validation_rules'  => ['nullable', 'array'],
            'field_config'      => ['nullable', 'array'],
            'default_value'     => ['nullable', 'string', 'max:1000'],
            'conditional_logic' => ['nullable', 'array'],
            'width'             => ['nullable', 'string', 'in:full,half,third,two-thirds,quarter,three-quarters'],
            'css_classes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
