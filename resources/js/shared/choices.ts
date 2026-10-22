/**
 * Choice field helpers.
 *
 * Client-side mirror of the choice settings on `FormField` (layout,
 * randomized order, "Select All", search, per-option defaults) and of the
 * bulk choice syntax in `PredefinedChoices`, so the React and Vue renderers
 * and builders behave like the Livewire ones.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { ChoiceLayout, FieldOption, FormField, OptionsFieldConfig } from '../types/artisanpack-forms';

/** Choice layouts, mirroring `FormField::CHOICE_LAYOUTS`. */
export const CHOICE_LAYOUTS: readonly ChoiceLayout[] = ['stacked', 'inline', '2-col', '3-col'];

/** The most choices one bulk edit may hold, mirroring `PredefinedChoices::MAX_CHOICES`. */
export const MAX_BULK_CHOICES = 1000;

/** The separator between a choice's value and label, mirroring `PredefinedChoices::SEPARATOR`. */
export const BULK_SEPARATOR = '|';

/** Read a `field_config` entry. */
function readConfig( field: FormField, key: string ): unknown {
	return ( field.field_config as Record<string, unknown> | null | undefined )?.[key];
}

/** Whether a stored setting counts as on, mirroring PHP's `FILTER_VALIDATE_BOOLEAN`. */
function isOn( value: unknown ): boolean {
	return value === true || ['1', 'true', 'on', 'yes'].includes( String( value ?? '' ).trim().toLowerCase() );
}

/** Whether a field's value is a list of option values. */
export function isMultipleChoiceField( field: FormField ): boolean {
	return field.type === 'checkbox_group' || field.type === 'select_multiple';
}

/**
 * Get a choice field's options: the resolved choices of its source, or the
 * stored list. Mirrors `FormField::$options`.
 */
export function getFieldOptions( field: FormField ): FieldOption[] {
	return field.options ?? ( field.field_config as OptionsFieldConfig | null )?.options ?? [];
}

/** Get how a radio or checkbox group arranges its choices, mirroring `FormField::getChoiceLayout()`. */
export function getChoiceLayout( field: FormField ): ChoiceLayout {
	const layout = readConfig( field, 'layout' );

	return ( field.type === 'radio' || field.type === 'checkbox_group' ) && CHOICE_LAYOUTS.includes( layout as ChoiceLayout )
		? layout as ChoiceLayout
		: 'stacked';
}

/** Get the classes that arrange a group's choices, mirroring `FormField::getChoiceLayoutClasses()`. */
export function getChoiceLayoutClasses( field: FormField ): string {
	switch ( getChoiceLayout( field ) ) {
		case 'inline':
			return 'flex flex-wrap gap-x-6 gap-y-2';
		case '2-col':
			return 'grid grid-cols-1 gap-2 sm:grid-cols-2';
		case '3-col':
			return 'grid grid-cols-1 gap-2 sm:grid-cols-3';
		default:
			return 'flex flex-col gap-2';
	}
}

/** Whether a radio or checkbox group shows its choices in a random order. */
export function randomizesChoices( field: FormField ): boolean {
	return ( field.type === 'radio' || field.type === 'checkbox_group' ) && isOn( readConfig( field, 'randomize' ) );
}

/** Whether a checkbox group offers a "Select All" checkbox. */
export function offersSelectAll( field: FormField ): boolean {
	return field.type === 'checkbox_group' && isOn( readConfig( field, 'select_all' ) );
}

/** Whether a select or multi-select is rendered as a searchable combobox. */
export function isSearchableField( field: FormField ): boolean {
	return ( field.type === 'select' || field.type === 'select_multiple' ) && isOn( readConfig( field, 'searchable' ) );
}

/** Get the choice source a field's options are loaded from, if any. */
export function getChoicesSource( field: FormField ): string | null {
	const source = readConfig( field, 'choices_source' );

	return typeof source === 'string' && source !== '' ? source : null;
}

/**
 * Get the values of the options marked as defaults; a single-choice field
 * takes the first only. Mirrors `FormField::getDefaultOptionValues()`.
 */
export function getDefaultOptionValues( field: FormField ): string[] {
	const values = Array.from( new Set(
		getFieldOptions( field ).filter( ( option ) => isOn( option.default ) ).map( ( option ) => String( option.value ) ),
	) );

	return isMultipleChoiceField( field ) ? values : values.slice( 0, 1 );
}

/** A small seeded PRNG (mulberry32), so a shuffle is repeatable for its seed. */
function seededRandom( seed: number ): () => number {
	let state = seed >>> 0;

	return () => {
		state = ( state + 0x6D2B79F5 ) >>> 0;
		let t = state;
		t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
		t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );

		return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
	};
}

