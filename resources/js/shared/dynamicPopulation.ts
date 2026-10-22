/**
 * Dynamic population of field values.
 *
 * Client-side mirror of `DynamicPopulation` and `MergeTags` on the server.
 * The render API resolves what the server knows — `{user:…}`, `{date}`,
 * `{ip}` and the `ap.forms.fieldValue.{parameter}` filter — into each field's
 * `resolved_default_value`, and leaves the page tags (`{embed_url}`,
 * `{referer}`, `{query:…}`) for the browser, which knows the page. A field
 * with a `dynamic_parameter` then takes, in order of precedence, the
 * renderer's `prefill`, the page's query string, and that resolved value.
 *
 * Every populated value is sanitized, and choice fields only take values
 * matching one of their options. A populated value is only a starting value;
 * the server validates it like any other input.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FieldOption, FormField, OptionsFieldConfig } from '../types/artisanpack-forms';

/** The longest populated value kept, mirroring `DynamicPopulation::MAX_LENGTH`. */
export const MAX_POPULATED_LENGTH = 1000;

/** The merge tags the builders list beside the Default Value input, mirroring `MergeTags::available()`. */
export const MERGE_TAGS = ['{user:email}', '{user:name}', '{date}', '{embed_url}', '{referer}', '{ip}', '{query:utm_source}'];

/** Matches the page tags the server leaves for the client. */
const PAGE_TAG_PATTERN = /\{(embed_url|referer|query:[A-Za-z0-9_.-]+)\}/g;

/** The page a form is shown on. */
export interface PageContext {
	/** The page URL. */
	url: string;
	/** The page the visitor came from. */
	referrer: string;
	/** The page's query string. */
	query: URLSearchParams;
}

/** Where a field's starting value may come from. */
export interface PopulationSource {
	/** Values passed to the renderer, keyed by parameter name. */
	prefill?: Record<string, unknown>;
	/** The page; read from `window` when left out. */
	page?: PageContext;
}

/**
 * Get the page the form is shown on, or undefined outside a browser.
 */
export function getPageContext(): PageContext | undefined {
	if ( typeof window === 'undefined' || !window.location ) {
		return undefined;
	}

	return {
		url: window.location.href,
		referrer: typeof document !== 'undefined' ? document.referrer : '',
		query: new URLSearchParams( window.location.search ),
	};
}

/**
 * Make a populated value safe to show and submit: strip tags and control
 * characters, trim, and cap the length. Mirrors `DynamicPopulation::sanitize()`.
 */
export function sanitizePopulatedValue( value: string ): string {
	return value
		.replace( /<[^>]*>/g, '' )
		// eslint-disable-next-line no-control-regex
		.replace( /[\u0000-\u001F\u007F]/g, '' )
		.trim()
		.slice( 0, MAX_POPULATED_LENGTH );
}

/**
 * Replace the page tags in a value. Without a page they become empty.
 */
export function resolvePageMergeTags( text: string, page?: PageContext ): string {
	return text.replace( PAGE_TAG_PATTERN, ( _match, tag: string ) => {
		if ( !page ) {
			return '';
		}

		if ( tag === 'embed_url' ) {
			return sanitizePopulatedValue( page.url );
		}

		if ( tag === 'referer' ) {
			return sanitizePopulatedValue( page.referrer );
		}

		return sanitizePopulatedValue( page.query.getAll( tag.slice( 6 ) ).join( ',' ) );
	} );
}

/** Read a field's options. */
function getOptions( field: FormField ): FieldOption[] {
	return field.options ?? ( field.field_config as OptionsFieldConfig | null )?.options ?? [];
}

/**
 * Find the option a populated value selects: by value, then by label
 * ignoring case. Mirrors `DynamicPopulation::matchOption()`.
 */
function matchOption( field: FormField, value: string ): string | undefined {
	if ( value === '' ) {
		return undefined;
	}

	const options = getOptions( field );
	const byValue = options.find( ( option ) => String( option.value ) === value );

	if ( byValue ) {
		return String( byValue.value );
	}

	const byLabel = options.find( ( option ) => String( option.label ?? '' ).toLowerCase() === value.toLowerCase() );

	return byLabel ? String( byLabel.value ) : undefined;
}

/** Read a value as on or off, or undefined when it is neither. */
function parseBoolean( value: string ): boolean | undefined {
	const normalized = value.trim().toLowerCase();

	if ( ['1', 'true', 'on', 'yes'].includes( normalized ) ) {
		return true;
	}

	if ( ['0', 'false', 'off', 'no', ''].includes( normalized ) ) {
		return false;
	}

	return undefined;
}

/**
 * Fit a populated value to the field's type. Mirrors `DynamicPopulation::coerce()`.
 */
function coerce( field: FormField, raw: unknown, fallback: unknown ): unknown {
	const items = ( Array.isArray( raw ) ? raw : [raw] )
		.filter( ( item ) => ['string', 'number', 'boolean'].includes( typeof item ) )
		.map( ( item ) => sanitizePopulatedValue( String( item ) ) );

	if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
		const values = items
			.flatMap( ( item ) => item.split( ',' ) )
			.map( ( part ) => matchOption( field, part.trim() ) )
			.filter( ( match ): match is string => match !== undefined );

		return values.length > 0 ? Array.from( new Set( values ) ) : fallback;
	}

	const value = items[0] ?? '';

	if ( field.type === 'checkbox' || field.type === 'toggle' ) {
		return parseBoolean( value ) ?? fallback;
	}

	if ( field.type === 'select' || field.type === 'radio' ) {
		return matchOption( field, value ) ?? fallback ?? '';
	}

	return value;
}

/**
 * Get the value a field starts with before it is shaped for its input:
 * its resolved default value with page tags replaced, or — when the field is
 * populated dynamically — the prefill or query string value that takes
 * precedence over it.
 *
 * Read-only fields are handled by the caller (see `getInitialFieldValue()`),
 * since the client cannot change their value.
 */
export function getPopulatedValue( field: FormField, source: PopulationSource = {} ): unknown {
	const page = source.page ?? getPageContext();
	let base: unknown = field.resolved_default_value !== undefined ? field.resolved_default_value : field.default_value;

	if ( typeof base === 'string' ) {
		base = resolvePageMergeTags( base, page );
	}

	const parameter = field.dynamic_parameter;

	if ( !parameter ) {
		return base;
	}

	if ( source.prefill && Object.prototype.hasOwnProperty.call( source.prefill, parameter ) ) {
		return coerce( field, source.prefill[parameter], base );
	}

	if ( page?.query.has( parameter ) ) {
		const values = page.query.getAll( parameter );

		return coerce( field, values.length > 1 ? values : values[0], base );
	}

	return base;
}
