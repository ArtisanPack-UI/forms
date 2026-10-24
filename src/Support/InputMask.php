<?php

/**
 * Input mask support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Resolves a field's input mask and derives its server-side check and hint.
 *
 * A mask uses the tokens of Alpine's mask plugin, which the Livewire renderer
 * uses and `resources/js/shared/mask.ts` mirrors for React and Vue: `9` is a
 * digit, `a` a letter and `*` a letter or digit. Every other character is a
 * literal the mask types for the visitor, so a complete value matches the
 * mask exactly and is stored with its literals.
 *
 * @since 1.6.0
 */
class InputMask
{
    /**
     * The masks of the presets, keyed by preset.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public const PRESETS = [
        'us_phone'  => '(999) 999-9999',
        'zip'       => '99999',
        'zip_plus4' => '99999-9999',
        'ssn'       => '999-99-9999',
        'date'      => '99/99/9999',
    ];

    /**
     * The value of the `input_mask` setting that uses the `custom_mask` pattern.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const CUSTOM = 'custom';

    /**
     * The longest custom mask a field may set.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_LENGTH = 100;

    /**
     * The characters that stand for a typed character.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    protected const TOKENS = [
        '9' => '\d',
        'a' => '[a-zA-Z]',
        '*' => '[a-zA-Z0-9]',
    ];

    /**
     * Gets the options of the `input_mask` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function options(): array
    {
        return [
            ['value' => '', 'label' => __( 'None' )],
            ['value' => 'us_phone', 'label' => __( 'US Phone — (999) 999-9999' )],
            ['value' => 'zip', 'label' => __( 'US ZIP Code — 99999' )],
            ['value' => 'zip_plus4', 'label' => __( 'US ZIP+4 — 99999-9999' )],
            ['value' => 'ssn', 'label' => __( 'Social Security Number — 999-99-9999' )],
            ['value' => 'date', 'label' => __( 'Date — 99/99/9999' )],
            ['value' => self::CUSTOM, 'label' => __( 'Custom' )],
        ];
    }

    /**
     * Resolves the mask a preset or custom pattern stands for.
     *
     * @since 1.6.0
     *
     * @param  mixed  $preset  The `input_mask` setting.
     * @param  mixed  $custom  The `custom_mask` setting.
     *
     * @return string|null The mask, or null when there is none or the custom pattern has no tokens.
     */
    public static function resolve( mixed $preset, mixed $custom ): ?string
    {
        if ( ! is_string( $preset ) || '' === $preset ) {
            return null;
        }

        if ( self::CUSTOM !== $preset ) {
            return self::PRESETS[ $preset ] ?? null;
        }

        return is_string( $custom ) && self::isValid( $custom ) ? $custom : null;
    }

    /**
     * Checks that a custom mask is usable: not too long and with at least one token.
     *
     * @since 1.6.0
     *
     * @param  string  $mask  The mask.
     *
     * @return bool True when usable.
     */
    public static function isValid( string $mask ): bool
    {
        if ( '' === $mask || mb_strlen( $mask ) > self::MAX_LENGTH ) {
            return false;
        }

        return false !== strpbrk( $mask, implode( '', array_keys( self::TOKENS ) ) );
    }

    /**
     * Builds the regular expression a complete masked value matches.
     *
     * @since 1.6.0
     *
     * @param  string  $mask  The mask.
     *
     * @return string The `regex:` pattern, with delimiters.
     */
    public static function toRegex( string $mask ): string
    {
        $pattern = '';

        foreach ( mb_str_split( $mask ) as $character ) {
            $pattern .= self::TOKENS[ $character ] ?? preg_quote( $character, '/' );
        }

        return '/^' . $pattern . '$/u';
    }

    /**
     * Gets the hint that tells visitors the format a mask expects.
     *
     * Presets show an example value; a custom mask shows its pattern with
     * `#` for a digit, `A` for a letter and `X` for either, and a key for the
     * symbols it uses.
     *
     * @since 1.6.0
     *
     * @param  string  $mask  The mask.
     *
     * @return string The hint.
     */
    public static function hint( string $mask ): string
    {
        $examples = [
            self::PRESETS['us_phone']  => '(555) 555-0123',
            self::PRESETS['zip']       => '12345',
            self::PRESETS['zip_plus4'] => '12345-6789',
            self::PRESETS['ssn']       => '123-45-6789',
            self::PRESETS['date']      => __( 'MM/DD/YYYY' ),
        ];

        if ( isset( $examples[ $mask ] ) ) {
            return __( 'Format: :format', ['format' => $examples[ $mask ]] );
        }

        $format = strtr( $mask, ['9' => '#', 'a' => 'A', '*' => 'X'] );
        $key    = array_filter( [
            str_contains( $mask, '9' ) ? __( '# is a digit' ) : null,
            str_contains( $mask, 'a' ) ? __( 'A is a letter' ) : null,
            str_contains( $mask, '*' ) ? __( 'X is a letter or digit' ) : null,
        ] );

        return __( 'Format: :format (:key)', ['format' => $format, 'key' => implode( ', ', $key )] );
    }
}