/** Shuffle a list the same way for the same seed (Fisher–Yates). */
export function shuffleWithSeed<T>( items: readonly T[], seed: number ): T[] {
	const random = seededRandom( seed );
	const shuffled = [...items];

	for ( let i = shuffled.length - 1; i > 0; i-- ) {
		const j = Math.floor( random() * ( i + 1 ) );
		[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
	}

	return shuffled;
}

/**
 * Seeds by field definition. The renderers keep the field objects they
 * fetched for as long as the form is shown, so a field keeps its order across
 * re-renders and step changes, and gets a new one when the form is fetched again.
 */
const choiceSeeds = new WeakMap<object, number>();

/** Get the seed a field's choices are shuffled with, creating it on first use. */
function getChoiceSeed( field: FormField ): number {
	let seed = choiceSeeds.get( field );

	if ( seed === undefined ) {
		seed = Math.floor( Math.random() * 2147483647 ) + 1;
		choiceSeeds.set( field, seed );
	}

	return seed;
}

/**
 * Get a field's options in the order the renderer shows them: shuffled once
 * per field when it randomizes its choices. Mirrors `FormField::getRenderOptions()`;
 * pass the raw field object (Vue: `toRaw()`), since the seed is kept per object.
 */
export function getRenderOptions( field: FormField ): FieldOption[] {
	const options = getFieldOptions( field );

	return randomizesChoices( field ) && options.length > 1 ? shuffleWithSeed( options, getChoiceSeed( field ) ) : options;
}

/**
 * Filter options by label for a combobox query, ignoring case. A blank
 * query keeps every option.
 */
export function filterChoices( options: FieldOption[], query: string ): FieldOption[] {
	const normalized = query.trim().toLowerCase();

	return normalized === '' ? options : options.filter( ( option ) => String( option.label ).toLowerCase().includes( normalized ) );
}

/**
 * Normalize choices to value/label pairs, keeping a `default` mark. Mirrors
 * `PredefinedChoices::normalizeChoices()` for the list shapes the builders use.
 */
export function normalizeChoices( choices: unknown ): FieldOption[] {
	if ( !Array.isArray( choices ) ) {
		return [];
	}

	const normalized: FieldOption[] = [];

	for ( const choice of choices ) {
		if ( choice && typeof choice === 'object' ) {
			const { value, label, default: isDefault } = choice as Record<string, unknown>;

			if ( ( typeof value !== 'string' && typeof value !== 'number' ) || String( value ) === '' ) {
				continue;
			}

			const text = typeof label === 'string' || typeof label === 'number' ? String( label ).trim() : '';
			const option: FieldOption = { value: String( value ), label: text !== '' ? String( label ) : String( value ) };

			if ( isDefault === true ) {
				option.default = true;
			}

			normalized.push( option );
		} else if ( ( typeof choice === 'string' || typeof choice === 'number' ) && String( choice ) !== '' ) {
			normalized.push( { value: String( choice ), label: String( choice ) } );
		}
	}

	return normalized;
}

/**
 * Parse bulk-edited choices, one per line: `value|label`, or a label that
 * doubles as the value. Mirrors `PredefinedChoices::parseBulk()`.
 */
export function parseBulkChoices( text: string ): FieldOption[] {
	const choices: FieldOption[] = [];
	const seen = new Set<string>();

	for ( const rawLine of text.split( /\r\n|\r|\n/ ) ) {
		const line = rawLine.trim();

		if ( line === '' ) {
			continue;
		}

		let value = line;
		let label = line;
		const separator = line.indexOf( BULK_SEPARATOR );

		if ( separator !== -1 ) {
			value = line.slice( 0, separator ).trim();
			label = line.slice( separator + 1 ).trim();
			value = value === '' ? label : value;
			label = label === '' ? value : label;
		}

		if ( value === '' || seen.has( value ) ) {
			continue;
		}

		seen.add( value );
		choices.push( { value, label } );

		if ( choices.length >= MAX_BULK_CHOICES ) {
			break;
		}
	}

	return choices;
}

/** Write choices in the bulk syntax, mirroring `PredefinedChoices::toBulk()`. */
export function toBulkChoices( choices: unknown ): string {
	return normalizeChoices( choices )
		.map( ( choice ) => ( choice.value === choice.label ? choice.label : `${choice.value}${BULK_SEPARATOR}${choice.label}` ) )
		.join( '\n' );
}

/**
 * Apply bulk-edited choices to a field's options: `replace` keeps the default
 * mark of values that stay, `append` adds new values after the existing ones.
 * Mirrors `PredefinedChoices::apply()`.
 */
export function applyBulkChoices( existing: unknown, choices: FieldOption[], mode: 'replace' | 'append' = 'replace' ): FieldOption[] {
	const current = normalizeChoices( existing );

	if ( mode === 'append' ) {
		const seen = new Set( current.map( ( option ) => option.value ) );
		const merged = [...current];

		for ( const choice of normalizeChoices( choices ) ) {
			if ( !seen.has( choice.value ) ) {
				seen.add( choice.value );
				merged.push( choice );
			}
		}

		return merged.slice( 0, MAX_BULK_CHOICES );
	}

	const defaults = new Set( current.filter( ( option ) => option.default ).map( ( option ) => option.value ) );

	return normalizeChoices( choices )
		.map( ( choice ) => ( defaults.has( choice.value ) ? { ...choice, default: true } : choice ) )
		.slice( 0, MAX_BULK_CHOICES );
}

/**
 * Mark or unmark an option as a default. A single-choice field keeps at
 * most one default.
 */
export function setOptionDefault( options: FieldOption[], index: number, isDefault: boolean, multiple: boolean ): FieldOption[] {
	return options.map( ( option, i ) => {
		const next = { ...option };

		if ( i === index ) {
			next.default = isDefault;
		} else if ( isDefault && !multiple ) {
			next.default = false;
		}

		if ( !next.default ) {
			delete next.default;
		}

		return next;
	} );
}
