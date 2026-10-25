<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\MediaLibraryStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #124: the allowed types in a file field's note and the
 * optional Media Library storage.
 */
beforeEach( function (): void {
    Storage::fake( 'form-uploads' );
    Storage::fake( 'public' );

    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'resume',
        'label'        => 'Resume',
        'help_text'    => null,
        'field_config' => ['max_size' => 200, 'allowed_types' => ['pdf', 'docx']],
    ] );

    $this->submit = fn ( FormField $field ) => Livewire::test( FormRenderer::class, ['form' => $field->form->fresh()] )
        ->set( 'formLoadedAt', time() - 10 )
        ->set( 'files.resume', UploadedFile::fake()->create( 'cv.pdf', 50, 'application/pdf' ) )
        ->call( 'submit' )
        ->assertHasNoErrors();
} );

/**
 * A media library that stores files on the faked public disk, the way
 * artisanpack-ui/media-library names them (after the client file name).
 */
function fakeMediaLibrary( bool $accepts = true ): object
{
    return new class( $accepts ) extends MediaLibraryStorage {
        public array $stored = [];

        public array $deleted = [];

        public array $options = [];

        public function __construct( private bool $accepts )
        {
        }

        public function isAvailable(): bool
        {
            return true;
        }

        protected function upload( UploadedFile $file, array $options ): object
        {
            if ( ! $this->accepts ) {
                throw new RuntimeException( 'Guest uploads are not enabled.' );
            }

            $path           = $file->storeAs( '2026/10', $file->getClientOriginalName(), 'public' );
            $this->stored[] = $path;
            $this->options  = $options;

            return (object) ['id' => 42, 'disk' => 'public', 'file_path' => $path, 'file_size' => 51200, 'mime_type' => 'application/pdf'];
        }

        protected function deleteMedia( int $mediaId ): bool
        {
            $this->deleted[] = $mediaId;

            return true;
        }
    };
}

describe( 'limits note', function (): void {
    it( 'lists the allowed types with the size limit', function (): void {
        expect( $this->field->getFileLimitsHint() )->toBe( 'Allowed file types: PDF, DOCX · Maximum file size: 200 KB' )
            ->and( $this->field->getDescribedBy() )->toBe( 'limits-resume' );
    } );

    it( 'has no note without limits', function (): void {
        $field = FormField::factory()->make( ['type' => 'file', 'name' => 'any', 'help_text' => null, 'field_config' => ['max_size' => 0, 'allowed_types' => []]] );

        expect( $field->getFileLimitsHint() )->toBeNull()
            ->and( $field->getDescribedBy() )->toBeNull();
    } );

    it( 'adds the file count and total size for multiple files', function (): void {
        $this->field->update( ['field_config' => ['multiple' => true, 'max_files' => 3, 'max_size' => 200, 'max_total_size' => 500, 'allowed_types' => ['png']]] );

        expect( $this->field->fresh()->getFileLimitsHint() )->toBe( 'Up to 3 files · Allowed file types: PNG · Maximum file size: 200 KB · Maximum total size: 500 KB' );
    } );

    it( 'renders the note referenced by the input', function (): void {
        view()->share( 'errors', new ViewErrorBag() );

        $html = FieldRenderer::render( $this->field );

        expect( $html )->toContain( 'id="limits-resume"' )
            ->toContain( 'Allowed file types: PDF, DOCX · Maximum file size: 200 KB' )
            ->toContain( 'aria-describedby="limits-resume"' );

        $this->field->update( ['field_config' => ['multiple' => true, 'allowed_types' => ['pdf']]] );

        expect( FieldRenderer::render( $this->field->fresh() ) )->toContain( 'id="limits-resume"' )
            ->toContain( 'Allowed file types: PDF' );
    } );
} );

