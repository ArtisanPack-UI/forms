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
 * - Every type-specific setting is described by the field's `settings_schema`
 *   (see `FieldSettings` on the server); `getSettingInput()`, `parseSetting()`
 *   and `buildSettingUpdate()` mirror its reading and parsing.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type {
	FieldSettingDefinition,
	FormField,
	UpdateFieldRequest,
	ValidationRules,
} from '../types/artisanpack-forms';

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

// ---------------------------------------------------------------------------
// Field widths
// ---------------------------------------------------------------------------

/**
 * Width classes, mirroring `FieldTypes::WIDTH_CLASSES` (the single source on
 * the server). Partial widths stack full-width below the `md` breakpoint.
 */
export const WIDTH_CLASSES: Readonly<Record<string, string>> = {
	full: 'w-full',
	half: 'w-full md:w-1/2',
	third: 'w-full md:w-1/3',
	'two-thirds': 'w-full md:w-2/3',
};

/** Get a field's width class, falling back to full width. */
export function getWidthClass( width: string | null | undefined ): string {
	return width && Object.prototype.hasOwnProperty.call( WIDTH_CLASSES, width ) ? WIDTH_CLASSES[width] : WIDTH_CLASSES.full;
}

// ---------------------------------------------------------------------------
// Settings schema
// ---------------------------------------------------------------------------

/** Get the settings in a field's schema that belong in one builder tab. */
export function getSettingsForTab( field: FormField, tab: FieldSettingDefinition['tab'] ): FieldSettingDefinition[] {
	return ( field.settings_schema ?? [] ).filter( ( setting ) => setting.tab === tab );
}

/** Read a setting's stored value, preferring an unmigrated legacy copy. */
export function readSetting( field: FormField, setting: FieldSettingDefinition ): unknown {
	if ( setting.legacy_store ) {
		const legacy = ( field[setting.legacy_store] as Record<string, unknown> | null | undefined )?.[setting.key];

		if ( legacy !== undefined && legacy !== null ) {
			return legacy;
		}
	}

	return ( field[setting.store] as Record<string, unknown> | null | undefined )?.[setting.key];
}

/**
 * Get a setting's value as the builder input shows it. Mirrors
 * `FieldSettings::inputValue()`: toggles give a boolean, selects fall back to
 * the default, and other inputs give a string (`''` when unset).
 */
export function getSettingInput( field: FormField, setting: FieldSettingDefinition ): string | boolean {
	const value = readSetting( field, setting );

	switch ( setting.input ) {
		case 'toggle':
			return value === undefined || value === null ? Boolean( setting.default ?? false ) : isTruthySetting( value );
		case 'select':
			return typeof value === 'string' || typeof value === 'number' ? String( value ) : String( setting.default ?? '' );
		case 'list':
			if ( Array.isArray( value ) ) {
				return value.filter( ( entry ) => typeof entry === 'string' ).join( ', ' );
			}

			return typeof value === 'string' ? value : '';
		default:
			return typeof value === 'string' || typeof value === 'number' ? String( value ) : '';
	}
}

/** Whether a stored setting counts as on, mirroring PHP's `FILTER_VALIDATE_BOOLEAN`. */
function isTruthySetting( value: unknown ): boolean {
	if ( typeof value === 'boolean' ) {
		return value;
	}

	return ['1', 'true', 'on', 'yes'].includes( String( value ).trim().toLowerCase() );
}

/** Whether a number is within a setting's `min` / `max`. */
function withinBounds( setting: FieldSettingDefinition, value: number ): boolean {
	if ( typeof setting.min === 'number' && value < setting.min ) {
		return false;
	}

	return !( typeof setting.max === 'number' && value > setting.max );
}

/** Matches `H:i` or `H:i:s`, mirroring `FormField::TIME_FORMAT_PATTERN`. */
const SETTING_TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/;

/**
 * Parse a raw builder input into the value to store, or undefined to clear
 * the setting. Mirrors `FieldSettings::parse()`.
 */
