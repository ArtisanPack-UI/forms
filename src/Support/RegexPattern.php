<?php

/**
 * Regex pattern support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Reads a field's "Pattern (Regex)" setting as a PHP pattern.
 *
 * The setting's canonical form is a delimited PHP pattern, such as
 * `/^[a-z]+$/i` (the builder's placeholder). A pattern typed without
 * delimiters, such as `^[a-z]+$`, is wrapped in `/…/u`. A pattern that does
 * not compile either way is ignored, so a bad setting never breaks
 * submissions. `resources/js/shared/validation.ts` reads both forms the same
 * way for React and Vue.
 *
 * @since 1.6.0
 */
class RegexPattern
{
    /**
     * Gets the PHP pattern for a stored setting.
     *
     * @since 1.6.0
     *
     * @param  mixed  $raw  The stored setting.
     *
     * @return string|null The delimited pattern, or null when the setting is blank or does not compile.
     */
    public static function normalize( mixed $raw ): ?string
    {
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            return null;
        }

        $pattern = trim( $raw );

        // A delimiter may not be alphanumeric, a backslash or whitespace
        if ( 1 !== preg_match( '/^[a-zA-Z0-9\\\\\s]/', $pattern ) && self::compiles( $pattern ) ) {
            return $pattern;
        }

        $wrapped = '/' . preg_replace( '#(?<!\\\\)/#', '\\/', $pattern ) . '/u';

        return self::compiles( $wrapped ) ? $wrapped : null;
    }

    /**
     * Checks whether a delimited pattern compiles.
     *
     * @since 1.6.0
     *
     * @param  string  $pattern  The pattern.
     *
     * @return bool True when preg_match() accepts it.
     */
    public static function compiles( string $pattern ): bool
    {
        set_error_handler( static fn (): bool => true );

        try {
            return false !== preg_match( $pattern, '' );
        } finally {
            restore_error_handler();
        }
    }
}
