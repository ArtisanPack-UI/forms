<?php

/**
 * Autocomplete token support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * The `autocomplete` values a field may expose (WCAG 2.2 SC 1.3.5).
 *
 * The autofill field names of the WHATWG HTML spec, plus `on` and `off`.
 * `resources/js/shared/fieldSettings.ts` mirrors the list.
 *
 * @since 1.6.0
 */
class Autocomplete
{
    /**
     * The accepted tokens, in the order the spec lists them.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const TOKENS = [
        'off',
        'on',
        'name',
        'honorific-prefix',
        'given-name',
        'additional-name',
        'family-name',
        'honorific-suffix',
        'nickname',
        'username',
        'new-password',
        'current-password',
        'one-time-code',
        'organization-title',
        'organization',
        'street-address',
        'address-line1',
        'address-line2',
        'address-line3',
        'address-level4',
        'address-level3',
        'address-level2',
        'address-level1',
        'country',
        'country-name',
        'postal-code',
        'cc-name',
        'cc-given-name',
        'cc-additional-name',
        'cc-family-name',
        'cc-number',
        'cc-exp',
        'cc-exp-month',
        'cc-exp-year',
        'cc-csc',
        'cc-type',
        'transaction-currency',
        'transaction-amount',
        'language',
        'bday',
        'bday-day',
        'bday-month',
        'bday-year',
        'sex',
        'url',
        'photo',
        'tel',
        'tel-country-code',
        'tel-national',
        'tel-area-code',
        'tel-local',
        'tel-local-prefix',
        'tel-local-suffix',
        'tel-extension',
        'email',
        'impp',
    ];

    /**
     * Checks whether a value is an accepted token.
     *
     * @since 1.6.0
     *
     * @param  mixed  $token  The value.
     *
     * @return bool True for an accepted token.
     */
    public static function isToken( mixed $token ): bool
    {
        return is_string( $token ) && in_array( $token, self::TOKENS, true );
    }

    /**
     * Gets the builder setting that overrides a type's autocomplete token.
     *
     * The first option keeps the type's default, so an unset setting shows it.
     *
     * @since 1.6.0
     *
     * @param  string|null  $default  The type's default token, or null for none.
     *
     * @return array<string, mixed> The setting.
     */
    public static function setting( ?string $default ): array
    {
        $options = [[
            'value' => '',
            'label' => null === $default
                ? __( 'Default (none)' )
                : __( 'Default (:token)', ['token' => $default] ),
        ]];

        foreach ( self::TOKENS as $token ) {
            $options[] = ['value' => $token, 'label' => $token];
        }

        return [
            'key'     => 'autocomplete',
            'input'   => 'select',
            'tab'     => 'advanced',
            'label'   => __( 'Autocomplete' ),
            'hint'    => __( 'Tells browsers and assistive technology what the field collects.' ),
            'options' => $options,
        ];
    }
}
