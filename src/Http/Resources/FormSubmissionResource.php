<?php

/**
 * Form submission API resource.
 *
 * Transforms FormSubmission model instances into JSON API responses.
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
 * Form submission API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormSubmissionResource extends JsonResource
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
            'id'                => $this->id,
            'form_id'           => $this->form_id,
            'submission_number' => $this->submission_number,
            'page_url'          => $this->page_url,
            'referrer_url'      => $this->referrer_url,
            'ip_address'        => $this->when(
                config( 'artisanpack.forms.privacy.include_ip_address', false ),
                $this->ip_address,
            ),
            'user_agent' => $this->when(
                config( 'artisanpack.forms.privacy.include_user_agent', false ),
                $this->user_agent,
            ),
            'is_read'     => $this->is_read,
            'is_spam'     => $this->is_spam,
            'is_starred'  => $this->is_starred,
            'admin_notes' => $this->admin_notes,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
            'values'      => FormSubmissionValueResource::collection( $this->whenLoaded( 'values' ) ),
            'uploads'     => FormUploadResource::collection( $this->whenLoaded( 'uploads' ) ),
        ];
    }
}
