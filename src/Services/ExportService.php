<?php

/**
 * Export service.
 *
 * Business logic layer for exporting form submissions to various formats.
 * Supports CSV export with configurable headers and data transformations.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export service class.
 *
 * Business logic layer for exporting form submissions to various formats.
 * Supports CSV export with configurable headers and data transformations.
 *
 *
 * @since      1.0.0
 */
class ExportService
{
    /**
     * Exports submissions to CSV format.
     *
     * Applies the 'ap.forms.exportHeaders' and 'ap.forms.exportData' filter hooks
     * to allow third-party packages to modify export data.
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form to export from.
     * @param  Collection<int, FormSubmission>  $submissions  The submissions to export.
     * @param  string|null  $filename  Optional custom filename.
     *
     * @return StreamedResponse The CSV download response.
     */
    public function exportToCsv( Form $form, Collection $submissions, ?string $filename = null ): StreamedResponse
    {
        $filename = $filename ?? $form->slug . '-submissions-' . now()->format( 'Y-m-d' ) . '.csv';

        $headers = $this->buildHeaders( $form );
        $rows    = $this->buildRows( $form, $submissions );

        return Response::streamDownload( function () use ( $headers, $rows ): void {
            $handle = fopen( 'php://output', 'w' );

            if ( false === $handle ) {
                throw new RuntimeException( __( 'Failed to open output stream for CSV export.' ) );
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
     * Builds CSV headers from form fields.
     *
     * Applies the 'ap.forms.exportHeaders' filter hook to allow
     * third-party packages to modify export headers.
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form to build headers for.
     *
     * @return array<int, string> The CSV headers.
     */
    public function buildHeaders( Form $form ): array
    {
        $localizeHeaders = config( 'artisanpack.forms.export.localize_headers', false );

        $headers = [
            $localizeHeaders ? __( 'Submission ID' ) : 'Submission ID',
            $localizeHeaders ? __( 'Submission Number' ) : 'Submission Number',
            $localizeHeaders ? __( 'Submitted At' ) : 'Submitted At',
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
        $headers[] = $localizeHeaders ? __( 'Page URL' ) : 'Page URL';

        // Only include IP Address header if explicitly enabled in config
        if ( config( 'artisanpack.forms.privacy.include_ip_address', false ) ) {
            $headers[] = $localizeHeaders ? __( 'IP Address' ) : 'IP Address';
        }

        $headers[] = $localizeHeaders ? __( 'Is Read' ) : 'Is Read';
        $headers[] = $localizeHeaders ? __( 'Is Spam' ) : 'Is Spam';
        $headers[] = $localizeHeaders ? __( 'Is Starred' ) : 'Is Starred';

        // Apply filter hook for extensibility
        $headers = applyFilters( 'ap.forms.exportHeaders', $headers, $form );

        return $headers;
    }

    /**
     * Builds CSV rows from submissions.
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form.
     * @param  Collection<int, FormSubmission>  $submissions  The submissions.
     *
     * @return array<int, array<int, string>> The CSV rows.
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
     * Builds a single CSV row from a submission.
     *
     * Applies the 'ap.forms.exportData' filter hook to allow
     * third-party packages to modify export row data.
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form.
     * @param  FormSubmission  $submission  The submission.
     * @param  Collection<int, FormField>  $fields  The form fields.
     *
     * @return array<int, string> The CSV row data.
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

        // Use stable boolean values (1/0) by default for machine parsing
        // Use localized Yes/No only when explicitly enabled
        $localizeBooleans = config( 'artisanpack.forms.export.localize_booleans', false );

        if ( $localizeBooleans ) {
            $row[] = $submission->is_read ? __( 'Yes' ) : __( 'No' );
            $row[] = $submission->is_spam ? __( 'Yes' ) : __( 'No' );
            $row[] = $submission->is_starred ? __( 'Yes' ) : __( 'No' );
        } else {
            $row[] = $submission->is_read ? '1' : '0';
            $row[] = $submission->is_spam ? '1' : '0';
            $row[] = $submission->is_starred ? '1' : '0';
        }

        // Apply filter hook for extensibility
        $row = applyFilters( 'ap.forms.exportData', $row, $submission );

        return $row;
    }

    /**
     * Exports submissions to JSON format.
     *
     * @since 1.0.0
     *
     * @param  Form  $form  The form to export from.
     * @param  Collection<int, FormSubmission>  $submissions  The submissions to export.
     *
     * @return array<int, array<string, mixed>> The JSON export data.
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
            $submissionData = applyFilters( 'ap.forms.exportData', $submissionData, $submission );

            $data[] = $submissionData;
        }

        return $data;
    }
}
