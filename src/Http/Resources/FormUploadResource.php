<?php

/**
 * Form upload API resource.
 *
 * Transforms FormUpload model instances into JSON API responses.
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
 * Form upload API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormUploadResource extends JsonResource
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
            'id'            => $this->id,
            'submission_id' => $this->submission_id,
            'field_id'      => $this->field_id,
            'original_name' => $this->original_name,
            'mime_type'     => $this->mime_type,
            'size'          => $this->size,
            'human_size'    => $this->human_size,
            'is_image'      => $this->is_image,
            'url'           => $this->url,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
