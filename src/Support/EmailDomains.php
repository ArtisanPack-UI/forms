<?php

/**
 * Email domain list support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Reads an email field's allowed and blocked domain lists and checks addresses against them.
 *
 * A listed domain matches the address's domain and its subdomains, so
 * `example.com` covers `mail.example.com` too. Entries may start with `@`
 * and are compared case-insensitively. `resources/js/shared/emailDomains.ts`
 * mirrors this for React and Vue.
 *
 * @since 1.6.0
 */
class EmailDomains
{
    /**
     * The strictness levels an email field may validate addresses with, and the `email` rule each one adds.
     *
     * - `rfc`: RFC 5322 syntax (Laravel's default).
     * - `dns`: RFC syntax and a domain with MX or A records.
     * - `strict`: RFC syntax without the warnings RFC tolerates.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public const VALIDATION_RULES = [
        'rfc'    => 'email',
        'dns'    => 'email:rfc,dns',
        'strict' => 'email:strict',
    ];

    /**
     * Gets the options of the `email_validation` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function validationOptions(): array
    {
        return [
            ['value' => 'rfc', 'label' => __( 'Standard (RFC)' )],
            ['value' => 'dns', 'label' => __( 'Standard and the domain must exist (DNS)' )],
            ['value' => 'strict', 'label' => __( 'Strict (no RFC warnings)' )],
        ];
    }

    /**
     * Normalizes a stored domain list: lowercase domains without a leading `@`, blanks removed.
     *
     * @since 1.6.0
     *
     * @param  mixed  $domains  The stored list, or a comma-separated string.
     *
     * @return list<string> The domains.
     */
    public static function normalizeList( mixed $domains ): array
    {
        if ( is_string( $domains ) ) {
            $domains = explode( ',', $domains );
        }

        if ( ! is_array( $domains ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $domains as $domain ) {
            if ( ! is_string( $domain ) ) {
                continue;
            }

            $domain = trim( mb_strtolower( ltrim( trim( $domain ), '@' ) ), '. ' );

            if ( '' !== $domain && ! str_contains( $domain, ',' ) && ! in_array( $domain, $normalized, true ) ) {
                $normalized[] = $domain;
            }
        }

        return $normalized;
    }

    /**
     * Gets the domain part of an email address, lowercased.
     *
     * @since 1.6.0
     *
     * @param  string  $email  The address.
     *
     * @return string|null The domain, or null when the address has none.
     */
    public static function domainOf( string $email ): ?string
    {
        $at = strrpos( $email, '@' );

        if ( false === $at ) {
            return null;
        }

        $domain = trim( mb_strtolower( substr( $email, $at + 1 ) ), '. ' );

        return '' === $domain ? null : $domain;
    }

    /**
     * Checks whether a domain is one of the listed domains or a subdomain of one.
     *
     * @since 1.6.0
     *
     * @param  string  $domain  The address's domain, lowercased.
     * @param  list<string>  $domains  The normalized list.
     *
     * @return bool True on a match.
     */
    public static function matches( string $domain, array $domains ): bool
    {
        foreach ( $domains as $listed ) {
            if ( $domain === $listed || str_ends_with( $domain, '.' . $listed ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks an address against an allow or block list, for the `forms_email_domain` rule.
     *
     * Values that are not strings or have no domain pass, leaving them to the `email` rule.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted address.
     * @param  array<int, string>  $parameters  `allow` or `block`, then the domains.
     *
     * @return bool True when the address's domain is allowed.
     */
    public static function passes( mixed $value, array $parameters ): bool
    {
        $mode    = array_shift( $parameters );
        $domains = self::normalizeList( $parameters );

        if ( ! is_string( $value ) || [] === $domains || ! in_array( $mode, ['allow', 'block'], true ) ) {
            return true;
        }

        $domain = self::domainOf( trim( $value ) );

        if ( null === $domain ) {
            return true;
        }

        return 'allow' === $mode ? self::matches( $domain, $domains ) : ! self::matches( $domain, $domains );
    }
}
