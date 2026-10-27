<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Livewire\SubmissionsList;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards SEC-02 and BE-9: a masked or redacted field's settings are copied
 * onto its stored values and uploads, so deleting or renaming the field does
 * not reveal its values or keep them past their redaction period.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create();
    $this->ssn  = FormField::factory()->for( $this->form )->create( [
        'type'         => 'text',
        'name'         => 'ssn',
        'label'        => 'SSN',
        'field_config' => ['mask_in_admin' => true, 'redact_after_days' => 1],
    ] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'sort_order' => 2] );

    $this->submission = app( SubmissionService::class )->create( $this->form, ['ssn' => '123456789', 'name' => 'Ada'] );
    $this->staff      = User::factory()->create( ['is_admin' => false] );
    $this->admin      = User::factory()->create( ['is_admin' => true] );
} );

it( 'copies the privacy settings onto the stored value', function (): void {
    $value = $this->submission->values()->where( 'field_name', 'ssn' )->first();

    expect( $value->is_masked )->toBeTrue()
        ->and( $value->redact_after_days )->toBe( 1 );
} );

it( 'keeps a deleted field\'s values masked', function (): void {
    $this->ssn->delete();

    $value = $this->submission->values()->where( 'field_name', 'ssn' )->first();

    expect( $value->field_id )->toBeNull()
        ->and( $value->isMasked() )->toBeTrue();

    $this->actingAs( $this->staff );

    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission->fresh()] )
        ->assertSee( '••••6789' )
        ->assertDontSee( '123456789' );

    $response = $this->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) );

    expect( $response->getContent() )->not->toContain( '123456789' );
} );

it( 'does not find submissions by a deleted masked field\'s value', function (): void {
    $this->ssn->delete();

    $this->actingAs( $this->staff );

    Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
        ->set( 'search', '123456789' )
        ->assertDontSee( $this->submission->submission_number );

    Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
        ->set( 'search', 'Ada' )
        ->assertSee( $this->submission->submission_number );

    $this->actingAs( $this->admin );

    Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
        ->set( 'search', '123456789' )
        ->assertSee( $this->submission->submission_number );
} );

it( 'leaves a deleted or renamed masked field out of the JSON export', function ( Closure $change ): void {
    $change->call( $this );

    $exporter    = app( ExportService::class );
    $submissions = FormSubmission::with( 'values.field' )->get();

    $this->actingAs( $this->staff );

    $data = $exporter->exportToJson( $this->form->fresh(), $submissions )[0]['data'];

    expect( $data )->not->toHaveKey( 'ssn' )
        ->and( $data )->not->toHaveKey( 'tax_id' )
        ->and( $data )->toHaveKey( 'name' );

    $this->actingAs( $this->admin );

    expect( $exporter->exportToJson( $this->form->fresh(), $submissions )[0]['data'] )->toHaveKey( 'ssn' );
} )->with( [
    'deleted' => [fn () => $this->ssn->delete()],
    'renamed' => [fn () => $this->ssn->update( ['name' => 'tax_id'] )],
] );

it( 'still redacts a deleted field\'s values after its period', function (): void {
    $this->submission->forceFill( ['created_at' => now()->subDays( 2 )] )->save();
    $recent = app( SubmissionService::class )->create( $this->form, ['ssn' => '987654321', 'name' => 'Grace'] );

    $this->ssn->delete();

    $this->artisan( 'forms:prune-submissions', ['--dry-run' => true] )
        ->expectsOutputToContain( '[Dry Run] Would redact 1 field value(s)' )
        ->assertSuccessful();

    $this->artisan( 'forms:prune-submissions' )
        ->expectsOutputToContain( 'Redacted 1 field value(s)' )
        ->assertSuccessful();

    $old = $this->submission->values()->where( 'field_name', 'ssn' )->first();

    expect( $old->value )->toBeNull()
        ->and( $old->redacted_at )->not->toBeNull()
        ->and( $recent->values()->where( 'field_name', 'ssn' )->value( 'value' ) )->toBe( '987654321' );
} );

it( 'keeps a deleted field\'s masked files masked', function (): void {
    Storage::fake( 'local' );

    $files = FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'id_scan',
        'label'        => 'ID scan',
        'field_config' => ['mask_in_admin' => true],
    ] );

    $submission = app( SubmissionService::class )->create( $this->form, ['name' => 'Ada'], ['id_scan' => UploadedFile::fake()->create( 'scan.pdf', 10, 'application/pdf' )] );
    $upload     = FormUpload::query()->where( 'submission_id', $submission->id )->firstOrFail();

    expect( $upload->is_masked )->toBeTrue();

    $files->delete();
    $upload = $upload->fresh();

    expect( $upload->field_id )->toBeNull()
        ->and( FieldPrivacy::masksUpload( $upload, $this->staff ) )->toBeTrue()
        ->and( FieldPrivacy::masksUpload( $upload, $this->admin ) )->toBeFalse();
} );
