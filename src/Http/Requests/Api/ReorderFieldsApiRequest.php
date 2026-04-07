<?php

/**
 * Reorder fields API request.
 *
 * Handles validation for reordering form fields via the API.
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
 * Reorder fields API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class ReorderFieldsApiRequest extends FormRequest
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

        $stepId = $this->input( 'step_id' );

        return [
            'ordered_uuids'   => ['required', 'array', 'min:1'],
            'ordered_uuids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists( 'form_fields', 'uuid' )->where( function ( $query ) use ( $formId, $stepId ): void {
                    $query->where( 'form_id', $formId );

                    if ( null !== $stepId ) {
                        $query->where( 'step_id', $stepId );
                    } else {
                        $query->whereNull( 'step_id' );
                    }
                } ),
            ],
            'step_id' => ['nullable', 'integer', Rule::exists( 'form_steps', 'id' )->where( 'form_id', $formId )],
        ];
    }
}
