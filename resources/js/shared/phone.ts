/**
 * Phone field formats and the country selector.
 *
 * The server describes a phone field that checks numbers in `field.phone`
 * (see `PhoneFieldType`). React and Vue keep the selected country beside the
 * input and submit the number with the country's calling code
 * (`+44 20 7946 0958`), so the server reads it in that country.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField, PhoneCountry, PhoneFormat } from '../types/artisanpack-forms';

/** Get how a phone field checks numbers. */
export function getPhoneFormat( field: FormField ): PhoneFormat {
	return field.phone?.format ?? 'any';
}

/** Get the countries a phone field's selector offers; empty when it has none. */
export function getPhoneCountries( field: FormField ): PhoneCountry[] {
	return field.phone?.format === 'international' ? field.phone.countries ?? [] : [];
}

/** Get the country a phone field's selector starts at. */
export function getPhoneDefaultCountry( field: FormField ): string {
	return field.phone?.default_country ?? 'US';
}

/**
 * Find the country a phone field's selector starts at for a value.
 *
 * The default country, unless the value starts with another country's
 * calling code; the default wins when several share a code (such as `+1`).
 */
export function findPhoneCountry( value: string, countries: PhoneCountry[], defaultCode: string ): PhoneCountry | undefined {
	const fallback = countries.find( ( country ) => country.value === defaultCode ) ?? countries[0];

	if ( !value.startsWith( '+' ) || ( fallback && value.startsWith( `+${fallback.dial_code} ` ) ) ) {
		return fallback;
	}

	return countries.find( ( country ) => value.startsWith( `+${country.dial_code} ` ) ) ?? fallback;
}

/**
 * Split a submitted number into the selected country and the number typed.
 *
 * A number that starts with the selected country's calling code (as
 * composePhoneNumber() writes it) has the code removed; any other number is
 * shown as it is.
 */
export function splitPhoneNumber( value: string, country: PhoneCountry | undefined ): string {
	if ( !country ) {
		return value;
	}

	const prefix = `+${country.dial_code} `;

	return value.startsWith( prefix ) ? value.slice( prefix.length ) : value;
}

/**
 * Join the selected country's calling code and the number typed.
 *
 * A number typed with its own `+` country code, or a blank one, is kept as
 * it is.
 */
export function composePhoneNumber( national: string, country: PhoneCountry | undefined ): string {
	const trimmed = national.trim();

	if ( !country || trimmed === '' || trimmed.startsWith( '+' ) ) {
		return national;
	}

	return `+${country.dial_code} ${national}`;
}

/** Count the digits in a number. */
function countDigits( value: string ): number {
	return value.replace( /\D/g, '' ).length;
}

/**
 * Roughly check a number before it is sent; the server checks it fully.
 *
 * US numbers need 10 digits (11 with a leading 1); international numbers
 * between 8 and 15 digits with their country code (E.164).
 */
export function isPlausiblePhoneNumber( value: string, format: PhoneFormat ): boolean {
	const digits = countDigits( value );

	if ( format === 'us' ) {
		return digits === 10 || ( digits === 11 && value.replace( /\D/g, '' ).startsWith( '1' ) );
	}

	if ( format === 'international' ) {
		return digits >= 8 && digits <= 15;
	}

	return true;
}
