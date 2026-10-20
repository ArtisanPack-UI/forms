/**
 * Field settings storage contract.
 *
 * Client-side mirror of `FormField::getFileMaxSize()`, `getFileAllowedTypes()`
 * and `getLayoutContent()`, so the React and Vue builders and renderers read and
 * write the same columns as the Livewire builder and the Blade renderer.
 *
 * - File `max_size` / `allowed_types` live in `field_config`. Fields saved before
 *   1.6.0 kept the builder's value in `validation_rules`, which still wins until
 *   the field is next saved (the builders clear it on write).
 * - `heading` content lives in `label`; `paragraph` and `html` content lives in
 *   `field_config.content`. The React and Vue builders wrote all three to
 *   `default_value` before 1.6.0, so a non-empty `default_value` is unmigrated
 *   content and wins until the field is next saved (the builders clear it).
 * - Display settings — textarea `rows`, heading `level`, divider `style` /
 *   `spacing`, time `step` and checkbox `checked_label` / `unchecked_label` —
 *   live in `field_config`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField, UpdateFieldRequest, ValidationRules } from '../types/artisanpack-forms';

/** Field types whose content is edited in the builder's General tab. */
export const CONTENT_LAYOUT_TYPES = new Set( ['heading', 'paragraph', 'html'] );

/** Field types whose required state means "must be checked". */
export const ACCEPTANCE_FIELD_TYPES = new Set( ['checkbox', 'toggle'] );

type FileSettingKey = 'max_size' | 'allowed_types';

/** Read a file setting, preferring an unmigrated `validation_rules` value. */
function readFileSetting( field: FormField, key: FileSettingKey ): unknown {
	const legacy = ( field.validation_rules as Record<string, unknown> | null | undefined )?.[key];

	if ( legacy !== undefined && legacy !== null ) {
		return legacy;
	}

	return ( field.field_config as Record<string, unknown> | null | undefined )?.[key];
}

/** Read a non-blank string, or undefined. */
function nonBlank( value: unknown ): string | undefined {
	return typeof value === 'string' && value.trim() !== '' ? value : undefined;
}

/**
 * Get the maximum upload size in KB for a file field, or undefined if unlimited.
 */
export function getFileMaxSize( field: FormField ): number | undefined {
	const value = Number( readFileSetting( field, 'max_size' ) );

	return Number.isFinite( value ) && value > 0 ? Math.trunc( value ) : undefined;
}

/**
 * Get the allowed extensions for a file field — lowercase, without a leading dot.
 */
export function getFileAllowedTypes( field: FormField ): string[] {
	let types = readFileSetting( field, 'allowed_types' );

	if ( typeof types === 'string' ) {
		types = types.split( ',' );
	}

	if ( !Array.isArray( types ) ) {
		return [];
	}

	const normalized = types
		.filter( ( type ): type is string => typeof type === 'string' )
		.map( ( type ) => type.trim().replace( /^\.+/, '' ).toLowerCase() )
		.filter( ( type ) => /^[a-z0-9]+$/.test( type ) );

	return Array.from( new Set( normalized ) );
}

/**
 * Get the `accept` attribute value for a file input (e.g. `.pdf,.png`).
 */
export function getFileAccept( field: FormField ): string | undefined {
	const types = getFileAllowedTypes( field );

	return types.length > 0 ? types.map( ( type ) => `.${type}` ).join( ',' ) : undefined;
}

/**
 * Get a file setting as the builder's text input shows it. Unlike the getters
 * above this keeps the value as entered (blank entries included), so a
 * trailing comma survives while the admin is still typing the next type.
 */
export function getFileSettingInput( field: FormField, key: FileSettingKey ): string {
	const value = readFileSetting( field, key );

	if ( Array.isArray( value ) ) {
		return value.filter( ( entry ) => typeof entry === 'string' ).join( ', ' );
	}

	return typeof value === 'string' || typeof value === 'number' ? String( value ) : '';
}

/**
 * Build the update that stores a file setting in `field_config` and drops any
 * legacy copy from `validation_rules` so it no longer shadows the new value.
 */
export function buildFileSettingUpdate(
	field: FormField,
	key: FileSettingKey,
	value: number | string[] | undefined,
): UpdateFieldRequest {
	const fieldConfig: Record<string, unknown> = { ...( field.field_config ?? {} ) };
	const validationRules: Record<string, unknown> = { ...( field.validation_rules ?? {} ) };

	if ( value === undefined ) {
		delete fieldConfig[key];
	} else {
		fieldConfig[key] = value;
	}

	delete validationRules[key];

	return {
		field_config: fieldConfig as FormField['field_config'],
		validation_rules: validationRules as ValidationRules,
	};
}

