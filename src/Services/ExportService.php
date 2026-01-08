<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ExportService
 *
 * Business logic layer for exporting form submissions to various formats.
 * Supports CSV export with configurable headers and data transformations.
 *
 * @since 1.0.0
 */
class ExportService
{
    /**
     * Export submissions to CSV format.
     *
     * Applies the 'forms.export_headers' and 'forms.export_data' filter hooks
     * to allow third-party packages to modify export data.
     *
     * @param  Collection<int, FormSubmission>  $submissions
     */
    public function exportToCsv( Form $form, Collection $submissions, ?string $filename = null ): StreamedResponse
    {
        $filename = $filename ?? $form->slug . '-submissions-' . now()->format( 'Y-m-d' ) . '.csv';

        $headers = $this->buildHeaders( $form );
        $rows    = $this->buildRows( $form, $submissions );

        return Response::streamDownload( function () use ( $headers, $rows ): void {
            $handle = fopen( 'php://output', 'w' );

            if ( false === $handle ) {
                throw new RuntimeException( 'Failed to open output stream for CSV export.' );
            }

            // Write headers
            fputcsv( $handle, $headers );

            // Write rows
            foreach ( $rows as $row ) {
                fputcsv( $handle, $row );
            }

            fclose( $handle );
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ] );
    }

    /**
     * Build CSV headers from form fields.
     *
     * Applies the 'forms.export_headers' filter hook to allow
     * third-party packages to modify export headers.
     *
     * @return array<int, string>
     */
    public function buildHeaders( Form $form ): array
    {
        $headers = [
            'Submission ID',
            'Submission Number',
            'Submitted At',
        ];

        // Add field headers
        foreach ( $form->fields()->orderBy( 'sort_order' )->get() as $field ) {
            // Skip layout fields
            if ( $field->isLayoutField() ) {
                continue;
            }

            $headers[] = $field->label;
        }

        // Add metadata headers
        $headers[] = 'Page URL';

        // Only include IP Address header if explicitly enabled in config
        if ( config( 'artisanpack.forms.privacy.include_ip_address', false ) ) {
            $headers[] = 'IP Address';
        }

        $headers[] = 'Is Read';
        $headers[] = 'Is Spam';
        $headers[] = 'Is Starred';

        // Apply filter hook for extensibility
        if ( function_exists( 'applyFilters' ) ) {
            $headers = applyFilters( 'forms.export_headers', $headers, $form );
        }

        return $headers;
    }

    /**
     * Build CSV rows from submissions.
     *
     * @param  Collection<int, FormSubmission>  $submissions
     *
     * @return array<int, array<int, string>>
     */
    public function buildRows( Form $form, Collection $submissions ): array
    {
        $fields = $form->fields()->orderBy( 'sort_order' )->get();
        $rows   = [];

        foreach ( $submissions as $submission ) {
            $row    = $this->buildRow( $form, $submission, $fields );
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Build a single CSV row from a submission.
     *
     * Applies the 'forms.export_data' filter hook to allow
     * third-party packages to modify export row data.
     *
     * @param  Collection<int, \ArtisanPackUI\Forms\Models\FormField>  $fields
     *
     * @return array<int, string>
     */
    public function buildRow( Form $form, FormSubmission $submission, $fields ): array
    {
        $row = [
            (string) $submission->id,
            $submission->submission_number,
            $submission->created_at->format( 'Y-m-d H:i:s' ),
        ];

        // Add field values
        foreach ( $fields as $field ) {
            // Skip layout fields
            if ( $field->isLayoutField() ) {
                continue;
            }

            $value = $submission->getValue( $field->name );
            $row[] = $value ?? '';
        }

        // Add metadata
        $row[] = $submission->page_url ?? '';

        // Only include IP address if explicitly enabled in config
        if ( config( 'artisanpack.forms.privacy.include_ip_address', false ) ) {
            $row[] = $submission->ip_address ?? '';
        }

        $row[] = $submission->is_read ? 'Yes' : 'No';
        $row[] = $submission->is_spam ? 'Yes' : 'No';
        $row[] = $submission->is_starred ? 'Yes' : 'No';

        // Apply filter hook for extensibility
        if ( function_exists( 'applyFilters' ) ) {
            $row = applyFilters( 'forms.export_data', $row, $submission );
        }

        return $row;
    }

    /**
     * Export submissions to JSON format.
     *
     * @param  Collection<int, FormSubmission>  $submissions
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportToJson( Form $form, Collection $submissions ): array
    {
        $data = [];

        foreach ( $submissions as $submission ) {
            // Build metadata, respecting privacy settings
            $metadata = [
                'page_url'   => $submission->page_url,
                'is_read'    => $submission->is_read,
                'is_spam'    => $submission->is_spam,
                'is_starred' => $submission->is_starred,
            ];

            // Only include IP address if explicitly enabled in config
            if ( config( 'artisanpack.forms.privacy.include_ip_address', false ) ) {
                $metadata['ip_address'] = $submission->ip_address;
            }

            $submissionData = [
                'id'                => $submission->id,
                'submission_number' => $submission->submission_number,
                'submitted_at'      => $submission->created_at->toIso8601String(),
                'data'              => $submission->data_array,
                'metadata'          => $metadata,
            ];

            // Apply filter hook for extensibility
            if ( function_exists( 'applyFilters' ) ) {
                $submissionData = applyFilters( 'forms.export_data', $submissionData, $submission);
            }

            $data[] = $submissionData;
        }

        return $data;
    }
}
