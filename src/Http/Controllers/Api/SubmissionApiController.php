<?php

/**
 * Submission API controller.
 *
 * Handles REST API requests for form submissions including listing,
 * viewing, updating metadata, bulk actions, export, and file downloads.
 * Also handles public form submission.
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

use ArtisanPackUI\Forms\Http\Requests\Api\BulkSubmissionApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\SubmitFormApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\UpdateSubmissionApiRequest;
use ArtisanPackUI\Forms\Http\Resources\FormSubmissionResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Submission API controller class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class SubmissionApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Creates a new controller instance.
     *
     * @since 1.1.0
     *
     * @param SubmissionService $submissionService The submission service instance.
     */
    public function __construct(
        protected SubmissionService $submissionService,
    ) {
    }

    /**
     * Lists submissions for a form with pagination and filtering.
     *
     * @since 1.1.0
     *
     * @param Request $request The incoming request.
     * @param Form    $form    The form to list submissions for.
     *
     * @return AnonymousResourceCollection The paginated submissions collection.
     */
    public function index( Request $request, Form $form ): AnonymousResourceCollection
    {
        $this->authorize( 'viewSubmissions', $form );

        $query = FormSubmission::where( 'form_id', $form->id )
            ->with( ['values', 'uploads'] );

        if ( $request->has( 'is_read' ) ) {
            $query->where( 'is_read', filter_var( $request->input( 'is_read' ), FILTER_VALIDATE_BOOLEAN ) );
        }

        if ( $request->has( 'is_spam' ) ) {
            $query->where( 'is_spam', filter_var( $request->input( 'is_spam' ), FILTER_VALIDATE_BOOLEAN ) );
        }

        if ( $request->has( 'is_starred' ) ) {
            $query->where( 'is_starred', filter_var( $request->input( 'is_starred' ), FILTER_VALIDATE_BOOLEAN ) );
        }

        $perPage = config( 'artisanpack.forms.api.per_page', 15 );

        return FormSubmissionResource::collection(
            $query->orderBy( 'created_at', 'desc' )->paginate( $perPage ),
        );
    }

    /**
     * Gets a single submission with its values.
     *
     * @since 1.1.0
     *
     * @param Form           $form       The parent form.
     * @param FormSubmission $submission The submission to show.
     *
     * @return FormSubmissionResource The submission resource.
     */
    public function show( Form $form, FormSubmission $submission ): FormSubmissionResource
    {
        if ( $submission->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->authorize( 'view', $submission );

        $submission->load( ['values', 'uploads'] );

        return new FormSubmissionResource( $submission );
    }

    /**
     * Updates submission metadata (read/starred/spam/notes).
     *
     * @since 1.1.0
     *
     * @param UpdateSubmissionApiRequest $request    The validated request.
     * @param Form                       $form       The parent form.
     * @param FormSubmission             $submission The submission to update.
     *
     * @return FormSubmissionResource The updated submission resource.
     */
    public function update( UpdateSubmissionApiRequest $request, Form $form, FormSubmission $submission ): FormSubmissionResource
    {
        if ( $submission->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->authorize( 'update', $submission );

        $submission->update( $request->validated() );

        $freshSubmission = $submission->fresh( ['values', 'uploads'] );

        if ( null === $freshSubmission ) {
            abort( 404, __( 'Submission not found after update.' ) );
        }

        return new FormSubmissionResource( $freshSubmission );
    }

    /**
     * Deletes a submission.
     *
     * @since 1.1.0
     *
     * @param Form           $form       The parent form.
     * @param FormSubmission $submission The submission to delete.
     *
     * @return JsonResponse Empty response on success.
     */
    public function destroy( Form $form, FormSubmission $submission ): JsonResponse
    {
        if ( $submission->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->authorize( 'delete', $submission );

        $submission->delete();

        return response()->json( null, 204 );
    }

    /**
     * Public form submission endpoint.
     *
     * Handles validation, spam checks, rate limiting, file uploads,
     * and notification triggering — same logic as the Livewire FormRenderer.
     *
     * @since 1.1.0
     *
     * @param SubmitFormApiRequest $request The validated request.
     * @param Form                 $form    The form being submitted.
     *
     * @return JsonResponse The submission result.
     */
    public function submit( SubmitFormApiRequest $request, Form $form ): JsonResponse
    {
        $honeypotField = config( 'artisanpack.forms.spam_protection.honeypot.field_name', 'website_url' );
        $honeypotValue = $request->input( $honeypotField );
        $formLoadedAt  = $request->input( '_form_loaded_at' );
        $ipAddress     = $request->ip() ?? 'unknown';

        // Check rate limiting
        if ( config( 'artisanpack.forms.spam_protection.rate_limit.enabled', true ) ) {
            if ( $this->submissionService->isRateLimited( $ipAddress, $form->id ) ) {
                return response()->json( [
                    'message' => __( 'Too many submissions. Please try again later.' ),
                ], 429 );
            }
        }

        // Record the attempt for rate limiting (before spam check so bots still count)
        $this->submissionService->recordAttempt( $ipAddress, $form->id );

        // Check for spam (honeypot only when enabled, time check always)
        $honeypotEnabled = config( 'artisanpack.forms.spam_protection.honeypot.enabled', true );
        $isSpam          = $this->submissionService->isSpam(
            $honeypotEnabled ? $honeypotValue : null,
            $formLoadedAt ? (int) $formLoadedAt : null,
            $form->id,
            $ipAddress,
        );

        if ( $isSpam ) {
            // Silent success for bots — mirrors real response shape
            $response = [
                'message'       => $form->success_message ?? __( 'Form submitted successfully.' ),
                'submission_id' => null,
            ];

            if ( $form->redirect_url ) {
                $response['redirect_url'] = $form->redirect_url;
            }

            return response()->json( $response, 201 );
        }

        try {
            $metadata = $this->submissionService->getRequestMetadata( $request );

            $submission = $this->submissionService->create(
                $form,
                $request->sanitizedData(),
                $request->sanitizedFiles(),
                $metadata,
            );

            $response = [
                'message'       => $form->success_message ?? __( 'Form submitted successfully.' ),
                'submission_id' => $submission->id,
            ];

            if ( $form->redirect_url ) {
                $response['redirect_url'] = $form->redirect_url;
            }

            return response()->json( $response, 201 );
        } catch ( Exception $e ) {
            Log::error( 'API form submission failed', [
                'form_id' => $form->id,
                'error'   => $e->getMessage(),
            ] );

            return response()->json( [
                'message' => __( 'An error occurred while submitting the form.' ),
            ], 500 );
        }
    }

    /**
     * Exports submissions as CSV.
     *
     * @since 1.1.0
     *
     * @param Form          $form          The form to export from.
     * @param ExportService $exportService The export service.
     *
     * @return StreamedResponse The CSV download response.
     */
    public function export( Form $form, ExportService $exportService ): StreamedResponse
    {
        $this->authorize( 'exportSubmissions', $form );

        $submissions = FormSubmission::where( 'form_id', $form->id )
            ->with( 'values' )
            ->orderBy( 'created_at', 'desc' )
            ->get();

        return $exportService->exportToCsv( $form, $submissions );
    }

    /**
     * Performs bulk actions on submissions.
     *
     * @since 1.1.0
     *
     * @param BulkSubmissionApiRequest $request The validated request.
     * @param Form                     $form    The parent form.
     *
     * @return JsonResponse The result of the bulk action.
     */
    public function bulk( BulkSubmissionApiRequest $request, Form $form ): JsonResponse
    {
        $this->authorize( 'viewSubmissions', $form );

        $action = $request->validated( 'action' );
        $ids    = $request->validated( 'ids' );

        $query = FormSubmission::where( 'form_id', $form->id )
            ->whereIn( 'id', $ids );

        $affected = DB::transaction( function () use ( $query, $action ): int {
            return match ( $action ) {
                'delete'        => $query->delete(),
                'mark_read'     => $query->update( ['is_read' => true] ),
                'mark_unread'   => $query->update( ['is_read' => false] ),
                'mark_spam'     => $query->update( ['is_spam' => true] ),
                'mark_not_spam' => $query->update( ['is_spam' => false] ),
                default         => 0,
            };
        } );

        return response()->json( [
            'message'  => __( ':count submissions updated.', ['count' => $affected] ),
            'affected' => $affected,
        ] );
    }

    /**
     * Downloads a file upload.
     *
     * @since 1.1.0
     *
     * @param FormSubmission $submission The parent submission.
     * @param FormUpload     $upload     The upload to download.
     *
     * @return StreamedResponse The file download response.
     */
    public function downloadUpload( FormSubmission $submission, FormUpload $upload ): StreamedResponse
    {
        if ( $upload->submission_id !== $submission->id ) {
            abort( 404 );
        }

        $this->authorize( 'view', $submission );

        if ( ! Storage::disk( $upload->disk )->exists( $upload->path ) ) {
            abort( 404, __( 'File not found.' ) );
        }

        return Storage::disk( $upload->disk )->download( $upload->path, $upload->original_name );
    }
}
