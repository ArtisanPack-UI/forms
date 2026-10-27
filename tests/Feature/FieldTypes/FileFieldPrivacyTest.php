<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Livewire\SubmissionsList;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #157: administrative visibility and the per-field privacy
 * settings on file upload fields.
 */
beforeEach( function (): void {
    Storage::fake( 'form-uploads' );

    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->staff = User::factory()->create( ['is_admin' => false] );
    $this->admin = User::factory()->create( ['is_admin' => true] );

    FormField::factory()->for( $this->form )->create( ['type' => 'email', 'name' => 'email', 'label' => 'Email'] );
} );

/**
 * Creates a file field on the test form.
 *
 * @param  array<string, mixed>  $config  Extra field_config.
 * @param  array<string, mixed>  $attributes  Extra attributes.
 */
function fileField( Form $form, array $config = [], array $attributes = [] ): FormField
{
    return FormField::factory()->for( $form )->create( array_merge( [
        'type'         => 'file',
        'name'         => 'document',
        'label'        => 'Document',
        'sort_order'   => 2,
        'field_config' => array_merge( ['max_size' => 200, 'allowed_types' => ['pdf', 'png']], $config ),
    ], $attributes ) );
}

describe( 'administrative file fields on the public form', function (): void {
    it( 'are left out of the public rules and ignore a file sent for them', function (): void {
        fileField( $this->form, ['visibility' => 'administrative'] );

        $rules = app( SubmissionService::class )->buildValidationRules( $this->form->fields()->get() );

        expect( $rules )->not->toHaveKey( 'files.document' );

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => UploadedFile::fake()->create( 'sneaky.pdf', 10 )] );

        expect( FormUpload::count() )->toBe( 0 );
    } );
} );

