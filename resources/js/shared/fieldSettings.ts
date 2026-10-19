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
