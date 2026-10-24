/**
 * Input masks for text and phone fields.
 *
 * Mirrors Alpine's mask plugin, which the Livewire renderer uses, and
 * `InputMask` on the server: `9` is a digit, `a` a letter and `*` a letter
 * or digit; every other character is a literal typed for the visitor. The
 * server resolves a field's preset or custom mask and its hint into
 * `field.input_mask`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

/** The pattern each mask token accepts. */
const TOKENS: Readonly<Record<string, RegExp>> = {
	'9': /[0-9]/,
	a: /[a-zA-Z]/,
	'*': /[a-zA-Z0-9]/,
};

/** Get a field's input mask, or undefined when it has none. */
export function getInputMask( field: FormField ): string | undefined {
	return field.input_mask?.mask || undefined;
}

/** Get the hint that tells visitors the format a field's mask expects. */
export function getInputMaskHint( field: FormField ): string | undefined {
	return field.input_mask?.hint || undefined;
}

/**
 * Keeps the characters of a typed value that fill the mask's tokens, in order.
 *
 * Literals the visitor typed (or the mask added earlier) are dropped first,
 * as Alpine's `stripDown()` does.
 */
function stripDown( mask: string, input: string ): string {
	const remaining = Array.from( input );
	const tokens: string[] = [];
	let output = '';

	for ( const character of mask ) {
		if ( character in TOKENS ) {
			tokens.push( character );

			continue;
		}

		const index = remaining.indexOf( character );

		if ( index >= 0 ) {
			remaining.splice( index, 1 );
		}
	}

	for ( const token of tokens ) {
		const index = remaining.findIndex( ( character ) => TOKENS[token].test( character ) );

		if ( index < 0 ) {
			break;
		}

		output += remaining.splice( index, 1 )[0];
	}

	return output;
}

/** Fills the mask's tokens with the kept characters, adding its literals. */
function buildUp( mask: string, input: string ): string {
	const characters = Array.from( input );
	let output = '';

	for ( const character of mask ) {
		if ( !( character in TOKENS ) ) {
			output += character;

			continue;
		}

		if ( characters.length === 0 ) {
			break;
		}

		output += characters.shift();
	}

	return output;
}

/**
 * Formats a typed value with a mask.
 *
 * When the visitor deleted a single character, the value is left as typed,
 * so backspacing over a literal (such as the space in `(555) `) works; Alpine
 * does the same.
 *
 * @param value    The value as typed.
 * @param mask     The mask.
 * @param previous The value before this change, if known.
 */
export function applyMask( value: string, mask: string, previous?: string ): string {
	if ( previous !== undefined && value.length === previous.length - 1 ) {
		return value;
	}

	if ( value === '' ) {
		return '';
	}

	return buildUp( mask, stripDown( mask, value ) );
}

/** Build the expression a complete masked value matches. Mirrors `InputMask::toRegex()`. */
export function maskToRegExp( mask: string ): RegExp {
	const pattern = Array.from( mask )
		.map( ( character ) => {
			if ( character === '9' ) {
				return '\\d';
			}

			if ( character === 'a' ) {
				return '[a-zA-Z]';
			}

			if ( character === '*' ) {
				return '[a-zA-Z0-9]';
			}

			return character.replace( /[.*+?^${}()|[\]\\/-]/g, '\\$&' );
		} )
		.join( '' );

	return new RegExp( `^${pattern}$`, 'u' );
}
