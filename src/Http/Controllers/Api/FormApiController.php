<?php

/**
 * Form API controller.
 *
 * Handles REST API requests for form CRUD operations.
 * Delegates business logic to FormService.
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

use ArtisanPackUI\Forms\Http\Requests\Api\StoreFormApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\UpdateFormApiRequest;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Http\Resources\FormResource;
use ArtisanPackUI\Forms\Http\Resources\PaginatedResourceCollection;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\FormService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Form API controller class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Creates a new controller instance.
     *
     * @since 1.1.0
     *
     * @param FormService $formService The form service instance.
     */
    public function __construct(
        protected FormService $formService,
    ) {
    }

    /**
     * Lists all forms with pagination.
     *
     * @since 1.1.0
     *
     * @param Request $request The incoming request.
     *
     * @return PaginatedResourceCollection The paginated form collection.
     */
    public function index( Request $request ): PaginatedResourceCollection
    {
        $this->authorize( 'viewAny', Form::class );

        $query = Form::query();

        $status = $request->input( 'status' );

        if ( 'active' === $status ) {
            $query->where( 'is_active', true );
        } elseif ( 'inactive' === $status ) {
            $query->where( 'is_active', false );
        }

        $perPage = config( 'artisanpack.forms.api.per_page', 15 );

        return new PaginatedResourceCollection(
            $query->orderBy( 'created_at', 'desc' )->paginate( $perPage ),
            FormResource::class,
        );
    }

    /**
     * Creates a new form.
     *
     * @since 1.1.0
     *
     * @param StoreFormApiRequest $request The validated request.
     *
     * @return JsonResponse The created form resource with 201 status.
     */
    public function store( StoreFormApiRequest $request ): JsonResponse
    {
        $form = $this->formService->create( $request->validated() );

        return ( new FormResource( $form ) )
            ->response()
            ->setStatusCode( 201 );
    }

    /**
     * Gets a single form with its relationships.
     *
     * @since 1.1.0
     *
     * @param Form $form The form to show.
     *
     * @return FormResource The form resource.
     */
    public function show( Form $form ): FormResource
    {
        $this->authorize( 'view', $form );

        $form->load( ['fields', 'steps.fields'] );
        $form->loadCount( [
            'submissions as total_submissions_count',
            'submissions as unread_submissions_count' => fn ( $q ) => $q->where( 'is_read', false ),
        ] );

        return new FormResource( $form );
    }

    /**
     * Updates a form.
     *
     * @since 1.1.0
     *
     * @param UpdateFormApiRequest $request The validated request.
     * @param Form                 $form    The form to update.
     *
     * @return FormResource The updated form resource.
     */
    public function update( UpdateFormApiRequest $request, Form $form ): FormResource
    {
        $updatedForm = $this->formService->update( $form, $request->validated() );

        if ( null === $updatedForm ) {
            abort( 500, __( 'Form could not be refreshed after update.' ) );
        }

        return new FormResource( $updatedForm );
    }

    /**
     * Deletes a form.
     *
     * @since 1.1.0
     *
     * @param Form $form The form to delete.
     *
     * @return JsonResponse Empty response on success.
     */
    public function destroy( Form $form ): JsonResponse
    {
        $this->authorize( 'delete', $form );

        if ( ! $this->formService->delete( $form ) ) {
            return response()->json( [
                'message' => __( 'Failed to delete form.' ),
            ], 500 );
        }

        return response()->json( null, 204 );
    }

    /**
     * Gets form definition for client-side rendering.
     *
     * @since 1.1.0
     *
     * @param Form $form The form to render.
     *
     * @return FormRenderResource The form render resource.
     */
    public function render( Form $form ): FormRenderResource
    {
        if ( ! $form->is_active ) {
            abort( 404, __( 'Form not found.' ) );
        }

        $form->load( ['fields', 'steps.fields'] );

        return new FormRenderResource( $form );
    }
}
