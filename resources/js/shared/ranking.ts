/**
 * Ranking field helpers.
 *
 * Client-side mirror of `RankingFieldType`. The choices are the field's
 * options; the value lists every option value, most preferred first,
 * submitted as `data[{field}][]`. The server sends the order the list starts
 * in (shuffled when the field randomizes it) as `resolved_default_value`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FieldOption, FormField } from '../types/artisanpack-forms';
import { t } from './i18n';

/** Whether a field is a ranking. */
export function isRankingField( field: FormField ): boolean {
	return field.type === 'ranking';
}

/** Get a ranking's choices, each once, in the options' order. */
export function getRankingChoices( field: FormField ): FieldOption[] {
	const seen = new Set<string>();
	const choices: FieldOption[] = [];

	for ( const option of field.options ?? [] ) {
		const value = String( option.value ?? '' );

		if ( value !== '' && !seen.has( value ) ) {
			seen.add( value );
			choices.push( { ...option, value, label: option.label ?? value } );
		}
	}

	return choices;
}

/**
 * Read a ranking's order, fitted to its choices: the values that are
 * choices, in order and once each, then any choice missing from the end.
 * Mirrors `RankingFieldType::order()`.
 */
export function getRankingOrder( field: FormField, value: unknown ): string[] {
	const choices = getRankingChoices( field ).map( ( choice ) => choice.value );
	const order: string[] = [];

	for ( const item of Array.isArray( value ) ? value : [] ) {
		const choice = String( item );

		if ( choices.includes( choice ) && !order.includes( choice ) ) {
			order.push( choice );
		}
	}

	return [...order, ...choices.filter( ( choice ) => !order.includes( choice ) )];
}

/**
 * Move one choice to a new position, mirroring `RankingFieldType::move()`.
 *
 * @returns The new order; unchanged when either position is out of range.
 */
export function moveRankingItem( order: string[], from: number, to: number ): string[] {
	if ( from === to || from < 0 || to < 0 || from >= order.length || to >= order.length ) {
		return order;
	}

	const next = [...order];
	const [moved] = next.splice( from, 1 );

	next.splice( to, 0, moved );

	return next;
}

/**
 * Validate a ranking, mirroring `RankingFieldType::rules()`: every choice,
 * each once.
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateRanking( field: FormField, value: unknown ): string[] {
	const choices = getRankingChoices( field ).map( ( choice ) => choice.value );
	const items = Array.isArray( value ) ? value.map( String ) : [];
	const isComplete = items.length === choices.length
		&& new Set( items ).size === items.length
		&& items.every( ( item ) => choices.includes( item ) );

	return isComplete ? [] : [t( 'Please put every choice of :field in order.', { field: field.label ?? field.name } )];
}
