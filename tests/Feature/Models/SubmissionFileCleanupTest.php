<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Livewire\SubmissionsList;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\FormService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\MediaLibraryStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards BE-5: deleting a submission or a form, by any path, removes its
 * stored files and media library items, not only its database rows.
 */
beforeEach( function (): void {
    Storage::fake( 'form-uploads' );
    Storage::fake( 'public' );

    $this->media = new class extends MediaLibraryStorage {
        public array $deleted = [];

        public function __construct()
        {
        }

        public function isAvailable(): bool
        {
            return true;
        }

        protected function upload( UploadedFile $file, array $options ): object
        {
            $path = $file->storeAs( 'media', $file->hashName(), 'public' );

            return (object) ['id' => 99, 'disk' => 'public', 'file_path' => $path, 'file_size' => 1024, 'mime_type' => 'application/pdf'];
        }

        protected function deleteMedia( int $mediaId ): bool
        {
            // The media library removes the item's file along with it
            $this->deleted[] = $mediaId;
            Storage::disk( 'public' )->deleteDirectory( 'media' );

            return true;
        }
    };

    $this->app->instance( MediaLibraryStorage::class, $this->media );

    $this->admin = User::factory()->create( ['is_admin' => true] );
    $this->form  = Form::factory()->create( ['is_active' => true, 'user_id' => $this->admin->id] );
    FormField::factory()->for( $this->form )->create( ['type' => 'file', 'name' => 'cv', 'field_config' => ['allowed_types' => ['pdf']]] );
    FormField::factory()->for( $this->form )->create( ['type' => 'file', 'name' => 'scan', 'sort_order' => 2, 'field_config' => ['allowed_types' => ['pdf'], 'storage' => 'media_library']] );

    $this->submit = fn (): FormSubmission => app( SubmissionService::class )->create( $this->form, [], [
        'cv'   => UploadedFile::fake()->create( 'cv.pdf', 10, 'application/pdf' ),
        'scan' => UploadedFile::fake()->create( 'scan.pdf', 10, 'application/pdf' ),
    ] );

    $this->assertRemoved = function ( FormSubmission $submission, array $paths ): void {
        expect( FormSubmission::query()->find( $submission->id ) )->toBeNull()
            ->and( FormUpload::query()->where( 'submission_id', $submission->id )->count() )->toBe( 0 )
            ->and( $this->media->deleted )->toContain( 99 );

        foreach ( $paths as $disk => $path ) {
            Storage::disk( $disk )->assertMissing( $path );
        }
    };

    $this->paths = fn ( FormSubmission $submission ): array => FormUpload::query()->where( 'submission_id', $submission->id )->get()->mapWithKeys( fn ( FormUpload $upload ): array => [$upload->disk => $upload->path] )->all();
} );

it( 'removes the files when the model is deleted', function (): void {
    $submission = ( $this->submit )();
    $paths      = ( $this->paths )( $submission );

    expect( $paths )->toHaveKeys( ['form-uploads', 'public'] );

    $submission->delete();

    ( $this->assertRemoved )( $submission, $paths );
} );

it( 'removes the files when deleted from the submissions list', function (): void {
    $submission = ( $this->submit )();
    $paths      = ( $this->paths )( $submission );

    Livewire::actingAs( $this->admin )->test( SubmissionsList::class, ['formId' => $this->form->id] )
        ->call( 'delete', $submission->id );

    ( $this->assertRemoved )( $submission, $paths );
} );

it( 'removes the files when bulk deleted from the submissions list', function (): void {
    $first  = ( $this->submit )();
    $second = ( $this->submit )();
    $paths  = [( $this->paths )( $first ), ( $this->paths )( $second )];

    Livewire::actingAs( $this->admin )->test( SubmissionsList::class, ['formId' => $this->form->id] )
        ->set( 'selected', [$first->id, $second->id] )
        ->call( 'bulkDelete' );

    ( $this->assertRemoved )( $first, $paths[0] );
    ( $this->assertRemoved )( $second, $paths[1] );
} );

it( 'removes the files when deleted from the submission detail', function (): void {
    $submission = ( $this->submit )();
    $paths      = ( $this->paths )( $submission );

    Livewire::actingAs( $this->admin )->test( SubmissionDetail::class, ['submission' => $submission] )
        ->call( 'delete' );

    ( $this->assertRemoved )( $submission, $paths );
} );

it( 'removes the files when deleted through the API', function (): void {
    $submission = ( $this->submit )();
    $paths      = ( $this->paths )( $submission );

    $this->actingAs( $this->admin )
        ->deleteJson( route( 'api.forms.submissions.destroy', [$this->form, $submission] ) )
        ->assertSuccessful();

    ( $this->assertRemoved )( $submission, $paths );
} );

it( 'removes the files when the form is deleted', function (): void {
    $submission = ( $this->submit )();
    $paths      = ( $this->paths )( $submission );

    app( FormService::class )->delete( $this->form );

    ( $this->assertRemoved )( $submission, $paths );
} );
