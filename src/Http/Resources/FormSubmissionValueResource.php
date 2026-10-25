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

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
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
     * A value whose field is masked in the admin is masked (see
     * FieldPrivacy::mask()) unless the user may reveal it.
     *
     * @since 1.1.0
     *
     * @param Request $request The incoming request.
     *
     * @return array<string, mixed> The transformed resource data.
     */
    public function toArray( Request $request ): array
    {
        $masked = $this->resource->isMasked() && ! FieldPrivacy::canReveal( $request->user() );

        return [
            'id'                => $this->id,
            'submission_id'     => $this->submission_id,
            'field_id'          => $this->field_id,
            'field_name'        => $this->field_name,
            'field_label'       => $this->field_label,
            'field_admin_label' => $this->field_admin_label,
            'field_type'        => $this->field_type,
            'value'             => $masked ? $this->resource->masked_display_value : $this->value,
            'value_array'       => $masked ? null : $this->safeValueArray(),
            'display_value'     => $masked ? $this->resource->masked_display_value : $this->display_value,
            'display_values'    => $masked ? [] : $this->display_values,
            'display_html'      => $masked ? null : $this->display_html,
            'is_masked'         => $masked,
            'is_redacted'       => $this->resource->isRedacted(),
            'upload_id'         => $this->upload_id,
            'upload_ids'        => $this->uploadIds(),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Gets the IDs of every upload a file value holds, in upload order.
     *
     * A multi-file value lists them in `value_array`; a single file is its
     * `upload_id`.
     *
     * @since 1.6.0
     *
     * @return list<int> The upload IDs; empty for other values.
     */
    protected function uploadIds(): array
    {
        if ( 'file' !== $this->field_type ) {
            return [];
        }

        if ( is_array( $this->value_array ) && [] !== $this->value_array && array_is_list( $this->value_array ) ) {
            return array_values( array_filter( array_map( 'intval', $this->value_array ) ) );
        }

        return null === $this->upload_id ? [] : [(int) $this->upload_id];
    }

    /**
     * Gets the stored value array, with a consent description sanitized.
     *
     * The React and Vue submission views show the consent description as
     * HTML. It was sanitized when stored; it is sanitized again here so a
     * value written some other way cannot inject markup.
     *
     * @since 1.6.0
     *
     * @return array<int|string, mixed>|null The value array.
     */
    protected function safeValueArray(): ?array
    {
        $values = $this->value_array;

        if ( 'consent' === $this->field_type && is_array( $values ) && is_string( $values['description'] ?? null ) ) {
            $values['description'] = FormField::sanitizeHtml( $values['description'] );
        }

        return $values;
    }
}
