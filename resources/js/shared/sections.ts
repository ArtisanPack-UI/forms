/**
 * Section helpers.
 *
 * Client-side mirror of `Support\Sections`: a section groups the fields after
 * it, in sort order, up to the next section or the end of its step, and a
 * hidden section hides every field it groups (a CAPTCHA stays shown).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

/** A run of fields, with the section that groups them (none before the first section). */
export interface FieldSegment {
	section: FormField | null;
	fields: FormField[];
}

/** Whether a field is a section. */
export function isSectionField( field: FormField ): boolean {
	return field.type === 'section';
}

/**
 * Split fields, already in display order, into the runs each section
 * groups. Mirrors `Sections::segments()`.
 */
export function getFieldSegments( fields: FormField[] ): FieldSegment[] {
	const segments: FieldSegment[] = [];
	let current: FieldSegment = { section: null, fields: [] };

	for ( const field of fields ) {
		if ( isSectionField( field ) ) {
			if ( current.section || current.fields.length > 0 ) {
				segments.push( current );
			}

			current = { section: field, fields: [] };

			continue;
		}

		current.fields.push( field );
	}

	if ( current.section || current.fields.length > 0 ) {
		segments.push( current );
	}

	return segments;
}

/**
 * Get the names of the fields each section groups, keyed by section name.
 * Fields are grouped by step and ordered by `sort_order`, so the list may
 * come in any order. Mirrors `Sections::members()`.
 */
export function getSectionMembers( fields: FormField[] ): Record<string, string[]> {
	const steps = new Map<string, FormField[]>();

	for ( const field of fields ) {
		const step = String( field.step_id ?? '' );

		steps.set( step, [...( steps.get( step ) ?? [] ), field] );
	}

	const members: Record<string, string[]> = {};

	for ( const stepFields of steps.values() ) {
		const ordered = [...stepFields].sort( ( a, b ) => a.sort_order - b.sort_order || a.id - b.id );

		for ( const segment of getFieldSegments( ordered ) ) {
			if ( segment.section ) {
				members[segment.section.name] = segment.fields.map( ( field ) => field.name );
			}
		}
	}

	return members;
}

/**
 * Hide the fields of each hidden section. Mirrors `Sections::applyVisibility()`.
 *
 * @param hidden Hidden state keyed by field name (true = hidden).
 * @returns The hidden state with each hidden section's fields hidden.
 */
export function applySectionVisibility( fields: FormField[], hidden: Record<string, boolean> ): Record<string, boolean> {
	const types = Object.fromEntries( fields.map( ( field ) => [field.name, field.type] ) );
	const result = { ...hidden };

	for ( const [section, names] of Object.entries( getSectionMembers( fields ) ) ) {
		if ( !hidden[section] ) {
			continue;
		}

		for ( const name of names ) {
			if ( types[name] !== 'captcha' ) {
				result[name] = true;
			}
		}
	}

	return result;
}

/** Read a `field_config` flag. */
function configFlag( field: FormField, key: string ): boolean {
	const value = ( field.field_config as Record<string, unknown> | null | undefined )?.[key];

	return value === true || value === 1 || value === '1' || value === 'true';
}

/** Get the description shown under a section's title, or undefined (mirrors `FormField::getSectionDescription()`). */
export function getSectionDescription( field: FormField ): string | undefined {
	const description = ( field.field_config as Record<string, unknown> | null | undefined )?.description;

	return isSectionField( field ) && typeof description === 'string' && description.trim() !== '' ? description.trim() : undefined;
}

/** Whether visitors can collapse a section. */
export function isCollapsibleSection( field: FormField ): boolean {
	return isSectionField( field ) && configFlag( field, 'collapsible' );
}

/** Whether a collapsible section starts collapsed. */
export function startsCollapsed( field: FormField ): boolean {
	return isCollapsibleSection( field ) && configFlag( field, 'collapsed' );
}
