/**
 * Color field helpers.
 *
 * Client-side mirror of `ColorFieldType`: a hex color, stored lowercase with
 * a `#` and the short forms expanded (`F80` becomes `#ff8800`), and checked
 * like Laravel's `hex_color` rule.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';
import { t } from './i18n';

/** The hex color forms `hex_color` accepts, mirroring `ColorFieldType::PATTERN`. */
const HEX_COLOR_PATTERN = /^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

/** Whether a field is a color field. */
export function isColorField( field: FormField ): boolean {
	return field.type === 'color';
}

/**
 * Normalize a typed color: trimmed, lowercase, with a `#` and the short
 * forms expanded. A value that is not a hex color is returned trimmed.
 * Mirrors `ColorFieldType::normalizeValue()`.
 */
export function normalizeColor( value: unknown ): string {
	const text = String( value ?? '' ).trim();

	if ( text === '' ) {
		return text;
	}

	const color = text.startsWith( '#' ) ? text : `#${text}`;

	if ( !HEX_COLOR_PATTERN.test( color ) ) {
		return text;
	}

	let digits = color.slice( 1 ).toLowerCase();

	if ( digits.length <= 4 ) {
		digits = [...digits].map( ( digit ) => digit + digit ).join( '' );
	}

	return `#${digits}`;
}

/** Whether a value is a hex color, mirroring `ColorFieldType::isHexColor()`. */
export function isHexColor( value: unknown ): boolean {
	return typeof value === 'string' && HEX_COLOR_PATTERN.test( value );
}

/**
 * Get the `#rrggbb` part of a color for `<input type="color">`, which takes no
 * other form, or `#000000` when the value is not a hex color. Mirrors
 * `ColorFieldType::pickerValue()`.
 */
export function getColorPickerValue( value: unknown ): string {
	const color = normalizeColor( value );

	return isHexColor( color ) ? color.slice( 0, 7 ) : '#000000';
}

/**
 * Validate a color, mirroring `ColorFieldType::typeRules()`: a hex color once
 * normalized.
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateColor( field: FormField, value: unknown ): string[] {
	if ( isHexColor( normalizeColor( value ) ) ) {
		return [];
	}

	return [t( 'The :field must be a hex color, such as #ff8800.', { field: field.label ?? field.name } )];
}
