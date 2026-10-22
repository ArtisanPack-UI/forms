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
use Illuminate\Contracts\Auth\Authenticatable;
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