describe( 'administrative file fields in the Livewire submission detail', function (): void {
    beforeEach( function (): void {
        $this->field      = fileField( $this->form, ['visibility' => 'administrative'] );
        $this->submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'] );

        $this->actingAs( $this->staff );
    } );

    it( 'shows a labelled upload control', function (): void {
        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertSee( 'Administrative Fields' )
            ->assertSeeHtml( 'for="admin-file-document"' )
            ->assertSeeHtml( 'wire:model="adminFiles.document"' )
            ->assertSeeHtml( 'aria-live="polite"' );
    } );

    it( 'uploads a file', function (): void {
        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'contract.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors()
            ->assertSet( 'adminFiles', [] )
            ->assertSet( 'administrativeFieldsSaved', true )
            ->assertSee( 'contract.pdf' )
            ->assertSeeHtml( 'aria-label="Remove contract.pdf"' );

        $upload = FormUpload::sole();

        expect( $upload->field_id )->toBe( $this->field->id )
            ->and( $upload->isTransient() )->toBeFalse()
            ->and( FormSubmissionValue::where( 'field_id', $this->field->id )->sole()->upload_id )->toBe( $upload->id );

        Storage::disk( 'form-uploads' )->assertExists( $upload->path );
    } );

    it( 'replaces the stored file and deletes the old one', function (): void {
        $detail = Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'first.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' );

        $first = FormUpload::sole();

        $detail->set( 'adminFiles.document', UploadedFile::fake()->create( 'second.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors()
            ->assertSee( 'second.pdf' )
            ->assertDontSee( 'first.pdf' );

        expect( FormUpload::pluck( 'original_name' )->all() )->toBe( ['second.pdf'] )
            ->and( FormSubmissionValue::where( 'field_id', $this->field->id )->count() )->toBe( 1 );

        Storage::disk( 'form-uploads' )->assertMissing( $first->path );
    } );

    it( 'removes a file, deleting it from storage', function (): void {
        $detail = Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'contract.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' );

        $upload = FormUpload::sole();

        $detail->call( 'removeAdministrativeUpload', $upload->id )
            ->assertSet( 'removedFileName', 'contract.pdf' )
            ->assertSee( 'contract.pdf removed.' );

        expect( FormUpload::count() )->toBe( 0 )
            ->and( FormSubmissionValue::where( 'field_id', $this->field->id )->exists() )->toBeFalse();

        Storage::disk( 'form-uploads' )->assertMissing( $upload->path );
    } );

    it( 'asks before removing a file', function (): void {
        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'contract.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' )
            ->assertSeeHtml( 'wire:confirm="Remove contract.pdf? The file will be deleted."' );
    } );

    it( 'checks the file against the field rules', function (): void {
        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'notes.txt', 20 ) )
            ->call( 'saveAdministrativeFields' )
            ->assertHasErrors( ['adminFiles.document' => 'mimes'] );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'huge.pdf', 500 ) )
            ->call( 'saveAdministrativeFields' )
            ->assertHasErrors( ['adminFiles.document' => 'max'] );

        expect( FormUpload::count() )->toBe( 0 );
    } );

    it( 'requires a file only while none is stored for a required field', function (): void {
        $this->field->update( ['is_required' => true] );

        $detail = Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->call( 'saveAdministrativeFields' )
            ->assertHasErrors( ['adminFiles.document' => 'required'] );

        $detail->set( 'adminFiles.document', UploadedFile::fake()->create( 'contract.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors()
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors();

        expect( FormUpload::count() )->toBe( 1 );
    } );

    it( 'replaces every file of a multi-file field', function (): void {
        $this->field->update( ['field_config' => array_merge( $this->field->field_config, ['multiple' => true, 'max_files' => 2] )] );

        $detail = Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'adminFiles.document', [UploadedFile::fake()->create( 'a.pdf', 10 ), UploadedFile::fake()->create( 'b.pdf', 10 )] )
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors();

        $value = FormSubmissionValue::where( 'field_id', $this->field->id )->sole();

        expect( collect( $value->getUploads() )->pluck( 'original_name' )->all() )->toBe( ['a.pdf', 'b.pdf'] );

        $detail->set( 'adminFiles.document', [UploadedFile::fake()->create( 'a.pdf', 10 ), UploadedFile::fake()->create( 'b.pdf', 10 ), UploadedFile::fake()->create( 'c.pdf', 10 )] )
            ->call( 'saveAdministrativeFields' )
            ->assertHasErrors( 'adminFiles.document' );

        $detail->call( 'removeAdministrativeUpload', $value->getUploads()[0]->id );

        expect( collect( $value->fresh()->getUploads() )->pluck( 'original_name' )->all() )->toBe( ['b.pdf'] )
            ->and( $value->fresh()->value_array )->toHaveCount( 1 );
    } );

    it( 'does not remove a file from another field or submission', function (): void {
        $other  = app( SubmissionService::class )->create( $this->form, ['email' => 'bob@example.com'] );
        $upload = FormUpload::factory()->create( ['submission_id' => $other->id, 'field_id' => $this->field->id] );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->call( 'removeAdministrativeUpload', $upload->id );

        expect( $upload->fresh() )->not->toBeNull();
    } );

    it( 'does not offer a masked file field to users who may not reveal it', function (): void {
        $this->field->update( ['field_config' => array_merge( $this->field->field_config, ['mask_in_admin' => true] )] );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertDontSeeHtml( 'admin-file-document' )
            ->set( 'adminFiles.document', UploadedFile::fake()->create( 'contract.pdf', 20 ) )
            ->call( 'saveAdministrativeFields' );

        expect( FormUpload::count() )->toBe( 0 );
    } );
} );

