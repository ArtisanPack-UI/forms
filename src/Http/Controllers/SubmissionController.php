<?php

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
 * SubmissionController
 *
 * Handles the admin interface for viewing and managing form submissions.
 *
 * @since 1.0.0
 */
class SubmissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of all submissions (across all forms).
     */
    public function indexAll(): View
    {
        $this->authorize( 'viewAny', FormSubmission::class );

        return view( 'forms::submissions.index-all' );
    }

    /**
     * Display a listing of submissions for a specific form.
     */
    public function index( Form $form ): View
    {
        $this->authorize( 'viewSubmissions', $form );

        return view( 'forms::submissions.index', [
            'form' => $form,
        ] );
    }

    /**
     * Display a specific submission.
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
     * Export submissions to CSV.
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
     * Download a file upload.
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
            abort( 404, 'File not found.' );
        }

        return Storage::disk( $upload->disk )->download( $upload->path, $upload->original_name );
    }

    /**
     * Get the IP address for logging, respecting privacy settings.
     *
     * If IP anonymization is enabled, the last octet of IPv4 addresses
     * will be masked (e.g., 192.168.1.123 becomes 192.168.1.0).
     * Uses inet_pton/inet_ntop for proper handling of compressed IPv6 addresses.
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
            $result = @inet_ntop( $anonymized);

            if ( false === $result) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            return $result;
        }

        return $ipAddress;
    }
}
