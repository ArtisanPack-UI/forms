<?php

/**
 * Image choices support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads and stores the images of a radio or checkbox group's options.
 *
 * With the field's `display` setting on `images` (see
 * FormField::showsImageChoices()), an option may carry:
 *
 * - `image`: the image's URL, an `http`/`https` address or a path on the site
 *   (`/storage/...`). Anything else, such as a `javascript:` or `data:` URL,
 *   is dropped when the option is read.
 * - `image_alt`: alternative text for the image. The option label stays the
 *   choice's accessible name; the alt text describes what the image shows.
 *
 * Images uploaded in the builders are stored with artisanpack-ui/media-library
 * when it is installed, and on the public `uploads.choice_images.disk`
 * otherwise. An image URL may point at any `http`/`https` address, which
 * every visitor's browser then loads, so only form editors can set one. Only JPEG, PNG, GIF and WebP images are accepted. Resolved from
 * the container, so it can be replaced.
 *
 * @since 1.6.0
 */
class ChoiceImages
{
    /**
     * The longest image URL and alt text kept.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_LENGTH = 2048;

    /**
     * The image types an upload may be.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const MIMES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * The Livewire media modal class shipped by artisanpack-ui/media-library.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MEDIA_MODAL = 'ArtisanPackUI\\MediaLibrary\\Livewire\\Components\\MediaModal';

    /**
     * Gets an option's image URL, when it is safe to show.
     *
     * @since 1.6.0
     *
     * @param  mixed  $option  The option.
     *
     * @return string|null The URL, or null when the option has none or it is not an `http`/`https` address or a site path.
     */
    public static function url( mixed $option ): ?string
    {
        $url = is_array( $option ) ? ( $option['image'] ?? null ) : null;

        if ( ! is_string( $url ) ) {
            return null;
        }

        $url = trim( $url );

        if ( '' === $url || strlen( $url ) > self::MAX_LENGTH || 1 === preg_match( '/[\x00-\x20"\'<>\\\\`]/', $url ) ) {
            return null;
        }

        // A site path, but not a protocol-relative `//host` address
        if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
            return $url;
        }

        return 1 === preg_match( '#^https?://[^/]#i', $url ) ? $url : null;
    }

    /**
     * Gets an option's image alt text.
     *
     * @since 1.6.0
     *
     * @param  mixed  $option  The option.
     *
     * @return string The alt text, or an empty string when the option sets none.
     */
    public static function alt( mixed $option ): string
    {
        $alt = is_array( $option ) ? ( $option['image_alt'] ?? null ) : null;

        return is_string( $alt ) ? mb_substr( trim( $alt ), 0, self::MAX_LENGTH ) : '';
    }

    /**
     * Gets options with their images made safe to show.
     *
     * Each option's `image` is replaced with its safe URL, or removed, and
     * its `image_alt` trimmed. Other keys and non-array options are kept.
     *
     * @since 1.6.0
     *
     * @param  array<int|string, mixed>  $options  The options.
     *
     * @return array<int|string, mixed> The options.
     */
    public static function sanitizeOptions( array $options ): array
    {
        foreach ( $options as $key => $option ) {
            if ( ! is_array( $option ) || ( ! array_key_exists( 'image', $option ) && ! array_key_exists( 'image_alt', $option ) ) ) {
                continue;
            }

            $url = self::url( $option );
            unset( $option['image'], $option['image_alt'] );

            if ( null !== $url ) {
                $option['image']     = $url;
                $option['image_alt'] = self::alt( $options[ $key ] );
            }

            $options[ $key ] = $option;
        }

        return $options;
    }

    /**
     * Gets the validation rules for an uploaded choice image.
     *
     * @since 1.6.0
     *
     * @return list<string> The rules.
     */
    public static function uploadRules(): array
    {
        return ['required', 'file', 'image', 'mimes:' . implode( ',', self::MIMES ), 'max:' . (int) config( 'artisanpack.forms.uploads.choice_images.max_size', 2048 )];
    }

    /**
     * Checks whether the media library's picker can be offered in the Livewire builder.
     *
     * @since 1.6.0
     *
     * @return bool True when artisanpack-ui/media-library is installed.
     */
    public function mediaPickerAvailable(): bool
    {
        return $this->mediaLibraryInstalled() && class_exists( self::MEDIA_MODAL );
    }

    /**
     * Stores an uploaded choice image and gets its URL.
     *
     * The image goes to the media library when it is installed, otherwise to
     * the public choice images disk, in a folder per form. An image the media
     * library refuses is not stored, so its own permission and quota checks
     * hold.
     *
     * @since 1.6.0
     *
     * @param  UploadedFile  $file  The validated image.
     * @param  Form  $form  The form the image is for.
     *
     * @return string|null The image's URL, or null when it could not be stored.
     */
    public function store( UploadedFile $file, Form $form ): ?string
    {
        if ( $this->mediaLibraryInstalled() ) {
            return $this->storeInMediaLibrary( $file );
        }

        $disk      = (string) config( 'artisanpack.forms.uploads.choice_images.disk', 'public' );
        $directory = trim( (string) config( 'artisanpack.forms.uploads.choice_images.directory', 'forms/choice-images' ), '/' ) . '/' . $form->getKey();

        try {
            $path = $file->storePubliclyAs( $directory, $file->hashName(), ['disk' => $disk] );
        } catch ( Throwable $exception ) {
            Log::warning( 'ArtisanPackUI Forms: choice image could not be stored', [
                'form_id' => $form->getKey(),
                'error'   => $exception::class,
            ] );

            return null;
        }

        return false === $path ? null : Storage::disk( $disk )->url( $path );
    }

    /**
     * Checks whether artisanpack-ui/media-library is installed to store uploads.
     *
     * @since 1.6.0
     *
     * @return bool True when its upload helper exists.
     */
    protected function mediaLibraryInstalled(): bool
    {
        return function_exists( 'apUploadMedia' );
    }

    /**
     * Stores an image with the media library.
     *
     * @since 1.6.0
     *
     * @param  UploadedFile  $file  The image.
     *
     * @return string|null The media item's URL, or null when the media library did not take the image.
     */
    protected function storeInMediaLibrary( UploadedFile $file ): ?string
    {
        try {
            $media = apUploadMedia( $file, array_filter( [
                'title'     => $file->getClientOriginalName(),
                'folder_id' => config( 'artisanpack.forms.uploads.media_library.folder_id' ),
            ], fn ( mixed $option ): bool => null !== $option && '' !== $option ) );

            $url = method_exists( $media, 'url' ) ? $media->url() : null;
        } catch ( Throwable $exception ) {
            Log::warning( 'ArtisanPackUI Forms: media library upload of a choice image failed', [
                'error' => $exception::class,
            ] );

            return null;
        }

        return is_string( $url ) && '' !== $url ? $url : null;
    }
}
