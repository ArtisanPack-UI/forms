/**
 * Number field formats, prefix and suffix.
 *
 * Mirrors `NumberFormat` on the server: with a format, visitors type numbers
 * such as `1,234.56` (`decimal_dot`, `currency`) or `1.234,56`
 * (`decimal_comma`) into a text input, and the server stores the plain
 * decimal.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField, NumberFormat } from '../types/artisanpack-forms';

/** The formats a number field may use, besides the plain browser number input. */
export const NUMBER_FORMATS: readonly NumberFormat[] = ['decimal_dot', 'decimal_comma', 'currency'];

/** Get a number field's format, or undefined for a plain number input. */
export function getNumberFormat( field: FormField ): NumberFormat | undefined {
	const format = field.field_config?.number_format;

	return typeof format === 'string' && ( NUMBER_FORMATS as readonly string[] ).includes( format )
		? format as NumberFormat
		: undefined;
}

/** Whether a number field accepts whole numbers only. */
export function isIntegerOnly( field: FormField ): boolean {
	const value: unknown = field.validation_rules?.integer_only;

	return value === true || value === 1 || value === '1' || value === 'true';
}

/** Get the text a number field shows before and after its input. */
export function getAffixes( field: FormField ): { prefix?: string; suffix?: string } {
	const read = ( key: 'prefix' | 'suffix' ): string | undefined => {
		const value = field.field_config?.[key];

		return typeof value === 'string' && value.trim() !== '' ? value.trim() : undefined;
	};

	return { prefix: read( 'prefix' ), suffix: read( 'suffix' ) };
}

/**
 * Converts a number typed in a format to a plain decimal string.
 *
 * Returns the value unchanged when it does not read as a number in the
 * format, so validation reports it. Mirrors `NumberFormat::normalize()`.
 */
export function normalizeFormattedNumber( value: string, format: NumberFormat ): string {
	const stripped = value.replace( /[^\d.,-]/g, '' );

	if ( stripped === '' ) {
		return value;
	}

	const [thousands, decimal] = format === 'decimal_comma' ? ['.', ','] : [',', '.'];
	const plain = stripped.split( thousands ).join( '' ).split( decimal ).join( '.' );

	if ( !/^-?(\d+(\.\d*)?|\.\d+)$/.test( plain ) ) {
		return value;
	}

	if ( format === 'currency' ) {
		return roundDecimal( plain, 2 );
	}

	return plain.replace( /\.$/, '' );
}

/**
 * Round a plain decimal string to a number of places, half away from zero,
 * on its digits rather than through a float, so `1.005` is `1.01` and
 * `2.675` is `2.68` as with PHP's `number_format()` (`toFixed()` gives
 * `1.00` and `2.67`). A result of zero has no minus sign, as in PHP.
 *
 * @since 1.6.0
 *
 * @param  plain  A plain decimal, such as `-12.345` or `.5`.
 * @param  places  The decimal places to keep.
 */
export function roundDecimal( plain: string, places: number ): string {
	const negative = plain.startsWith( '-' );
	const [whole = '', fraction = ''] = ( negative ? plain.slice( 1 ) : plain ).split( '.' );
	const digits = ( ( whole === '' ? '0' : whole ) + fraction.padEnd( places, '0' ).slice( 0, places ) )
		.split( '' )
		.map( Number );

	if ( Number( fraction.charAt( places ) || '0' ) >= 5 ) {
		let index = digits.length - 1;

		while ( index >= 0 && digits[index] === 9 ) {
			digits[index] = 0;
			index--;
		}

		if ( index < 0 ) {
			digits.unshift( 1 );
		} else {
			digits[index]++;
		}
	}

	const integer = digits.slice( 0, digits.length - places ).join( '' ).replace( /^0+(?=\d)/, '' );
	const decimals = digits.slice( digits.length - places ).join( '' );
	const result = places > 0 ? `${integer}.${decimals}` : integer;

	return negative && /[1-9]/.test( result ) ? `-${result}` : result;
}