describe( 'administrative file fields in the submission API', function (): void {
    beforeEach( function (): void {
        $this->field      = fileField( $this->form, ['visibility' => 'administrative'] );
        $this->submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'] );
        $this->url        = route( 'api.forms.submissions.update', [$this->form, $this->submission] );
    } );

    it( 'uploads a file through a multipart update', function (): void {
        $response = $this->actingAs( $this->staff )->post( $this->url, [
            '_method'     => 'PUT',
            'admin_files' => ['document' => UploadedFile::fake()->create( 'contract.pdf', 20 )],
        ], ['Accept' => 'application/json'] );

        $response->assertSuccessful();

        $upload = FormUpload::sole();

        expect( $response->json( 'data.uploads.0.original_name' ) )->toBe( 'contract.pdf' )
            ->and( $response->json( 'data.values' ) )->toHaveCount( 2 );

        Storage::disk( 'form-uploads' )->assertExists( $upload->path );
    } );

    it( 'rejects a file the field does not allow', function (): void {
        $this->actingAs( $this->staff )->post( $this->url, [
            '_method'     => 'PUT',
            'admin_files' => ['document' => UploadedFile::fake()->create( 'notes.txt', 20 )],
        ], ['Accept' => 'application/json'] )->assertUnprocessable()->assertJsonValidationErrors( 'admin_files.document' );

        expect( FormUpload::count() )->toBe( 0 );
    } );

    it( 'requires a file for a required field when the administrative fields are saved', function (): void {
        $this->field->update( ['is_required' => true] );

        $this->actingAs( $this->staff )->putJson( $this->url, ['admin_values' => []] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'admin_files.document' );

        $this->actingAs( $this->staff )->putJson( $this->url, ['is_starred' => true] )->assertSuccessful();
    } );

    it( 'removes a file', function (): void {
        $this->actingAs( $this->staff )->post( $this->url, [
            '_method'     => 'PUT',
            'admin_files' => ['document' => UploadedFile::fake()->create( 'contract.pdf', 20 )],
        ], ['Accept' => 'application/json'] );

        $upload = FormUpload::sole();

        $this->actingAs( $this->staff )->putJson( $this->url, ['remove_admin_uploads' => [$upload->id]] )
            ->assertSuccessful()
            ->assertJsonPath( 'data.uploads', [] );

        expect( FormUpload::count() )->toBe( 0 );

        Storage::disk( 'form-uploads' )->assertMissing( $upload->path );
    } );

    it( 'rejects removing a file that is not an administrative file of the submission', function (): void {
        $visible = fileField( $this->form, [], ['name' => 'resume', 'label' => 'Resume', 'sort_order' => 3] );
        $upload  = FormUpload::factory()->create( ['submission_id' => $this->submission->id, 'field_id' => $visible->id] );

        $this->actingAs( $this->staff )->putJson( $this->url, ['remove_admin_uploads' => [$upload->id]] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'remove_admin_uploads.0' );

        expect( $upload->fresh() )->not->toBeNull();
    } );

    it( 'leaves a masked file field to users who may reveal it', function (): void {
        $this->field->update( ['field_config' => array_merge( $this->field->field_config, ['mask_in_admin' => true] )] );

        $this->actingAs( $this->staff )->post( $this->url, [
            '_method'     => 'PUT',
            'admin_files' => ['document' => UploadedFile::fake()->create( 'contract.pdf', 20 )],
        ], ['Accept' => 'application/json'] )->assertSuccessful();

        expect( FormUpload::count() )->toBe( 0 );

        $this->actingAs( $this->admin )->post( $this->url, [
            '_method'     => 'PUT',
            'admin_files' => ['document' => UploadedFile::fake()->create( 'contract.pdf', 20 )],
        ], ['Accept' => 'application/json'] )->assertSuccessful();

        expect( FormUpload::count() )->toBe( 1 );
    } );
} );

describe( 'mask in admin', function (): void {
    beforeEach( function (): void {
        $this->field      = fileField( $this->form, ['mask_in_admin' => true] );
        $this->submission = app( SubmissionService::class )->create(
            $this->form,
            ['email'    => 'ada@example.com'],
            ['document' => UploadedFile::fake()->create( 'passport-scan.pdf', 20 )],
        );
        $this->upload = FormUpload::sole();
    } );

    it( 'hides the file name, link and attachment in the detail', function (): void {
        $this->actingAs( $this->staff );

        $download = route( 'forms.uploads.download', [$this->form, $this->submission, $this->upload] );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertSee( 'Masked' )
            ->assertDontSee( 'passport-scan' )
            ->assertDontSeeHtml( $download );

        $this->actingAs( $this->admin );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertSee( 'passport-scan.pdf' )
            ->assertSeeHtml( $download );
    } );

    it( 'hides the file name in the list', function (): void {
        $this->actingAs( $this->staff );

        Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
            ->assertDontSee( 'passport-scan' );
    } );

    it( 'hides the file in the API', function (): void {
        $response = $this->actingAs( $this->staff )->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) );
        $value    = collect( $response->json( 'data.values' ) )->firstWhere( 'field_name', 'document' );

        expect( $value['is_masked'] )->toBeTrue()
            ->and( $value['display_value'] )->toBe( '••••' )
            ->and( $value['value'] )->toBe( '••••' )
            ->and( $value['upload_id'] )->toBeNull()
            ->and( $value['upload_ids'] )->toBe( [] )
            ->and( $response->json( 'data.uploads' ) )->toBe( [] );

        $response = $this->actingAs( $this->admin )->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) );

        expect( collect( $response->json( 'data.values' ) )->firstWhere( 'field_name', 'document' )['upload_id'] )->toBe( $this->upload->id )
            ->and( $response->json( 'data.uploads.0.original_name' ) )->toBe( 'passport-scan.pdf' );
    } );

    it( 'blocks the download for users who may not reveal it', function (): void {
        $this->actingAs( $this->staff )
            ->get( route( 'forms.uploads.download', [$this->form, $this->submission, $this->upload] ) )
            ->assertForbidden();

        $this->actingAs( $this->staff )
            ->get( route( 'api.forms.uploads.download', [$this->submission, $this->upload] ) )
            ->assertForbidden();

        $this->actingAs( $this->admin )
            ->get( route( 'forms.uploads.download', [$this->form, $this->submission, $this->upload] ) )
            ->assertSuccessful();

        $this->actingAs( $this->admin )
            ->get( route( 'api.forms.uploads.download', [$this->submission, $this->upload] ) )
            ->assertSuccessful();
    } );

    it( 'leaves the file column out of exports unless the user may reveal it', function (): void {
        $exporter    = app( ExportService::class );
        $submissions = FormSubmission::with( 'values.field' )->get();

        $this->actingAs( $this->staff );

        expect( $exporter->buildHeaders( $this->form ) )->not->toContain( 'Document' )
            ->and( $exporter->buildRows( $this->form, $submissions )[0] )->not->toContain( 'passport-scan.pdf' )
            ->and( $exporter->exportToJson( $this->form, $submissions )[0]['data'] )->not->toHaveKey( 'document' );

        $this->actingAs( $this->admin );

        expect( $exporter->buildHeaders( $this->form ) )->toContain( 'Document' )
            ->and( $exporter->buildRows( $this->form, $submissions )[0] )->toContain( 'passport-scan.pdf' );
    } );
} );