describe( 'media library storage', function (): void {
    it( 'offers the storage setting on the advanced tab', function (): void {
        expect( FieldSettings::find( $this->field, 'storage' ) )->toMatchArray( ['input' => 'select', 'tab' => 'advanced', 'default' => 'forms'] )
            ->and( $this->field->usesMediaLibraryStorage() )->toBeFalse();
    } );

    it( 'saves the file as a media item when the field asks for it', function (): void {
        $media = fakeMediaLibrary();
        $this->app->instance( MediaLibraryStorage::class, $media );
        $this->field->update( ['field_config' => ['storage' => 'media_library', 'allowed_types' => ['pdf']]] );

        ( $this->submit )( $this->field->fresh() );

        $upload = FormUpload::query()->sole();

        expect( $upload->media_id )->toBe( 42 )
            ->and( $upload->isInMediaLibrary() )->toBeTrue()
            ->and( $upload->disk )->toBe( 'public' )
            ->and( $upload->path )->toBe( $media->stored[0] )
            ->and( $upload->original_name )->toBe( 'cv.pdf' )
            ->and( Storage::disk( 'form-uploads' )->allFiles() )->toBe( [] );

        Storage::disk( 'public' )->assertExists( $upload->path );

        $upload->delete();

        expect( $media->deleted )->toBe( [42] );
    } );

    it( 'deletes the media item when the submission is pruned', function (): void {
        $media = fakeMediaLibrary();
        $this->app->instance( MediaLibraryStorage::class, $media );
        $this->field->update( ['field_config' => ['storage' => 'media_library', 'allowed_types' => ['pdf']]] );

        ( $this->submit )( $this->field->fresh() );

        $this->form->submissions()->update( ['created_at' => now()->subDays( 30 )] );

        $this->artisan( 'forms:prune-submissions', ['--days' => 7] )->assertSuccessful();

        expect( $media->deleted )->toBe( [42] )
            ->and( FormUpload::query()->count() )->toBe( 0 );
    } );

    it( 'names the media file with the extension found from its contents', function (): void {
        $media = fakeMediaLibrary();
        $image = UploadedFile::fake()->image( 'invoice.png' );
        $file  = new UploadedFile( $image->getRealPath(), 'invoice.php', 'image/png', null, true );

        expect( $file->guessExtension() )->toBe( 'png' );

        $media->store( $file, $this->field, $file->guessExtension() );

        expect( $media->stored )->toBe( ['2026/10/invoice.png'] )
            ->and( $media->options['title'] )->toBe( 'invoice.php' );
    } );

    it( 'keeps files a browser could run on the forms disk', function (): void {
        $media = fakeMediaLibrary();
        $this->app->instance( MediaLibraryStorage::class, $media );

        expect( $media->store( UploadedFile::fake()->create( 'logo.svg', 1, 'image/svg+xml' ), $this->field, 'svg' ) )->toBeNull()
            ->and( $media->store( UploadedFile::fake()->create( 'page.html', 1, 'text/html' ), $this->field, 'HTML' ) )->toBeNull()
            ->and( $media->stored )->toBe( [] );
    } );

    it( 'attributes media items to the configured user, not the visitor', function (): void {
        $media = fakeMediaLibrary();
        config()->set( 'artisanpack.forms.uploads.media_library', ['folder_id' => '7', 'uploaded_by' => null] );

        $media->store( UploadedFile::fake()->create( 'cv.pdf', 1, 'application/pdf' ), $this->field, 'pdf' );

        expect( $media->options )->toBe( ['title' => 'cv.pdf', 'folder_id' => 7] );
    } );

    it( 'keeps the file on the forms disk when the media library does not take it', function (): void {
        $this->app->instance( MediaLibraryStorage::class, fakeMediaLibrary( false ) );
        $this->field->update( ['field_config' => ['storage' => 'media_library', 'allowed_types' => ['pdf']]] );

        ( $this->submit )( $this->field->fresh() );

        $upload = FormUpload::query()->sole();

        expect( $upload->media_id )->toBeNull()
            ->and( $upload->disk )->toBe( config( 'artisanpack.forms.uploads.disk' ) );

        Storage::disk( 'form-uploads' )->assertExists( $upload->path );

        $upload->delete();

        Storage::disk( 'form-uploads' )->assertMissing( $upload->path );
    } );

    it( 'uses the forms disk for fields left on it', function (): void {
        $media = fakeMediaLibrary();
        $this->app->instance( MediaLibraryStorage::class, $media );

        ( $this->submit )( $this->field );

        expect( $media->stored )->toBe( [] )
            ->and( FormUpload::query()->sole()->media_id )->toBeNull();
    } );

    it( 'stores nothing without the media library package', function (): void {
        $storage = new MediaLibraryStorage();

        expect( $storage->isAvailable() )->toBeFalse()
            ->and( $storage->store( UploadedFile::fake()->create( 'a.pdf', 1 ), $this->field, 'pdf' ) )->toBeNull()
            ->and( $storage->delete( 42 ) )->toBeFalse();
    } )->skip( function_exists( 'apUploadMedia' ), 'artisanpack-ui/media-library is installed' );
} );
