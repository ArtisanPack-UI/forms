<?php

/**
 * Form render API resource.
 *
 * Transforms a Form model into a client-side rendering definition
 * including fields, steps, conditional logic, and configuration.
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
 * Form render API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormRenderResource extends JsonResource
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
            'id'                    => $this->id,
            'name'                  => $this->name,
            'slug'                  => $this->slug,
            'description'           => $this->description,
            'submit_button_text'    => $this->submit_button_text,
            'success_message'       => $this->success_message,
            'redirect_url'          => $this->redirect_url,
            'is_multi_step'         => $this->is_multi_step,
            'show_progress_bar'     => $this->show_progress_bar,
            'allow_step_navigation' => $this->allow_step_navigation,
            'fields'                => FormFieldResource::collection( $this->resource->fields ),
            'steps'                 => FormStepResource::collection( $this->resource->steps ),
            'config'                => [
                'honeypot' => [
                    'enabled'    => config( 'artisanpack.forms.spam_protection.honeypot.enabled', true ),
                    'field_name' => config( 'artisanpack.forms.spam_protection.honeypot.field_name', 'website_url' ),
                ],
                'display' => config( 'artisanpack.forms.display', [] ),
            ],
        ];
    }
}
