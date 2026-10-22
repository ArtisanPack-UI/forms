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

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
            'is_read'               => $this->is_read,
            'is_spam'               => $this->is_spam,
            'is_starred'            => $this->is_starred,
            'admin_notes'           => $this->admin_notes,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
            'values'                => FormSubmissionValueResource::collection( $this->whenLoaded( 'values' ) ),
            'uploads'               => FormUploadResource::collection( $this->whenLoaded( 'uploads' ) ),
            'administrative_fields' => $this->when(
                $this->resource->relationLoaded( 'form' ) && true === $this->resource->form?->relationLoaded( 'fields' ),
                fn (): AnonymousResourceCollection => $this->administrativeFields(),
            ),
        ];
    }

    /**
     * Gets the form's administrative fields, which admins fill in on the submission.
     *
     * Included when the submission is loaded with `form.fields`. Fields masked
     * in the admin are left out unless the user may reveal them.
     *
     * @since 1.6.0
     *
     * @return AnonymousResourceCollection The field resources, without their settings schema.
     */
    protected function administrativeFields(): AnonymousResourceCollection
    {
        $form      = $this->resource->form;
        $canReveal = FieldPrivacy::canReveal( request()->user() );
        $fields    = $form->fields
            ->filter( fn ( FormField $field ): bool => $field->isAdministrative() && ( $canReveal || ! $field->isMaskedInAdmin() ) )
            ->each( fn ( FormField $field ): FormField => $field->withSettingsForm( $form ) )
            ->values();

        $resources = FormFieldResource::collection( $fields );

        $resources->collection->each( fn ( FormFieldResource $field ): FormFieldResource => $field->withoutSettingsSchema() );

        return $resources;
    }
}
