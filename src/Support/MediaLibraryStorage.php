<?php

/**
 * Media Library storage for file uploads.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores a file field's uploads with artisanpack-ui/media-library.
 *
 * Used for file fields whose Storage setting is "Media Library" (see
 * FormField::usesMediaLibraryStorage()). The upload is saved as a media item
 * titled with the file's name, in the `uploads.media_library.folder_id`
 * folder and attributed to `uploads.media_library.uploaded_by` when set.
 * The media library's disk is usually public, so the file is handed over
 * under the extension the forms package validated from its contents, never
 * the visitor's, and types a browser would run (SVG, HTML, XML) are never
 * handed over. When the media library is not installed, or rejects the file
 * (for example because guest uploads are off), store() returns null and the
 * file stays on the private forms disk. Resolved from the container, so it
 * can be replaced.
 *
 * @since 1.6.0
 */
class MediaLibraryStorage
{
    /**
     * Extensions that are kept on the forms disk, since a browser opening
     * them from a public disk could run their scripts.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const UNSAFE_EXTENSIONS = [ 'svg', 'svgz', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'xsl', 'js', 'mjs', 'php', 'phtml', 'phar' ];

    /**
     * Checks whether artisanpack-ui/media-library is installed.
     *
     * @since 1.6.0
     *
     * @return bool True when its `apUploadMedia()` helper exists.
     */
    public function isAvailable(): bool
    {
        return function_exists( 'apUploadMedia' );
    }

    /**
     * Stores an upload as a media item.
     *
     * @since 1.6.0
     *
     * @param  UploadedFile  $file  The uploaded file.
     * @param  FormField  $field  The file field.
     * @param  string  $extension  The extension validated from the file's contents.
     *
     * @return array{media_id: int, disk: string, path: string, size: int, mime_type: string}|null The stored file, or null when the media library did not take it.
     */
    public function store( UploadedFile $file, FormField $field, string $extension ): ?array
    {
        $extension = strtolower( $extension );
        $path      = $file->getRealPath();

        if ( ! $this->isAvailable() || in_array( $extension, self::UNSAFE_EXTENSIONS, true ) || false === $path ) {
            return null;
        }

        $options = array_filter( [
            'title'       => $file->getClientOriginalName(),
            'folder_id'   => $this->configuredId( 'folder_id' ),
            'uploaded_by' => $this->configuredId( 'uploaded_by' ),
        ], fn ( mixed $option ): bool => null !== $option );

        // The media library names the file after its client name, so rename
        // it to the validated extension first.
        $baseName = pathinfo( $file->getClientOriginalName(), PATHINFO_FILENAME );
        $renamed  = new UploadedFile( $path, ( '' !== $baseName ? $baseName : 'upload' ) . '.' . $extension, $file->getMimeType(), null, true );

        try {
            $media = $this->upload( $renamed, $options );
        } catch ( Throwable $exception ) {
            Log::warning( 'ArtisanPackUI Forms: media library upload failed, storing on the forms disk', [
                'field_id' => $field->id,
                'error'    => $exception::class,
            ] );

            return null;
        }

        return [
            'media_id'  => (int) $media->id,
            'disk'      => $this->diskOf( $media ),
            'path'      => (string) $media->file_path,
            'size'      => (int) ( $media->file_size ?? $file->getSize() ),
            'mime_type' => (string) ( $media->mime_type ?? $file->getMimeType() ?? 'application/octet-stream' ),
        ];
    }

    /**
     * Deletes a media item and its files, when the media library is installed.
     *
     * @since 1.6.0
     *
     * @param  int  $mediaId  The media item's ID.
     *
     * @return bool True when the media item was deleted.
     */
    public function delete( int $mediaId ): bool
    {
        if ( ! $this->isAvailable() ) {
            return false;
        }

        try {
            return $this->deleteMedia( $mediaId );
        } catch ( Throwable $exception ) {
            Log::warning( 'ArtisanPackUI Forms: media library item could not be deleted', [
                'media_id' => $mediaId,
                'error'    => $exception::class,
            ] );

            return false;
        }
    }

    /**
     * Saves a file as a media item with `apUploadMedia()`.
     *
     * @since 1.6.0
     *
     * @param  UploadedFile  $file  The file, named with its validated extension.
     * @param  array<string, mixed>  $options  The media item's options.
     *
     * @return object The media item.
     */
    protected function upload( UploadedFile $file, array $options ): object
    {
        return apUploadMedia( $file, $options );
    }

    /**
     * Deletes a media item and its files with `apDeleteMedia()`.
     *
     * @since 1.6.0
     *
     * @param  int  $mediaId  The media item's ID.
     *
     * @return bool True when the media item was deleted.
     */
    protected function deleteMedia( int $mediaId ): bool
    {
        return apDeleteMedia( $mediaId );
    }

    /**
     * Gets the disk a media item's file was stored on, applying the media
     * library's `ap.mediaLibrary.storageDisk` filter the way its upload does
     * (with no media item as context).
     *
     * @since 1.6.0
     *
     * @param  object  $media  The media item.
     *
     * @return string The disk name.
     */
    protected function diskOf( object $media ): string
    {
        return (string) applyFilters( 'ap.mediaLibrary.storageDisk', (string) $media->disk, null );
    }

    /**
     * Reads a positive ID from the `uploads.media_library` config.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The config key.
     *
     * @return int|null The ID, or null when unset.
     */
    protected function configuredId( string $key ): ?int
    {
        $value = config( "artisanpack.forms.uploads.media_library.{$key}" );

        return is_numeric( $value ) && (int) $value > 0 ? (int) $value : null;
    }
}
