<?php

/**
 * Form field API controller.
 *
 * Handles REST API requests for form field CRUD operations.
 * Delegates business logic to FieldService.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Controllers\Api;

use ArtisanPackUI\Forms\Http\Requests\Api\ReorderFieldsApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\StoreFieldApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\UpdateFieldApiRequest;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\FieldService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Form field API controller class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormFieldApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Creates a new controller instance.
     *
     * @since 1.1.0
     *
     * @param FieldService $fieldService The field service instance.
     */
    public function __construct(
        protected FieldService $fieldService,
    ) {
    }

    /**
     * Lists all fields for a form (ordered).
     *
     * @since 1.1.0
     *
     * @param Form $form The form to get fields from.
     *
     * @return AnonymousResourceCollection The fields collection.
     */
    public function index( Form $form ): AnonymousResourceCollection
    {
        $this->authorize( 'view', $form );

        $fields = $form->fields()->orderBy( 'sort_order' )->get();

        return FormFieldResource::collection( $fields );
    }

    /**
     * Creates a new field.
     *
     * @since 1.1.0
     *
     * @param StoreFieldApiRequest $request The validated request.
     * @param Form                 $form    The form to add the field to.
     *
     * @return FormFieldResource The created field resource.
     */
    public function store( StoreFieldApiRequest $request, Form $form ): FormFieldResource
    {
        $validated = $request->validated();
        $type      = $validated['type'];
        $stepId    = $validated['step_id'] ?? null;

        unset( $validated['type'], $validated['step_id'] );

        $field = $this->fieldService->create( $form, $type, $stepId, $validated );

        return new FormFieldResource( $field );
    }

    /**
     * Updates a field.
     *
     * @since 1.1.0
     *
     * @param UpdateFieldApiRequest $request The validated request.
     * @param Form                  $form    The parent form.
     * @param FormField             $field   The field to update.
     *
     * @return FormFieldResource The updated field resource.
     */
    public function update( UpdateFieldApiRequest $request, Form $form, FormField $field ): FormFieldResource
    {
        if ( $field->form_id !== $form->id ) {
            abort( 404 );
        }

        $field = $this->fieldService->update( $field, $request->validated() );

        return new FormFieldResource( $field );
    }

    /**
     * Deletes a field.
     *
     * @since 1.1.0
     *
     * @param Form      $form  The parent form.
     * @param FormField $field The field to delete.
     *
     * @return JsonResponse Empty response on success.
     */
    public function destroy( Form $form, FormField $field ): JsonResponse
    {
        $this->authorize( 'update', $form );

        if ( $field->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->fieldService->delete( $field );

        return response()->json( null, 204 );
    }

    /**
     * Reorders fields.
     *
     * @since 1.1.0
     *
     * @param ReorderFieldsApiRequest $request The validated request.
     * @param Form                    $form    The form containing the fields.
     *
     * @return JsonResponse Success response.
     */
    public function reorder( ReorderFieldsApiRequest $request, Form $form ): JsonResponse
    {
        $this->fieldService->reorder(
            $form,
            $request->validated( 'ordered_uuids' ),
            $request->validated( 'step_id' ),
        );

        return response()->json( ['message' => __( 'Fields reordered successfully.' )] );
    }
}
