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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     * Whether the step's fields include the builder settings schema.
     *
     * @since 1.6.0
     */
    protected bool $includeSettingsSchema = true;

    /**
     * Whether its fields include the value they start with in the renderer.
     *
     * @since 1.6.0
     */
    protected bool $includeResolvedDefaults = false;

    /**
     * Leaves the settings schema out of the step's fields, for payloads that only render the form.
     *
     * @since 1.6.0
     *
     * @return static The resource.
     */
    public function withoutSettingsSchema(): static
    {
        $this->includeSettingsSchema = false;

        return $this;
    }

    /**
     * Adds each field's `resolved_default_value` (see FormFieldResource::withResolvedDefault()).
     *
     * @since 1.6.0
     *
     * @return static The resource.
     */
    public function withResolvedDefaults(): static
    {
        $this->includeResolvedDefaults = true;

        return $this;
    }

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
            'fields'           => $this->whenLoaded( 'fields', fn (): AnonymousResourceCollection => $this->fieldResources() ),
        ];
    }

    /**
     * Gets the step's field resources.
     *
     * @since 1.6.0
     *
     * @return AnonymousResourceCollection The field resources.
     */
    protected function fieldResources(): AnonymousResourceCollection
    {
        $fields = FormFieldResource::collection( $this->resource->fields );

        if ( ! $this->includeSettingsSchema ) {
            $fields->collection->each( fn ( FormFieldResource $field ): FormFieldResource => $field->withoutSettingsSchema() );
        }

        if ( $this->includeResolvedDefaults ) {
            $fields->collection->each( fn ( FormFieldResource $field ): FormFieldResource => $field->withResolvedDefault() );
        }

        return $fields;
    }
}
