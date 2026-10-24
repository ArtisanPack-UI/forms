<?php

/**
 * Number format support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Reads numbers typed in a number field's format and stores them as plain decimals.
 *
 * - `decimal_dot`: `9,999.99`, commas group thousands.
 * - `decimal_comma`: `9.999,99`, dots group thousands.
 * - `currency`: like `decimal_dot`, rounded to two decimal places.
 *
 * Spaces and any symbol the visitor types (a currency sign, a unit) are
 * ignored. `resources/js/shared/numberFormat.ts` mirrors this for React and
 * Vue.
 *
 * @since 1.6.0
 */
class NumberFormat
{
    /**
     * The formats a number field may use, besides the plain browser number input.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const FORMATS = ['decimal_dot', 'decimal_comma', 'currency'];

    /**
     * Gets the options of the `number_format` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function options(): array
    {
        return [
            ['value' => '', 'label' => __( 'Plain number' )],
            ['value' => 'decimal_dot', 'label' => '9,999.99'],
            ['value' => 'decimal_comma', 'label' => '9.999,99'],
            ['value' => 'currency', 'label' => __( 'Currency (9,999.99)' )],
        ];
    }

    /**
     * Converts a number typed in a format to a plain decimal string.
     *
     * Values that are not a scalar, are blank or do not read as a number in
     * the format are returned unchanged, so the `numeric` rule rejects them.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     * @param  string  $format  The format, one of FORMATS.
     *
     * @return mixed The plain decimal, such as `1234.5`, or the value unchanged.
     */
    public static function normalize( mixed $value, string $format ): mixed
    {
        if ( ! in_array( $format, self::FORMATS, true ) || ! is_scalar( $value ) || is_bool( $value ) ) {
            return $value;
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return 'currency' === $format ? number_format( (float) $value, 2, '.', '' ) : $value;
        }

        $stripped = preg_replace( '/[^\d.,\-]/u', '', $value ) ?? '';

        if ( '' === $stripped ) {
            return $value;
        }

        [$thousands, $decimal] = 'decimal_comma' === $format ? ['.', ','] : [',', '.'];

        $plain = str_replace( [$thousands, $decimal], ['', '.'], $stripped );

        if ( 1 !== preg_match( '/^-?(\d+(\.\d*)?|\.\d+)$/', $plain ) ) {
            return $value;
        }

        if ( 'currency' === $format ) {
            return number_format( (float) $plain, 2, '.', '' );
        }

        return rtrim( $plain, '.' );
    }
}
