<?php

/**
 * Submission controller.
 *
 * Handles the admin interface for viewing and managing form submissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Controllers;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Submission controller class.
 *
 * Handles the admin interface for viewing and managing form submissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class SubmissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Displays a listing of all submissions across all forms.
     *
     * @since 1.0.0
     *
     * @return View The all submissions index view.
     */
    public function indexAll(): View
    {
        $this->authorize( 'viewAny', FormSubmission::class );

        return view( 'forms::submissions.index-all' );
    }

    /**
     * Displays a listing of submissions for a specific form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to view submissions for.
     *
     * @return View The form submissions index view.
     */
    public function index( Form $form ): View
    {
        $this->authorize( 'viewSubmissions', $form );

        return view( 'forms::submissions.index', [
            'form' => $form,
        ] );
    }

    /**
     * Displays a specific submission.
     *
     * @since 1.0.0
     *
     * @param Form           $form       The parent form.
     * @param FormSubmission $submission The submission to display.
     *
     * @return View The submission detail view.
     */
    public function show( Form $form, FormSubmission $submission ): View
    {
        // Ensure the submission belongs to this form
        if ( $submission->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->authorize( 'view', $submission );

        return view( 'forms::submissions.show', [
            'form'       => $form,
            'submission' => $submission,
        ] );
    }

    /**
     * Exports submissions to CSV.
     *
     * @since 1.0.0
     *
     * @param Request       $request       The HTTP request.
     * @param Form          $form          The form to export submissions for.
     * @param ExportService $exportService The export service.
     *
     * @return StreamedResponse The CSV download response.
     */
    public function export( Request $request, Form $form, ExportService $exportService ): StreamedResponse
    {
        $this->authorize( 'exportSubmissions', $form );

        $submissions = FormSubmission::where( 'form_id', $form->id )
            ->with( 'values' )
            ->orderBy( 'created_at', 'desc' )
            ->get();

        return $exportService->exportToCsv( $form, $submissions );
    }

    /**
     * Downloads a file upload.
     *
     * @since 1.0.0
     *
     * @param Form           $form       The parent form.
     * @param FormSubmission $submission The parent submission.
     * @param FormUpload     $upload     The upload to download.
     *
     * @return StreamedResponse The file download response.
     */
    public function downloadUpload( Form $form, FormSubmission $submission, FormUpload $upload ): StreamedResponse
    {
        // Ensure the submission belongs to this form
        if ( $submission->form_id !== $form->id ) {
            Log::warning( 'Unauthorized file download attempt: submission does not belong to form', [
                'form_id'       => $form->id,
                'submission_id' => $submission->id,
                'upload_id'     => $upload->id,
                'ip'            => $this->getLoggableIp(),
            ] );
            abort( 404 );
        }

        // Ensure the upload belongs to this submission
        if ( $upload->submission_id !== $submission->id ) {
            Log::warning( 'Unauthorized file download attempt: upload does not belong to submission', [
                'form_id'       => $form->id,
                'submission_id' => $submission->id,
                'upload_id'     => $upload->id,
                'ip'            => $this->getLoggableIp(),
            ] );
            abort( 404 );
        }

        $this->authorize( 'view', $submission );

        if ( ! Storage::disk( $upload->disk )->exists( $upload->path ) ) {
            abort( 404, __( 'File not found.' ) );
        }

        return Storage::disk( $upload->disk )->download( $upload->path, $upload->original_name );
    }

    /**
     * Gets the IP address for logging, respecting privacy settings.
     *
     * If IP anonymization is enabled, the last octet of IPv4 addresses
     * will be masked (e.g., 192.168.1.123 becomes 192.168.1.0).
     * Uses inet_pton/inet_ntop for proper handling of compressed IPv6 addresses.
     *
     * @since 1.0.0
     *
     * @return string|null The loggable IP address or null.
     */
    protected function getLoggableIp(): ?string
    {
        $ipAddress = request()->ip();

        if ( null === $ipAddress ) {
            return null;
        }

        // Check if anonymization is enabled
        if ( ! config( 'artisanpack.forms.privacy.submission.anonymize_ip', false ) ) {
            return $ipAddress;
        }

        // Anonymize IPv4
        if ( filter_var( $ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return preg_replace( '/\.\d+$/', '.0', $ipAddress ) ?? $ipAddress;
        }

        // Anonymize IPv6 - mask the last 80 bits (10 bytes)
        if ( filter_var( $ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // Convert to binary representation (16 bytes)
            $binary = @inet_pton( $ipAddress );

            if ( false === $binary ) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            // Zero out the last 10 bytes (80 bits) - keep first 6 bytes (48 bits / 3 groups)
            $anonymized = substr( $binary, 0, 6 ) . str_repeat( "\x00", 10 );

            // Convert back to string representation
            $result = @inet_ntop( $anonymized );

            if ( false === $result ) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            return $result;
        }

        return $ipAddress;
    }
}
