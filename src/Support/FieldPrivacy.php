<?php

/**
 * Per-field privacy support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Masks values of fields with "Mask in admin" on and checks who may see them.
 *
 * @since 1.6.0
 */
class FieldPrivacy
{
    /**
     * The characters shown in place of a masked value.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MASK = '••••';

    /**
     * How many trailing characters a masked value keeps.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const VISIBLE_CHARACTERS = 4;

    /**
     * The shortest value whose trailing characters are kept. Shorter values
     * are masked in full, since four characters would give most of them away.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MIN_LENGTH_TO_SHOW_END = 8;

    /**
     * Masks a value, keeping its last characters when it is long enough.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The value.
     *
     * @return string The masked value, such as `••••1234`, or `''` for a blank value.
     */
    public static function mask( string $value ): string
    {
        if ( '' === $value ) {
            return '';
        }

        if ( mb_strlen( $value ) < self::MIN_LENGTH_TO_SHOW_END ) {
            return self::MASK;
        }

        return self::MASK . mb_substr( $value, -self::VISIBLE_CHARACTERS );
    }

    /**
     * Gets the IDs of the fields masked in the admin.
     *
     * The setting lives in the `field_config` JSON, so it is read in PHP to
     * stay portable across databases.
     *
     * @since 1.6.0
     *
     * @return list<int> The field IDs.
     */
    public static function maskedFieldIds(): array
    {
        return FormField::query()
            ->whereNotNull( 'field_config' )
            ->get()
            ->filter( fn ( FormField $field ): bool => $field->isMaskedInAdmin() )
            ->values()
            ->modelKeys();
    }

    /**
     * Checks whether an upload's file is masked from a user.
     *
     * A file uploaded to a field with "Mask in admin" on is hidden (its name,
     * preview and download) unless the user may reveal masked values. Once
     * the field is deleted, the setting copied onto the upload is used.
     *
     * @since 1.6.0
     *
     * @param  FormUpload  $upload  The upload.
     * @param  Authenticatable|null  $user  The user, or null for the authenticated user.
     *
     * @return bool True when masked.
     */
    public static function masksUpload( FormUpload $upload, ?Authenticatable $user = null ): bool
    {
        $masked = null !== $upload->field ? $upload->field->isMaskedInAdmin() : true === (bool) $upload->is_masked;

        return $masked && ! self::canReveal( $user );
    }

    /**
     * Gets the uploads of a submission the admin may list for a user.
     *
     * Leaves out the files of "Do not store" fields, which are only kept for
     * the submission's deliveries, and the files masked from the user.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormUpload>  $uploads  The uploads.
     * @param  Authenticatable|null  $user  The user, or null for the authenticated user.
     *
     * @return Collection<int, FormUpload> The uploads to list.
     */
    public static function visibleUploads( iterable $uploads, ?Authenticatable $user = null ): Collection
    {
        return collect( $uploads )
            ->reject( fn ( FormUpload $upload ): bool => $upload->isTransient() || self::masksUpload( $upload, $user ) )
            ->values();
    }

    /**
     * Checks whether a user may see masked values in full.
     *
     * @since 1.6.0
     *
     * @param  Authenticatable|null  $user  The user, or null for the authenticated user.
     *
     * @return bool True when the `revealMaskedValues` ability is granted.
     */
    public static function canReveal( ?Authenticatable $user = null ): bool
    {
        $gate = null === $user ? Gate::getFacadeRoot() : Gate::forUser( $user );

        return $gate->allows( 'revealMaskedValues', FormSubmission::class );
    }
}
