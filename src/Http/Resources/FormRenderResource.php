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

use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
            'fields'                => $this->renderFields(),
            'steps'                 => $this->renderSteps(),
            'config'                => [
                'honeypot' => [
                    'enabled'    => config( 'artisanpack.forms.spam_protection.honeypot.enabled', true ),
                    'field_name' => config( 'artisanpack.forms.spam_protection.honeypot.field_name', 'website_url' ),
                ],
                'display' => config( 'artisanpack.forms.display', [] ),
            ],
        ];
    }

    /**
     * Gets the field resources without the builder-only settings schema.
     *
     * Administrative fields are left out, since they are not on the public form.
     *
     * @since 1.6.0
     *
     * @return AnonymousResourceCollection The field resources.
     */
    protected function renderFields(): AnonymousResourceCollection
    {
        $fields = FormFieldResource::collection( $this->publicFields( $this->resource->fields ) );

        $fields->collection->each( fn ( FormFieldResource $field ): FormFieldResource => $field->withoutSettingsSchema()->withResolvedDefault() );

        return $fields;
    }

    /**
     * Gets the step resources without the builder-only settings schema.
     *
     * @since 1.6.0
     *
     * @return AnonymousResourceCollection The step resources.
     */
    protected function renderSteps(): AnonymousResourceCollection
    {
        foreach ( $this->resource->steps as $step ) {
            $step->fields->each( fn ( FormField $field ): FormField => $field->withSettingsForm( $this->resource ) );
        }

        $steps = FormStepResource::collection( $this->resource->steps );

        $steps->collection->each( fn ( FormStepResource $step ): FormStepResource => $step->withoutSettingsSchema()->withResolvedDefaults()->withoutAdministrativeFields() );

        return $steps;
    }

    /**
     * Gets the fields the public form shows, each reading form-level settings from this form.
     *
     * @since 1.6.0
     *
     * @param  Collection<int, FormField>  $fields  The fields.
     *
     * @return Collection<int, FormField> The fields without administrative ones.
     */
    protected function publicFields( Collection $fields ): Collection
    {
        return $fields
            ->reject( fn ( FormField $field ): bool => $field->isAdministrative() )
            ->each( fn ( FormField $field ): FormField => $field->withSettingsForm( $this->resource ) )
            ->values();
    }
}
