<?php

/**
 * Form field API resource.
 *
 * Transforms FormField model instances into JSON API responses.
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

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Form field API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormFieldResource extends JsonResource
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
            'step_id'           => $this->step_id,
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'type'              => $this->type,
            'label'             => $this->label,
            'placeholder'       => $this->placeholder,
            'help_text'         => $this->help_text,
            'is_required'       => $this->is_required,
            'validation_rules'  => $this->validation_rules,
            'field_config'      => $this->sanitizedFieldConfig(),
            'default_value'     => 'html' === $this->type && null !== $this->default_value
                ? FormField::sanitizeHtml( $this->default_value )
                : $this->default_value,
            'conditional_logic' => $this->conditional_logic,
            'width'             => $this->width,
            'css_classes'       => $this->css_classes,
            'sort_order'        => $this->sort_order,
            'options'           => $this->when( FieldTypes::hasOptions( $this->type ) && ! empty( $this->options ), $this->options ),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Gets the field config with an html field's content sanitized.
     *
     * The React and Vue renderers inject html content as raw HTML, so it is
     * sanitized here exactly like the legacy `default_value` copy.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed>|null The field config.
     */
    protected function sanitizedFieldConfig(): ?array
    {
        $config = $this->field_config;

        if ( 'html' === $this->type && is_array( $config ) && is_string( $config['content'] ?? null ) ) {
            $config['content'] = FormField::sanitizeHtml( $config['content'] );
        }

        return $config;
    }
}
