<?php

/**
 * URL scheme support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Reads a URL field's allowed schemes and adds a missing scheme to typed addresses.
 *
 * Visitors often type `example.com`, which the `url` rule rejects. Before
 * validation, a value without a scheme gets `https://` (or `http://` when
 * only `http` is allowed); a protocol-relative `//example.com` gets the
 * scheme alone. `localhost:8080` reads as a host and port, not a scheme.
 * `resources/js/shared/url.ts` mirrors this for React and Vue.
 *
 * @since 1.6.0
 */
class UrlSchemes
{
    /**
     * The schemes a URL field allows when it sets none.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const DEFAULT = ['http', 'https'];

    /**
     * The schemes a URL field may never allow.
     *
     * A link with one of these runs script or reads local data when an admin
     * opens it (for example `javascript:` in the submission detail), so they
     * are dropped from a field's allowed schemes.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const BLOCKED = ['javascript', 'data', 'vbscript', 'file', 'blob'];

    /**
     * Normalizes a stored scheme list: lowercase schemes without `:` or `://`, invalid entries removed.
     *
     * The schemes in BLOCKED are removed too.
     *
     * @since 1.6.0
     *
     * @param  mixed  $schemes  The stored list, or a comma-separated string.
     *
     * @return list<string> The schemes, or DEFAULT when none is valid.
     */
    public static function normalizeList( mixed $schemes ): array
    {
        if ( is_string( $schemes ) ) {
            $schemes = explode( ',', $schemes );
        }

        $normalized = [];

        foreach ( is_array( $schemes ) ? $schemes : [] as $scheme ) {
            if ( ! is_string( $scheme ) ) {
                continue;
            }

            $scheme = mb_strtolower( preg_replace( '/:(\/\/)?$/', '', trim( $scheme ) ) ?? '' );

            if ( 1 === preg_match( '/^[a-z][a-z0-9+.\-]*$/', $scheme ) && ! in_array( $scheme, self::BLOCKED, true ) && ! in_array( $scheme, $normalized, true ) ) {
                $normalized[] = $scheme;
            }
        }

        return [] === $normalized ? self::DEFAULT : $normalized;
    }

    /**
     * Checks whether a value starts with a scheme, such as `https:` or `mailto:`.
     *
     * A colon followed by a digit is a port (`localhost:8080`), not a scheme.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The trimmed value.
     *
     * @return bool True when the value has a scheme.
     */
    public static function hasScheme( string $value ): bool
    {
        return 1 === preg_match( '/^[a-z][a-z0-9+.\-]*:(?!\d)/i', $value );
    }

    /**
     * Adds a scheme to a typed address that has none.
     *
     * Values that are not strings, are blank or already have a scheme are
     * returned unchanged (apart from surrounding whitespace), as are values
     * when neither `https` nor `http` is allowed.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     * @param  list<string>  $allowed  The field's allowed schemes.
     *
     * @return mixed The value with a scheme.
     */
    public static function withScheme( mixed $value, array $allowed = self::DEFAULT ): mixed
    {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        $trimmed = trim( $value );
        $scheme  = in_array( 'https', $allowed, true ) ? 'https' : ( in_array( 'http', $allowed, true ) ? 'http' : null );

        if ( '' === $trimmed || null === $scheme || self::hasScheme( $trimmed ) ) {
            return '' === $trimmed ? $value : $trimmed;
        }

        return str_starts_with( $trimmed, '//' ) ? "{$scheme}:{$trimmed}" : "{$scheme}://{$trimmed}";
    }
}
