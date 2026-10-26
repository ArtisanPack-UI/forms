/**
 * Live merge tags for HTML blocks.
 *
 * Client-side mirror of `Support\LiveMergeTags` and the Blade `html` partial:
 * an HTML block's `{field_name}` (or `{field_name:key}`) tags show what the
 * visitor entered, as they type. Only tags in the block's text are replaced,
 * never in its attributes; the value is escaped. A choice shows its label, a
 * list of choices or a composite field its filled parts, a list field its
 * rows, and a field hidden by conditional logic nothing. Tags naming no input
 * on the public form are left as typed.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';
import { OTHER_CHOICE_VALUE } from './fieldSettings';
import { t } from './i18n';

/** The merge tag pattern, mirroring `LiveMergeTags::PATTERN`. */
export const LIVE_MERGE_TAG_PATTERN = /\{([A-Za-z0-9_-]+)(?::([A-Za-z0-9_]+))?\}/g;

/** Field types whose value never shows in a merge tag. */
const UNTAGGABLE_TYPES = new Set( ['section', 'heading', 'paragraph', 'divider', 'html', 'file', 'captcha'] );

/** The values a block's merge tags read. */
export interface LiveMergeTagValues {
	/** The form's values keyed by field name. */
	values: Record<string, unknown>;
	/** Fields hidden by conditional logic (true = hidden). */
	hiddenFields?: Record<string, boolean>;
	/** Text typed into each field's "Other" choice. */
	otherValues?: Record<string, string>;
}

/** Escape text for HTML. */
function escapeHtml( text: string ): string {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
}

/** Whether a merge tag may show a field's value (mirrors `LiveMergeTags::isTaggable()`). */
function isTaggable( field: FormField ): boolean {
	return !UNTAGGABLE_TYPES.has( field.type ) && !field.is_administrative;
}

/**
 * Map a field's choice values (and sub-input options) to their labels. The
 * map has no prototype, so a value such as "constructor" finds no label.
 */
function getOptionLabels( field: FormField ): Record<string, string> {
	const labels: Record<string, string> = Object.create( null );

	for ( const option of field.options ?? [] ) {
		labels[String( option.value )] = String( option.label ?? option.value );
	}

	for ( const subInput of field.sub_inputs ?? [] ) {
		for ( const option of subInput.options ?? [] ) {
			labels[option.value] = option.label;
		}
	}

	return labels;
}

/** Show one scalar value as text. */
function formatScalar( field: FormField, value: unknown, otherText: string | undefined ): string {
	if ( value === null || value === undefined || value === false ) {
		return '';
	}

	if ( value === true ) {
		return t( 'Yes' );
	}

	const text = String( value ).trim();

	if ( text === OTHER_CHOICE_VALUE ) {
		return ( otherText ?? '' ).trim();
	}

	return getOptionLabels( field )[text] ?? text;
}

/** Show a field's value (or one of its parts) as text. */
function formatValue( field: FormField, value: unknown, part: string | undefined, otherText: string | undefined ): string {
	if ( Array.isArray( value ) ) {
		const isRows = value.some( ( item ) => item !== null && typeof item === 'object' );

		return value
			.map( ( item ) => ( item !== null && typeof item === 'object' ? formatValue( field, item, undefined, otherText ) : formatScalar( field, item, otherText ) ) )
			.filter( ( item ) => item !== '' )
			.join( isRows ? '; ' : ', ' );
	}

	if ( value !== null && typeof value === 'object' ) {
		const parts = value as Record<string, unknown>;

		if ( part !== undefined ) {
			return formatScalar( field, parts[part], otherText );
		}

		return Object.values( parts )
			.map( ( item ) => formatScalar( field, item, otherText ) )
			.filter( ( item ) => item !== '' )
			.join( field.format_separator ?? ', ' );
	}

	return part === undefined ? formatScalar( field, value, otherText ) : '';
}

/**
 * Replace the merge tags in an HTML block's text with the visitor's values,
 * escaped. Text inside tags (attributes) is left alone.
 *
 * @param html The block's sanitized HTML.
 * @param fields The form's fields.
 */
export function applyLiveMergeTags( html: string, fields: FormField[], state: LiveMergeTagValues ): string {
	if ( !html.includes( '{' ) ) {
		return html;
	}

	const byName = new Map( fields.filter( isTaggable ).map( ( field ) => [field.name, field] ) );

	if ( byName.size === 0 ) {
		return html;
	}

	return html
		.split( /(<[^>]*>)/ )
		.map( ( segment ) => ( segment.startsWith( '<' )
			? segment
			: segment.replace( LIVE_MERGE_TAG_PATTERN, ( tag: string, name: string, part?: string ) => {
				const field = byName.get( name );

				if ( !field ) {
					return tag;
				}

				if ( state.hiddenFields?.[name] ) {
					return '';
				}

				return escapeHtml( formatValue( field, state.values[name], part, state.otherValues?.[name] ) );
			} ) ) )
		.join( '' );
}
