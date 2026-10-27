/**
 * Character and word counters for text and textarea fields.
 *
 * Mirrors the Livewire `counter` partial. Words are runs of characters
 * between whitespace, as the server's `forms_max_words` rule counts them.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

import { t } from './i18n';

/** How long typing must pause before the counter is announced, in milliseconds. */
export const COUNTER_ANNOUNCE_DELAY = 1000;

/** What a field's counter shows. */
export interface CounterState {
	count: number;
	limit: number | undefined;
	words: boolean;
	text: string;
	overLimit: boolean;
}

/** Read a positive whole-number rule, or undefined. */
function positiveRule( value: unknown ): number | undefined {
	const number = Number( value );

	return value !== null && value !== undefined && value !== '' && Number.isInteger( number ) && number >= 0 ? number : undefined;
}

/** Whether a text or textarea field shows a live counter. */
export function showsCounter( field: FormField ): boolean {
	const value = field.field_config?.show_counter;

	return ( field.type === 'text' || field.type === 'textarea' ) && ( value === true || value === 1 || value === '1' || value === 'true' );
}

/** Get a textarea's word limit, or undefined. */
export function getMaxWords( field: FormField ): number | undefined {
	const limit = field.type === 'textarea' ? positiveRule( field.validation_rules?.max_words ) : undefined;

	return limit !== undefined && limit > 0 ? limit : undefined;
}

/** Get a text or textarea field's length limit, for `minlength` and `maxlength`. */
export function getLengthLimit( field: FormField, key: 'min' | 'max' ): number | undefined {
	return field.type === 'text' || field.type === 'textarea' ? positiveRule( field.validation_rules?.[key] ) : undefined;
}

/** Count the words in a text. */
export function countWords( text: string ): number {
	const trimmed = text.trim();

	return trimmed === '' ? 0 : trimmed.split( /\s+/u ).length;
}

/** Work out what a field's counter shows for a value. */
export function getCounterState( field: FormField, value: string ): CounterState {
	const maxWords = getMaxWords( field );
	const words = maxWords !== undefined;
	const limit = maxWords ?? getLengthLimit( field, 'max' );
	const count = words ? countWords( value ) : Array.from( value ).length;

	let text: string;

	if ( words ) {
		text = t( ':count of :max words', { count, max: limit ?? '' } );
	} else if ( limit !== undefined ) {
		text = t( ':count of :max characters', { count, max: limit } );
	} else {
		text = t( ':count characters', { count } );
	}

	return { count, limit, words, text, overLimit: limit !== undefined && count > limit };
}