/**
 * Get the display content for a heading, paragraph, or HTML layout field.
 */
export function getLayoutContent( field: FormField ): string {
	const legacy = nonBlank( field.default_value );
	const content = nonBlank( ( field.field_config as Record<string, unknown> | null | undefined )?.content );

	switch ( field.type ) {
		case 'heading':
			return legacy ?? field.label ?? '';
		case 'paragraph':
			return legacy ?? content ?? nonBlank( field.help_text ) ?? field.label ?? '';
		case 'html':
			return legacy ?? content ?? '';
		default:
			return '';
	}
}

/**
 * Build the update that stores a layout field's content in its canonical
 * column and clears the legacy `default_value` copy.
 */
export function buildLayoutContentUpdate( field: FormField, content: string ): UpdateFieldRequest {
	if ( field.type === 'heading' ) {
		return { label: content, default_value: null };
	}

	return {
		field_config: { ...( field.field_config ?? {} ), content } as FormField['field_config'],
		default_value: null,
	};
}

/** Heading levels the renderers accept, mirroring `FormField::HEADING_LEVELS`. */
export const HEADING_LEVELS = ['h2', 'h3', 'h4', 'h5', 'h6'] as const;

export type HeadingLevel = typeof HEADING_LEVELS[number];

/** Divider line styles, mirroring `FormField::DIVIDER_STYLES`. */
export const DIVIDER_STYLES = ['solid', 'dashed', 'dotted'] as const;

/** Divider spacing sizes, mirroring `FormField::DIVIDER_SPACINGS`. */
export const DIVIDER_SPACINGS = ['small', 'normal', 'large'] as const;

/** Read a `field_config` entry. */
function readConfig( field: FormField, key: string ): unknown {
	return ( field.field_config as Record<string, unknown> | null | undefined )?.[key];
}

/**
 * Get a heading's tag. The builders store `h2`–`h6`; a bare level number
 * (from an API write) is accepted too. Anything else falls back to `h3`, as
 * the Blade renderer does.
 */
export function getHeadingLevel( field: FormField ): HeadingLevel {
	const raw = readConfig( field, 'level' );
	const tag = typeof raw === 'number' || ( typeof raw === 'string' && /^\d$/.test( raw ) )
		? `h${raw}`
		: String( raw ?? '' ).toLowerCase();

	return ( HEADING_LEVELS as readonly string[] ).includes( tag ) ? tag as HeadingLevel : 'h3';
}

/** Get a textarea's row count, defaulting to 4. */
export function getTextareaRows( field: FormField ): number {
	const rows = Number( readConfig( field, 'rows' ) );

	return Number.isInteger( rows ) && rows >= 1 ? rows : 4;
}

/** Get a time field's step in seconds, or undefined for the browser default (60). */
export function getTimeStep( field: FormField ): number | undefined {
	const step = Number( readConfig( field, 'step' ) );

	return Number.isFinite( step ) && step > 0 ? step : undefined;
}

/** Get the border style and spacing classes for a divider, as the Blade renderer applies them. */
export function getDividerClasses( field: FormField ): string {
	const style = readConfig( field, 'style' );
	const spacing = readConfig( field, 'spacing' );

	const borderClass = style === 'dashed' ? 'border-dashed' : style === 'dotted' ? 'border-dotted' : 'border-solid';
	const spacingClass = spacing === 'small' ? 'my-2' : spacing === 'large' ? 'my-8' : 'my-4';

	return `${borderClass} ${spacingClass}`;
}

/**
 * Parse a builder input as a whole number from 1 to `max`, or undefined to
 * clear the setting. Mirrors the Livewire builder's `rows` and `step` parsing.
 */
export function parsePositiveInteger( value: string, max = Number.MAX_SAFE_INTEGER ): number | undefined {
	if ( !/^\d+$/.test( value.trim() ) ) {
		return undefined;
	}

	const num = Number( value.trim() );

	return num >= 1 && num <= max ? num : undefined;
}

/** Get a `field_config` setting as the builder's input shows it. */
export function getFieldConfigInput( field: FormField, key: string ): string {
	const value = readConfig( field, key );

	return typeof value === 'string' || typeof value === 'number' ? String( value ) : '';
}

/**
 * Build the update that sets one `field_config` entry, or removes it when the
 * value is undefined so the renderer falls back to its default.
 */
export function buildFieldConfigUpdate(
	field: FormField,
	key: string,
	value: string | number | undefined,
): UpdateFieldRequest {
	const fieldConfig: Record<string, unknown> = { ...( field.field_config ?? {} ) };

	if ( value === undefined || value === '' ) {
		delete fieldConfig[key];
	} else {
		fieldConfig[key] = value;
	}

	return { field_config: fieldConfig as FormField['field_config'] };
}
