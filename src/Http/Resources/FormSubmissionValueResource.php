<?php

/**
 * Form submission value API resource.
 *
 * Transforms FormSubmissionValue model instances into JSON API responses.
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
 * Form submission value API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormSubmissionValueResource extends JsonResource
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
            'field_name'    => $this->field_name,
            'field_label'   => $this->field_label,
            'field_type'    => $this->field_type,
            'value'         => $this->value,
            'value_array'   => $this->value_array,
            'display_value' => $this->display_value,
            'upload_id'     => $this->upload_id,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
