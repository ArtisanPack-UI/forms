<?php

/**
 * Form step API controller.
 *
 * Handles REST API requests for form step CRUD operations.
 * Delegates business logic to StepService.
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

use ArtisanPackUI\Forms\Http\Requests\Api\ReorderStepsApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\StoreStepApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\UpdateStepApiRequest;
use ArtisanPackUI\Forms\Http\Resources\FormStepResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\StepService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Form step API controller class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormStepApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Creates a new controller instance.
     *
     * @since 1.1.0
     *
     * @param StepService $stepService The step service instance.
     */
    public function __construct(
        protected StepService $stepService,
    ) {
    }

    /**
     * Lists all steps for a form with their fields.
     *
     * @since 1.1.0
     *
     * @param Form $form The form to get steps from.
     *
     * @return AnonymousResourceCollection The steps collection.
     */
    public function index( Form $form ): AnonymousResourceCollection
    {
        $this->authorize( 'view', $form );

        $steps = $this->stepService->getSteps( $form );

        return FormStepResource::collection( $steps );
    }

    /**
     * Creates a new step.
     *
     * @since 1.1.0
     *
     * @param StoreStepApiRequest $request The validated request.
     * @param Form                $form    The form to add the step to.
     *
     * @return FormStepResource The created step resource.
     */
    public function store( StoreStepApiRequest $request, Form $form ): FormStepResource
    {
        $step = $this->stepService->create( $form, $request->validated() );

        return new FormStepResource( $step );
    }

    /**
     * Updates a step.
     *
     * @since 1.1.0
     *
     * @param UpdateStepApiRequest $request The validated request.
     * @param Form                 $form    The parent form.
     * @param FormStep             $step    The step to update.
     *
     * @return FormStepResource The updated step resource.
     */
    public function update( UpdateStepApiRequest $request, Form $form, FormStep $step ): FormStepResource
    {
        if ( $step->form_id !== $form->id ) {
            abort( 404 );
        }

        $step = $this->stepService->update( $step, $request->validated() );

        return new FormStepResource( $step );
    }

    /**
     * Deletes a step.
     *
     * @since 1.1.0
     *
     * @param Form     $form The parent form.
     * @param FormStep $step The step to delete.
     *
     * @return JsonResponse Empty response on success.
     */
    public function destroy( Form $form, FormStep $step ): JsonResponse
    {
        $this->authorize( 'update', $form );

        if ( $step->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->stepService->delete( $step );

        return response()->json( null, 204 );
    }

    /**
     * Reorders steps.
     *
     * @since 1.1.0
     *
     * @param ReorderStepsApiRequest $request The validated request.
     * @param Form                   $form    The form containing the steps.
     *
     * @return JsonResponse Success response.
     */
    public function reorder( ReorderStepsApiRequest $request, Form $form ): JsonResponse
    {
        $this->stepService->reorder( $form, $request->validated( 'ordered_ids' ) );

        return response()->json( ['message' => __( 'Steps reordered successfully.' )] );
    }
}
