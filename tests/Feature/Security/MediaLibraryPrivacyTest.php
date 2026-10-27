<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\MediaLibraryStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guards SEC-05 / BE-6: a masked or "Do not store" file field never puts its
 * files in the (public by default) media library, whatever its Storage
 * setting.
 */
beforeEach( function (): void {
    Storage::fake( 'form-uploads' );
    Storage::fake( 'public' );

    $this->media = new class extends MediaLibraryStorage {
        public int $uploads = 0;

        public function __construct()
        {
        }

        public function isAvailable(): bool
        {
            return true;
        }

        protected function upload( UploadedFile $file, array $options ): object
        {
            $this->uploads++;

            $path = $file->storeAs( 'media', $file->getClientOriginalName(), 'public' );

            return (object) ['id' => 7, 'disk' => 'public', 'file_path' => $path, 'file_size' => 1024, 'mime_type' => 'application/pdf'];
        }

        protected function deleteMedia( int $mediaId ): bool
        {
            return true;
        }
    };

    $this->app->instance( MediaLibraryStorage::class, $this->media );

    $this->form = Form::factory()->create( ['is_active' => true] );
} );

it( 'keeps a private file field\'s uploads on the forms disk', function ( array $privacy ): void {
    $field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'scan',
        'field_config' => array_merge( ['storage' => 'media_library', 'allowed_types' => ['pdf']], $privacy ),
    ] );

    expect( $field->usesMediaLibraryStorage() )->toBeFalse();

    app( SubmissionService::class )->create( $this->form, [], ['scan' => UploadedFile::fake()->create( 'scan.pdf', 10, 'application/pdf' )] );

    $upload = FormUpload::query()->where( 'field_id', $field->id )->first();

    expect( $this->media->uploads )->toBe( 0 )
        ->and( Storage::disk( 'public' )->allFiles() )->toBe( [] );

    if ( null !== $upload ) {
        expect( $upload->media_id )->toBeNull()
            ->and( $upload->disk )->not->toBe( 'public' );
    }
} )->with( [
    'mask in admin' => [['mask_in_admin' => true]],
    'do not store'  => [['do_not_store' => true]],
] );

it( 'still uses the media library for other file fields', function (): void {
    $field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'scan',
        'field_config' => ['storage' => 'media_library', 'allowed_types' => ['pdf']],
    ] );

    app( SubmissionService::class )->create( $this->form, [], ['scan' => UploadedFile::fake()->create( 'scan.pdf', 10, 'application/pdf' )] );

    expect( $this->media->uploads )->toBe( 1 )
        ->and( FormUpload::query()->where( 'field_id', $field->id )->value( 'media_id' ) )->toBe( 7 );
} );
