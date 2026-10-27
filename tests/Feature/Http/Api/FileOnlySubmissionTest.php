<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guards VU-04: a multipart submission with no `data` key (a form whose only
 * inputs are files) is read as empty data rather than rejected.
 */
beforeEach( function (): void {
    Storage::fake( 'local' );
    Storage::fake( 'form-uploads' );

    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'docs',
        'label'        => 'Documents',
        'field_config' => ['allowed_types' => ['pdf'], 'max_size' => 200],
    ] );
} );

it( 'stores a submission sent with files only', function (): void {
    $this->post( route( 'api.forms.submit', $this->form ), [
        'files'           => ['docs' => UploadedFile::fake()->create( 'cv.pdf', 20, 'application/pdf' )],
        '_form_loaded_at' => time() - 10,
    ], ['Accept' => 'application/json'] )->assertCreated();

    expect( FormSubmission::query()->count() )->toBe( 1 )
        ->and( FormSubmission::query()->sole()->uploads()->count() )->toBe( 1 );
} );

it( 'still rejects data that is not an array', function (): void {
    $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => 'nope',
        '_form_loaded_at' => time() - 10,
    ] )->assertUnprocessable()->assertJsonValidationErrors( 'data' );
} );
