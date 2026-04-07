<?php

/**
 * Form step API resource.
 *
 * Transforms FormStep model instances into JSON API responses.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Form step API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormStepResource extends JsonResource
{
    /**
     * Transforms the resource into an array.
     *
     * @since 1.1.0
     *
     * @param Request $request The incoming request.
     *
     * @return array<string, mixed> The transformed resource data.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'               => $this->id,
            'form_id'          => $this->form_id,
            'title'            => $this->title,
            'description'      => $this->description,
            'sort_order'       => $this->sort_order,
            'next_button_text' => $this->next_button_text,
            'prev_button_text' => $this->prev_button_text,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
            'fields'           => FormFieldResource::collection( $this->whenLoaded( 'fields' ) ),
        ];
    }
}