describe( 'do not store', function (): void {
    beforeEach( function (): void {
        $this->field = fileField( $this->form, ['do_not_store' => true] );
        $this->file  = fn (): UploadedFile => UploadedFile::fake()->createWithContent( 'id-card.pdf', '%PDF-1.4 secret' );
    } );

    it( 'deletes the file once the deliveries have run', function (): void {
        config( ['mail.default' => 'array'] );
        Http::fake( ['*' => Http::response( [], 200 )] );
        config( ['artisanpack.forms.webhooks.enabled' => true, 'artisanpack.forms.webhooks.url' => 'https://hooks.example.com/forms'] );
        FormNotification::factory()->for( $this->form )->create( ['include_submission_data' => true] );

        $submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => ( $this->file )()] );

        $attachments = Mail::mailer( 'array' )->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getAttachments();

        expect( $attachments )->toHaveCount( 1 )
            ->and( $attachments[0]->getFilename() )->toBe( 'id-card.pdf' )
            ->and( $attachments[0]->getBody() )->toBe( '%PDF-1.4 secret' );

        Http::assertSent( fn ( $request ): bool => 'id-card.pdf' === ( $request['submission']['files'][0]['name'] ?? null )
            && base64_encode( '%PDF-1.4 secret' ) === $request['submission']['files'][0]['content']
            && 'document' === $request['submission']['files'][0]['field'] );

        expect( FormUpload::count() )->toBe( 0 )
            ->and( FormSubmissionValue::where( 'field_id', $this->field->id )->exists() )->toBeFalse()
            ->and( Storage::disk( 'form-uploads' )->allFiles() )->toBe( [] )
            ->and( $submission->fresh()->uploads )->toBeEmpty();
    } );

    it( 'deletes the file straight away when nothing delivers it', function (): void {
        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => ( $this->file )()] );

        expect( FormUpload::count() )->toBe( 0 )
            ->and( Storage::disk( 'form-uploads' )->allFiles() )->toBe( [] );
    } );

    it( 'keeps the file until every queued delivery has run', function (): void {
        Queue::fake();
        Mail::fake();
        Http::fake( ['*' => Http::response( [], 200 )] );
        config( ['artisanpack.forms.webhooks.enabled' => true, 'artisanpack.forms.webhooks.url' => 'https://hooks.example.com/forms'] );
        FormNotification::factory()->for( $this->form )->create( ['include_submission_data' => true] );

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => ( $this->file )()] );

        $upload = FormUpload::sole();

        expect( $upload->pending_deliveries )->toBe( 2 )
            ->and( $upload->isTransient() )->toBeTrue();

        $notification = Queue::pushed( SendFormNotification::class )->first();
        $webhook      = Queue::pushed( SendWebhook::class )->first();

        expect( $notification->transientUploadIds )->toBe( [$upload->id] )
            ->and( $notification->shouldBeEncrypted )->toBeTrue();

        $notification->handle();

        expect( $upload->fresh()->pending_deliveries )->toBe( 1 );

        $webhook->failed( new RuntimeException( 'Gave up' ) );

        expect( $upload->fresh() )->toBeNull();

        Storage::disk( 'form-uploads' )->assertMissing( $upload->path );
    } );

    it( 'keeps the file for a retry when a delivery throws', function (): void {
        Queue::fake();
        Http::fake( ['*' => Http::response( [], 500 )] );
        config( ['artisanpack.forms.webhooks.enabled' => true, 'artisanpack.forms.webhooks.url' => 'https://hooks.example.com/forms'] );

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => ( $this->file )()] );

        $webhook = Queue::pushed( SendWebhook::class )->first();

        expect( fn () => $webhook->handle() )->toThrow( RuntimeException::class )
            ->and( FormUpload::sole()->pending_deliveries )->toBe( 1 );
    } );

    it( 'never lists or serves the file in the admin', function (): void {
        Queue::fake();
        FormNotification::factory()->for( $this->form )->create();

        $submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => ( $this->file )()] );
        $upload     = FormUpload::sole();

        $this->actingAs( $this->admin );

        Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
            ->assertDontSee( 'id-card.pdf' );

        $this->getJson( route( 'api.forms.submissions.show', [$this->form, $submission] ) )
            ->assertJsonPath( 'data.uploads', [] );

        $this->get( route( 'forms.uploads.download', [$this->form, $submission, $upload] ) )->assertNotFound();
        $this->get( route( 'api.forms.uploads.download', [$submission, $upload] ) )->assertNotFound();
    } );

    it( 'stores every file of a multi-file field until delivered', function (): void {
        Queue::fake();
        FormNotification::factory()->for( $this->form )->create();
        $this->field->update( ['field_config' => array_merge( $this->field->field_config, ['multiple' => true] )] );

        $submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => [( $this->file )(), UploadedFile::fake()->create( 'b.pdf', 10 )]] );

        $ids = FormUpload::orderBy( 'id' )->pluck( 'id' )->all();

        expect( $ids )->toHaveCount( 2 )
            ->and( $submission->getTransientValues()[0]['value_array'] )->toBe( $ids )
            ->and( Queue::pushed( SendFormNotification::class )->first()->transientUploadIds )->toBe( $ids );
    } );

    it( 'deletes files whose deliveries never reported back a day later', function (): void {
        Queue::fake();
        FormNotification::factory()->for( $this->form )->create();

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'], ['document' => ( $this->file )()] );

        $this->artisan( 'forms:prune-submissions' )->assertSuccessful();

        expect( FormUpload::count() )->toBe( 1 );

        $this->travel( 2 )->days();

        $this->artisan( 'forms:prune-submissions', ['--dry-run' => true] )
            ->expectsOutputToContain( 'Would delete 1 "Do not store" file(s)' )
            ->assertSuccessful();

        expect( FormUpload::count() )->toBe( 1 );

        $this->artisan( 'forms:prune-submissions' )
            ->expectsOutputToContain( 'Deleted 1 "Do not store" file(s)' )
            ->assertSuccessful();

        expect( FormUpload::count() )->toBe( 0 )
            ->and( Storage::disk( 'form-uploads' )->allFiles() )->toBe( [] );
    } );
} );

