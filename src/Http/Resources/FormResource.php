<?php

/**
 * Form API resource.
 *
 * Transforms Form model instances into JSON API responses.
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
 * Form API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormResource extends JsonResource
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
            'id'                       => $this->id,
            'name'                     => $this->name,
            'slug'                     => $this->slug,
            'description'              => $this->description,
            'submit_button_text'       => $this->submit_button_text,
            'success_message'          => $this->success_message,
            'redirect_url'             => $this->redirect_url,
            'is_multi_step'            => $this->is_multi_step,
            'show_progress_bar'        => $this->show_progress_bar,
            'allow_step_navigation'    => $this->allow_step_navigation,
            'is_active'                => $this->is_active,
            'created_at'               => $this->created_at?->toIso8601String(),
            'updated_at'               => $this->updated_at?->toIso8601String(),
            'fields'                   => FormFieldResource::collection( $this->whenLoaded( 'fields' ) ),
            'steps'                    => FormStepResource::collection( $this->whenLoaded( 'steps' ) ),
            'notifications'            => FormNotificationResource::collection( $this->whenLoaded( 'notifications' ) ),
            'total_submissions_count'  => $this->when( null !== $this->total_submissions_count, $this->total_submissions_count ),
            'unread_submissions_count' => $this->when( null !== $this->unread_submissions_count, $this->unread_submissions_count ),
        ];
    }
}
