<?php

/**
 * Color field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

/**
 * A hex color, picked with the browser's color picker or typed as a hex code.
 *
 * Values are checked with Laravel's `hex_color` rule (`#rgb`, `#rgba`,
 * `#rrggbb` or `#rrggbbaa`) and stored lowercase with the short forms
 * expanded, such as `#ff8800` for `FF8800` or `#f80`. Submissions show a
 * swatch beside the hex code. `resources/js/shared/color.ts` mirrors the
 * normalization for React and Vue.
 *
 * @since 1.6.0
 */
class ColorFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'color';

    /**
     * The hex color forms `hex_color` accepts.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const PATTERN = '/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'color';

    /**
     * Normalizes a typed color: trimmed, lowercase, with a `#` and the short forms expanded.
     *
     * Runs before validation, so `FF8800` and `#F80` pass as `#ff8800`. A
     * value that is not a hex color is returned trimmed, for the
     * `hex_color` rule to reject.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     *
     * @return mixed The normalized value.
     */
    public static function normalizeValue( mixed $value ): mixed
    {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        $value = trim( $value );

        if ( '' === $value ) {
            return $value;
        }

        $color = '#' === $value[0] ? $value : '#' . $value;

        if ( 1 !== preg_match( self::PATTERN, $color ) ) {
            return $value;
        }

        $digits = strtolower( substr( $color, 1 ) );

        if ( strlen( $digits ) <= 4 ) {
            $digits = implode( '', array_map( fn ( string $digit ): string => $digit . $digit, str_split( $digits ) ) );
        }

        return '#' . $digits;
    }

    /**
     * Checks whether a value is a hex color.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The value.
     *
     * @return bool True for a `#rgb`, `#rgba`, `#rrggbb` or `#rrggbbaa` string.
     */
    public static function isHexColor( mixed $value ): bool
    {
        return is_string( $value ) && 1 === preg_match( self::PATTERN, $value );
    }

    /**
     * Gets the `#rrggbb` part of a color, for `<input type="color">`, which takes no other form.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The value.
     *
     * @return string The color without its alpha, or `#000000` when the value is not a hex color.
     */
    public static function pickerValue( mixed $value ): string
    {
        $color = self::normalizeValue( $value );

        return self::isHexColor( $color ) ? substr( $color, 0, 7 ) : '#000000';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return self::normalizeValue( $value );
    }

    /**
     * {@inheritDoc}
     *
     * A swatch before the hex code. The swatch is decorative, so the hex code
     * is what screen readers and plain-text views get.
     *
     * @since 1.6.0
     */
    public function displayHtml( FormSubmissionValue $value ): ?string
    {
        $color = self::normalizeValue( $value->value ?? '' );

        if ( ! self::isHexColor( $color ) ) {
            return null;
        }

        return '<span style="display: inline-flex; align-items: center; gap: 0.5em;">'
            . '<span aria-hidden="true" style="display: inline-block; width: 1.25em; height: 1.25em; border-radius: 0.25em; border: 1px solid rgba(0, 0, 0, 0.2); background-color: ' . e( $color ) . ';"></span>'
            . '<code>' . e( $color ) . '</code>'
            . '</span>';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['color'], [
            'label'    => __( 'Color' ),
            'defaults' => [
                'label' => __( 'Color' ),
            ],
            'settings' => [
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['string', 'hex_color'];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return [
            'hex_color' => __( 'The :field must be a hex color, such as #ff8800.', ['field' => $field->label] ),
        ];
    }
}