describe( 'redact after N days', function (): void {
    beforeEach( function (): void {
        $this->field      = fileField( $this->form, ['redact_after_days' => 30] );
        $this->submission = app( SubmissionService::class )->create(
            $this->form,
            ['email'    => 'ada@example.com'],
            ['document' => UploadedFile::fake()->create( 'old.pdf', 20 )],
        );
        $this->upload = FormUpload::sole();
    } );

    it( 'deletes the stored file and upload record and marks the value redacted', function (): void {
        $this->artisan( 'forms:prune-submissions' )->assertSuccessful();

        expect( FormUpload::count() )->toBe( 1 );

        $this->travel( 31 )->days();

        $this->artisan( 'forms:prune-submissions' )
            ->expectsOutputToContain( 'Redacted 1 field value(s)' )
            ->assertSuccessful();

        $value = FormSubmissionValue::where( 'field_id', $this->field->id )->sole();

        expect( FormUpload::count() )->toBe( 0 )
            ->and( $value->isRedacted() )->toBeTrue()
            ->and( $value->upload_id )->toBeNull()
            ->and( $value->getUploads() )->toBe( [] );

        Storage::disk( 'form-uploads' )->assertMissing( $this->upload->path );

        $this->actingAs( $this->admin );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertDontSee( 'old.pdf' )
            ->assertSee( '[Redacted]' );
    } );

    it( 'only counts the values on a dry run', function (): void {
        $this->travel( 31 )->days();

        $this->artisan( 'forms:prune-submissions', ['--dry-run' => true] )->assertSuccessful();

        expect( FormUpload::count() )->toBe( 1 );
    } );
} );