export function parseSetting(
	setting: FieldSettingDefinition,
	raw: unknown,
): string | number | boolean | string[] | undefined {
	if ( setting.input === 'toggle' ) {
		return isTruthySetting( raw ) ? true : undefined;
	}

	if ( setting.input === 'list' ) {
		const items = ( Array.isArray( raw ) ? raw : String( raw ?? '' ).split( ',' ) )
			.map( ( item ) => ( typeof item === 'string' || typeof item === 'number' ? String( item ).trim() : '' ) )
			.filter( ( item ) => item !== '' );

		return items.length > 0 ? items : undefined;
	}

	if ( typeof raw !== 'string' && typeof raw !== 'number' ) {
		return undefined;
	}

	const value = String( raw ).trim();

	if ( value === '' ) {
		return undefined;
	}

	switch ( setting.input ) {
		case 'integer': {
			if ( !/^-?\d+$/.test( value ) ) {
				return undefined;
			}

			const num = Number( value );

			return withinBounds( setting, num ) ? num : undefined;
		}
		case 'number': {
			const num = Number( value );

			return Number.isFinite( num ) && withinBounds( setting, num ) ? num : undefined;
		}
		case 'select':
			return ( setting.options ?? [] ).some( ( option ) => option.value === value ) ? value : undefined;
		case 'date': {
			const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value );

			if ( !match ) {
				return undefined;
			}

			const date = new Date( Date.UTC( Number( match[1] ), Number( match[2] ) - 1, Number( match[3] ) ) );

			return date.getUTCMonth() === Number( match[2] ) - 1 && date.getUTCDate() === Number( match[3] ) ? value : undefined;
		}
		case 'time':
			return SETTING_TIME_PATTERN.test( value ) ? value.replace( /^(\d{2}:\d{2}):00$/, '$1' ) : undefined;
		default:
			return typeof setting.max_length === 'number' ? value.slice( 0, setting.max_length ) : value;
	}
}

/**
 * Build the update that stores a raw builder input for a setting, removing it
 * when blank or invalid and dropping any legacy copy. Mirrors
 * `FieldSettings::update()`.
 */
export function buildSettingUpdate(
	field: FormField,
	setting: FieldSettingDefinition,
	raw: unknown,
): UpdateFieldRequest {
	const parsed = parseSetting( setting, raw );
	const stored: Record<string, unknown> = { ...( ( field[setting.store] as Record<string, unknown> | null ) ?? {} ) };

	if ( parsed === undefined ) {
		delete stored[setting.key];
	} else {
		stored[setting.key] = parsed;
	}

	const update: Record<string, unknown> = { [setting.store]: stored };

	if ( setting.legacy_store && setting.legacy_store !== setting.store ) {
		const legacy: Record<string, unknown> = { ...( ( field[setting.legacy_store] as Record<string, unknown> | null ) ?? {} ) };

		delete legacy[setting.key];
		update[setting.legacy_store] = legacy;
	}

	return update as UpdateFieldRequest;
}

// ---------------------------------------------------------------------------
// Read-only and rating fields
// ---------------------------------------------------------------------------

/** Whether a field is shown but not editable. */
export function isReadOnlyField( field: FormField ): boolean {
	return field.is_read_only === true;
}

/**
 * The value a field starts with in the renderer.
 *
 * A read-only field starts with the value the server will store
 * (`read_only_value`), so what is shown matches what is saved; other fields
 * start with their default value.
 *
 * @since 1.6.0
 */
export function getInitialFieldValue( field: FormField ): unknown {
	const source: unknown = isReadOnlyField( field ) && field.read_only_value !== undefined
		? field.read_only_value
		: field.default_value;

	if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
		if ( Array.isArray( source ) ) {
			return source.map( String );
		}

		return typeof source === 'string' && source
			? source.split( ',' ).map( ( v ) => v.trim() )
			: [];
	}

	if ( field.type === 'checkbox' ) {
		return source === true || source === '1' || source === 'true';
	}

	return source ?? '';
}

/** The largest number of stars a rating field may have, mirroring `RatingFieldType::MAX_STARS`. */
export const RATING_MAX_STARS = 10;

/** Get a rating field's number of stars (1–10, default 5), mirroring `RatingFieldType::maxRating()`. */
export function getRatingMax( field: FormField ): number {
	const max = Number( readConfig( field, 'max_rating' ) );

	if ( !Number.isFinite( max ) || Math.trunc( max ) < 1 ) {
		return 5;
	}

	return Math.min( Math.trunc( max ), RATING_MAX_STARS );
}

/** Whether a rating field accepts half stars. */
export function ratingAllowsHalf( field: FormField ): boolean {
	return isTruthySetting( readConfig( field, 'allow_half' ) ?? false );
}

/** Get a rating field's daisyUI size class (`rating-xs` … `rating-lg`, or none for `md`). */
export function getRatingSizeClass( field: FormField ): string {
	const size = readConfig( field, 'size' );

	return size === 'xs' || size === 'sm' || size === 'lg' ? `rating-${size}` : '';
}

/** Get the selectable rating values, in ascending order: 1…max, or 0.5…max in halves. */
export function getRatingSteps( field: FormField ): number[] {
	const max = getRatingMax( field );
	const step = ratingAllowsHalf( field ) ? 0.5 : 1;
	const steps: number[] = [];

	for ( let value = step; value <= max; value += step ) {
		steps.push( value );
	}

	return steps;
}
