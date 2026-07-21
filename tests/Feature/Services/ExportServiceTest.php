<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach( function (): void {
    $this->service = app( ExportService::class );
} );

describe( 'ExportService', function (): void {
    describe( 'buildHeaders', function (): void {
        it( 'includes standard headers', function (): void {
            $form = Form::factory()->create();

            $headers = $this->service->buildHeaders( $form );

            expect( $headers )->toContain( 'Submission ID' )
                ->and( $headers )->toContain( 'Submission Number' )
                ->and( $headers )->toContain( 'Submitted At' )
                ->and( $headers )->toContain( 'Page URL' )
                ->and( $headers )->toContain( 'Is Read' )
                ->and( $headers )->toContain( 'Is Spam' )
                ->and( $headers )->toContain( 'Is Starred' );

            // IP Address should NOT be included by default (privacy)
            expect( $headers )->not->toContain( 'IP Address' );
        } );

        it( 'includes ip address header when privacy setting is enabled', function (): void {
            config( ['artisanpack.forms.privacy.include_ip_address' => true] );

            $form    = Form::factory()->create();
            $headers = $this->service->buildHeaders( $form );

            expect( $headers )->toContain( 'IP Address' );
        } );

        it( 'includes field labels as headers', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['label' => 'Full Name', 'type' => 'text', 'sort_order' => 1] );
            FormField::factory()->for( $form )->create( ['label' => 'Email Address', 'type' => 'email', 'sort_order' => 2] );

            $headers = $this->service->buildHeaders( $form );

            expect( $headers )->toContain( 'Full Name' )
                ->and( $headers )->toContain( 'Email Address' );
        } );

        it( 'excludes layout fields from headers', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['label' => 'Name', 'type' => 'text'] );
            FormField::factory()->for( $form )->create( ['label' => 'Section Header', 'type' => 'heading'] );

            $headers = $this->service->buildHeaders( $form );

            expect( $headers )->toContain( 'Name' )
                ->and( $headers )->not->toContain( 'Section Header' );
        } );

        it( 'applies ap.forms.exportHeaders filter', function (): void {
            if ( ! function_exists( 'addFilter' ) ) {
                $this->markTestSkipped( 'Hook system not available' );
            }

            addFilter( 'ap.forms.exportHeaders', function ( array $headers, Form $form ): array {
                $headers[] = 'Custom Integration Column';

                return $headers;
            } );

            $form    = Form::factory()->create();
            $headers = $this->service->buildHeaders( $form );

            expect( $headers )->toContain( 'Custom Integration Column' );
        } );
    } );

    describe( 'buildRow', function (): void {
        it( 'builds row with submission data', function (): void {
            $form       = Form::factory()->create();
            $field      = FormField::factory()->for( $form )->create( ['name' => 'email', 'label' => 'Email', 'type' => 'email'] );
            $submission = FormSubmission::factory()->for( $form )->create( [
                'page_url'   => 'https://example.com',
                'ip_address' => '192.168.1.1',
                'is_read'    => true,
                'is_spam'    => false,
                'is_starred' => true,
            ] );
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'email',
                'value'      => 'test@example.com',
            ] );

            $fields = $form->fields()->orderBy( 'sort_order' )->get();
            $row    = $this->service->buildRow( $form, $submission, $fields );

            expect( $row )->toContain( (string) $submission->id )
                ->and( $row )->toContain( $submission->submission_number )
                ->and( $row )->toContain( 'test@example.com' )
                ->and( $row )->toContain( 'https://example.com' )
                ->and( $row )->toContain( '1' ) // is_read (default: stable 1/0 values)
                ->and( $row )->toContain( '0' );  // is_spam (default: stable 1/0 values)

            // IP address should NOT be included by default (privacy)
            expect( $row )->not->toContain( '192.168.1.1' );
        } );

        it( 'includes ip address when privacy setting is enabled', function (): void {
            config( ['artisanpack.forms.privacy.include_ip_address' => true] );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'ip_address' => '192.168.1.1',
            ] );

            $fields = $form->fields()->orderBy( 'sort_order' )->get();
            $row    = $this->service->buildRow( $form, $submission, $fields );

            expect( $row )->toContain( '192.168.1.1' );
        } );

        it( 'applies ap.forms.exportData filter', function (): void {
            if ( ! function_exists( 'addFilter' ) ) {
                $this->markTestSkipped( 'Hook system not available' );
            }

            addFilter( 'ap.forms.exportData', function ( array $row, FormSubmission $submission ): array {
                $row[] = 'Added by integration';

                return $row;
            } );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();
            $fields     = $form->fields()->orderBy( 'sort_order' )->get();

            $row = $this->service->buildRow( $form, $submission, $fields );

            expect( $row )->toContain( 'Added by integration' );
        } );
    } );

    describe( 'buildRows', function (): void {
        it( 'builds rows for multiple submissions', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->for( $form )->count( 3 )->create();

            $submissions = $form->submissions()->get();
            $rows        = $this->service->buildRows( $form, $submissions );

            expect( $rows )->toHaveCount( 3 );
        } );
    } );

    describe( 'exportToCsv', function (): void {
        it( 'returns a streamed response', function (): void {
            $form        = Form::factory()->create();
            $submissions = $form->submissions()->get();

            $response = $this->service->exportToCsv( $form, $submissions );

            expect( $response )->toBeInstanceOf( StreamedResponse::class )
                ->and( $response->headers->get( 'Content-Type' ) )->toBe( 'text/csv' );
        } );

        it( 'uses custom filename when provided', function (): void {
            $form        = Form::factory()->create();
            $submissions = $form->submissions()->get();

            $response = $this->service->exportToCsv( $form, $submissions, 'custom-export.csv' );

            expect( $response->headers->get( 'Content-Disposition' ) )->toContain( 'custom-export.csv' );
        } );
    } );

    describe( 'exportToJson', function (): void {
        it( 'returns array of submission data', function (): void {
            $form       = Form::factory()->create();
            $field      = FormField::factory()->for( $form )->create( ['name' => 'name', 'type' => 'text'] );
            $submission = FormSubmission::factory()->for( $form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'name',
                'value'      => 'Test User',
            ] );

            $submissions = $form->submissions()->get();
            $data        = $this->service->exportToJson( $form, $submissions );

            expect( $data )->toHaveCount( 1 )
                ->and( $data[0]['id'] )->toBe( $submission->id )
                ->and( $data[0]['submission_number'] )->toBe( $submission->submission_number )
                ->and( $data[0] )->toHaveKey( 'data' )
                ->and( $data[0] )->toHaveKey( 'metadata' );
        } );

        it( 'applies ap.forms.exportData filter to JSON export', function (): void {
            if ( ! function_exists( 'addFilter' ) ) {
                $this->markTestSkipped( 'Hook system not available' );
            }

            addFilter( 'ap.forms.exportData', function ( array $data, FormSubmission $submission ): array {
                $data['custom_json_field'] = 'custom_value';

                return $data;
            } );

            $form = Form::factory()->create();
            FormSubmission::factory()->for( $form )->create();

            $submissions = $form->submissions()->get();
            $data        = $this->service->exportToJson( $form, $submissions );

            expect( $data[0]['custom_json_field'] )->toBe( 'custom_value' );
        } );
    } );
} );

afterEach( function (): void {
    // Reset privacy config
    config( ['artisanpack.forms.privacy.include_ip_address' => false] );

    if ( function_exists( 'removeAllFilters' ) ) {
        removeAllFilters( 'ap.forms.exportHeaders' );
        removeAllFilters( 'ap.forms.exportData' );
    }
});
