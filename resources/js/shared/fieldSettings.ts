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
	DisplayConfig,
	FieldSettingDefinition,
	FormField,
	FormSubmissionValue,
	OptionsFieldConfig,
	UpdateFieldRequest,
	ValidationRules,
} from '../types/artisanpack-forms';

import { getEnabledSubInputs, isCompositeField, matchSubInputOption, toCompositeValue } from './composite';
import { showsCounter } from './counter';
import { parseDateExpression } from './dateConstraints';
import { getPageContext, getPopulatedValue, sanitizePopulatedValue } from './dynamicPopulation';
import type { PopulationSource } from './dynamicPopulation';
import { t } from './i18n';
import { getInitialListRows, isListField } from './list';
import { getInitialMatrixValue, isMatrixField } from './matrix';
import { getRankingOrder, isRankingField } from './ranking';
import { getInitialSliderValue, isSliderField } from './slider';
import { getInputMaskHint } from './mask';

/** Field types whose content is edited in the builder's General tab. */
export const CONTENT_LAYOUT_TYPES = new Set( ['heading', 'paragraph', 'html'] );

/** Layout field types, which collect no data (`section` since 1.6.0). */
export const LAYOUT_FIELD_TYPES: ReadonlySet<string> = new Set( ['section', 'heading', 'paragraph', 'divider', 'html'] );

/**
 * Whether conditional logic can show and hide a field type: input types, and
 * a section, which hides the fields it groups (since 1.6.0). Mirrors
 * `FieldTypes::supportsConditionalLogic()`.
 */
export function supportsConditionalLogic( type: string ): boolean {
	return !LAYOUT_FIELD_TYPES.has( type ) || type === 'section';
}

/** Field types whose required state means "must be checked". */
export const ACCEPTANCE_FIELD_TYPES = new Set( ['checkbox', 'toggle', 'consent'] );

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

/** Read a positive whole number from `field_config`, or undefined. */
function positiveConfigInteger( field: FormField, key: string ): number | undefined {
	const value = Number( field.field_config?.[key] );

	return Number.isFinite( value ) && value > 0 ? Math.trunc( value ) : undefined;
}

/** Whether a file field takes several files (since 1.6.0). */
export function allowsMultipleFiles( field: FormField ): boolean {
	const value = field.field_config?.multiple;

	return field.type === 'file' && ( value === true || value === 1 || value === '1' || value === 'true' );
}

/** Get the most files a multi-file field takes, or undefined when unlimited (since 1.6.0). */
export function getFileMaxCount( field: FormField ): number | undefined {
	return allowsMultipleFiles( field ) ? positiveConfigInteger( field, 'max_files' ) : undefined;
}

/** Get the largest total size in KB of a multi-file field's files, or undefined (since 1.6.0). */
export function getFileMaxTotalSize( field: FormField ): number | undefined {
	return allowsMultipleFiles( field ) ? positiveConfigInteger( field, 'max_total_size' ) : undefined;
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

/** Heading alignments, mirroring `FormField::HEADING_ALIGNMENTS` (since 1.6.0). */
export const HEADING_ALIGNMENTS = ['left', 'center', 'right'] as const;

/** Get a heading's text alignment class, as the Blade renderer applies it (since 1.6.0). */
export function getHeadingAlignClass( field: FormField ): string {
	const align = readConfig( field, 'align' );

	return align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : '';
}

/**
 * Get the note that tells visitors a file field's limits: the number of
 * files, the allowed types and the size limits. Mirrors
 * `FormField::getFileLimitsHint()` (since 1.6.0).
 */
export function getFileLimitsHint( field: FormField ): string | undefined {
	const types = getFileAllowedTypes( field );
	const maxSize = getFileMaxSize( field );
	const maxFiles = getFileMaxCount( field );
	const maxTotal = getFileMaxTotalSize( field );

	const notes = [
		maxFiles !== undefined ? t( 'Up to :count files', { count: maxFiles } ) : undefined,
		types.length > 0 ? t( 'Allowed file types: :types', { types: types.map( ( type ) => type.toUpperCase() ).join( ', ' ) } ) : undefined,
		maxSize !== undefined ? t( 'Maximum file size: :size KB', { size: maxSize } ) : undefined,
		maxTotal !== undefined ? t( 'Maximum total size: :size KB', { size: maxTotal } ) : undefined,
	].filter( ( note ): note is string => note !== undefined );

	return notes.length > 0 ? notes.join( ' · ' ) : undefined;
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
		case 'date_expression':
			return parseDateExpression( value );
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

/**
 * Whether a setting's typed draft is what the field holds.
 *
 * Compares the draft as it would be stored with the stored value, so a draft
 * that only differs in formatting (`pdf,` for `pdf`) counts as stored, while
 * one the builder reverted or the server changed does not.
 *
 * @since 1.6.0
 */
export function isDraftStored( field: FormField, setting: FieldSettingDefinition, draft: string ): boolean {
	const drafted = { ...field, ...buildSettingUpdate( field, setting, draft ) } as FormField;

	return String( getSettingInput( drafted, setting ) ) === String( getSettingInput( field, setting ) );
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
 * (`read_only_value`), so what is shown matches what is saved. Other fields
 * start with their resolved default value, or — when populated dynamically —
 * the prefill or query string value (see `getPopulatedValue()`). A
 * composite field starts with the server's value per enabled sub-input (its
 * default, or the filter value of its parameter), and a sub-input with a
 * population parameter takes the prefill or query string value first; a
 * select sub-input keeps only a value that matches an option. Mirrors
 * `DynamicPopulation::compositeValue()`. A slider starts within its limits,
 * a matrix with every row unanswered and a ranking with every choice.
 *
 * @since 1.6.0
 */
export function getInitialFieldValue( field: FormField, population: PopulationSource = {} ): unknown {
	if ( isCompositeField( field ) ) {
		const resolved = toCompositeValue( field.resolved_default_value );
		const page = population.page ?? getPageContext();

		return Object.fromEntries( getEnabledSubInputs( field ).map( ( subInput ) => {
			const base = resolved[subInput.key] ?? subInput.default ?? '';
			const parameter = subInput.parameter;
			let raw: unknown;

			if ( parameter && population.prefill && Object.prototype.hasOwnProperty.call( population.prefill, parameter ) ) {
				raw = population.prefill[parameter];
			} else if ( parameter && page?.query.has( parameter ) ) {
				raw = page.query.get( parameter );
			}

			if ( typeof raw !== 'string' && typeof raw !== 'number' ) {
				return [subInput.key, base];
			}

			let value = sanitizePopulatedValue( String( raw ) );

			if ( subInput.type === 'select' && value !== '' ) {
				value = matchSubInputOption( subInput, value ) ?? base;
			}

			return [subInput.key, value === '' ? base : value];
		} ) );
	}

	// A list starts with empty rows to fill in (since 1.6.0)
	if ( isListField( field ) ) {
		return getInitialListRows( field );
	}

	// A matrix starts unanswered, and a ranking in the order the server sent (since 1.6.0)
	if ( isMatrixField( field ) ) {
		return getInitialMatrixValue( field, field.resolved_default_value );
	}

	if ( isRankingField( field ) ) {
		return getRankingOrder( field, field.resolved_default_value );
	}

	const source: unknown = isReadOnlyField( field ) && field.read_only_value !== undefined
		? field.read_only_value
		: getPopulatedValue( field, population );

	// A slider always has a value within its limits (since 1.6.0)
	if ( isSliderField( field ) ) {
		return getInitialSliderValue( field, source );
	}

	// A date and time input takes `YYYY-MM-DDTHH:MM` (since 1.6.0)
	if ( field.type === 'datetime' && typeof source === 'string' ) {
		return source.trim().replace( /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})(:\d{2})?$/, '$1T$2' );
	}

	if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
		if ( Array.isArray( source ) ) {
			return source.map( String );
		}

		return typeof source === 'string' && source
			? source.split( ',' ).map( ( v ) => v.trim() )
			: [];
	}

	if ( ACCEPTANCE_FIELD_TYPES.has( field.type ) ) {
		return source === true || source === '1' || source === 'true';
	}

	return source ?? '';
}

/** Whether an email field asks for the address twice (since 1.6.0). */
export function requiresEmailConfirmation( field: FormField ): boolean {
	return field.type === 'email' && field.email_confirmation !== undefined && field.email_confirmation !== null;
}

/**
 * Get the data key an email field's confirmation is submitted under,
 * mirroring `FormField::getConfirmationKey()` (since 1.6.0).
 */
export function getConfirmationKey( field: FormField ): string {
	return `${field.name}_confirmation`;
}

/** Switch sizes, mirroring `ToggleFieldType::SIZES`. */
export const TOGGLE_SIZES = ['xs', 'sm', 'md', 'lg'] as const;

/** daisyUI switch colors, mirroring `ToggleFieldType::COLORS`. */
export const TOGGLE_COLORS = ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info'] as const;

export type ToggleColor = typeof TOGGLE_COLORS[number];

/** Get a toggle's daisyUI size class (`toggle-xs` … `toggle-lg`, or none for `md`). */
export function getToggleSizeClass( field: FormField ): string {
	const size = readConfig( field, 'size' );

	return size === 'xs' || size === 'sm' || size === 'lg' ? `toggle-${size}` : '';
}

/** Get a toggle's color, defaulting to `primary` as the Blade renderer does. */
export function getToggleColor( field: FormField ): ToggleColor {
	const color = readConfig( field, 'color' );

	return ( TOGGLE_COLORS as readonly string[] ).includes( String( color ) ) ? color as ToggleColor : 'primary';
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

// ---------------------------------------------------------------------------
// "Other" choice
// ---------------------------------------------------------------------------

/**
 * The value a selected "Other" choice holds while the form is filled in,
 * mirroring `FormField::OTHER_CHOICE_VALUE`. It is replaced with the typed
 * text on submit (see `resolveOtherChoices()`); a blank text leaves it in
 * place, which the server rejects.
 *
 * @since 1.6.0
 */
export const OTHER_CHOICE_VALUE = '__other__';

/** The longest "Other" text, mirroring `FormField::OTHER_CHOICE_MAX_LENGTH`. */
export const OTHER_CHOICE_MAX_LENGTH = 255;

/** Whether a radio or checkbox group offers an "Other" choice with a text input. */
export function allowsOtherChoice( field: FormField ): boolean {
	return typeof field.other_choice_label === 'string';
}

/** Get the label of a field's "Other" choice. */
export function getOtherChoiceLabel( field: FormField ): string {
	return field.other_choice_label ?? 'Other';
}

/** Whether a field's value has its "Other" choice selected. */
export function isOtherChoiceSelected( value: unknown ): boolean {
	return Array.isArray( value ) ? value.includes( OTHER_CHOICE_VALUE ) : value === OTHER_CHOICE_VALUE;
}

/**
 * Replace each selected "Other" choice with the text typed for it, mirroring
 * `SubmissionService::resolveOtherChoices()`. A blank text leaves the
 * placeholder in place.
 *
 * @since 1.6.0
 */
export function resolveOtherChoices(
	fields: FormField[],
	values: Record<string, unknown>,
	otherValues: Record<string, string>,
): Record<string, unknown> {
	const resolved: Record<string, unknown> = { ...values };

	for ( const field of fields ) {
		const text = ( otherValues[field.name] ?? '' ).trim();

		if ( !allowsOtherChoice( field ) || text === '' || !( field.name in resolved ) ) {
			continue;
		}

		const value = resolved[field.name];

		if ( Array.isArray( value ) ) {
			resolved[field.name] = Array.from( new Set( value.map( ( item ) => ( item === OTHER_CHOICE_VALUE ? text : item ) ) ) );
		} else if ( value === OTHER_CHOICE_VALUE ) {
			resolved[field.name] = text;
		}
	}

	return resolved;
}

/**
 * Get the options a conditional logic rule on a field can test for: its
 * options, then its "Other" choice when it has one, so a rule can match any
 * text typed into it. Mirrors `FormField::getConditionOptions()`.
 *
 * @since 1.6.0
 */
export function getConditionOptions( field: FormField ): Array<{ label: string; value: string }> | null {
	const options = field.options ?? ( field.field_config as OptionsFieldConfig | null )?.options ?? [];
	const withOther = allowsOtherChoice( field )
		? [...options, { label: getOtherChoiceLabel( field ), value: OTHER_CHOICE_VALUE }]
		: options;

	return withOther.length > 0 ? withOther : null;
}

/**
 * Get the values an admin edits a submission's administrative fields with,
 * seeded from what is stored. Text stored for an "Other" choice selects the
 * choice and fills its input. Mirrors `SubmissionDetail::fillAdministrativeValues()`.
 *
 * @since 1.6.0
 */
export function getAdministrativeFormState(
	fields: FormField[],
	stored: FormSubmissionValue[],
): { values: Record<string, unknown>; otherValues: Record<string, string> } {
	const values: Record<string, unknown> = {};
	const otherValues: Record<string, string> = {};

	for ( const field of fields ) {
		const entry = stored.find( ( value ) => value.field_id === field.id );
		let value: unknown = entry?.value_array ?? entry?.value ?? null;

		if ( ACCEPTANCE_FIELD_TYPES.has( field.type ) ) {
			value = ['1', 'true', 'yes', 'on'].includes( String( value ?? '' ).toLowerCase() );
		} else if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
			value = Array.isArray( value ) ? value.map( String ) : [];
		}

		if ( allowsOtherChoice( field ) ) {
			const optionValues = new Set( ( field.options ?? ( field.field_config as OptionsFieldConfig | null )?.options ?? [] ).map( ( option ) => option.value ) );
			const isOther = ( item: unknown ): boolean => typeof item === 'string' && item.trim() !== '' && !optionValues.has( item );

			if ( Array.isArray( value ) ) {
				value = value.map( ( item ) => {
					if ( isOther( item ) ) {
						otherValues[field.name] = String( item );

						return OTHER_CHOICE_VALUE;
					}

					return item;
				} );
			} else if ( isOther( value ) ) {
				otherValues[field.name] = String( value );
				value = OTHER_CHOICE_VALUE;
			}
		}

		values[field.name] = value ?? '';
	}

	return { values, otherValues };
}

// ---------------------------------------------------------------------------
// Label and description placement
// ---------------------------------------------------------------------------

/** Label placements, mirroring `FormField::LABEL_PLACEMENTS`. */
export type LabelPlacement = 'top' | 'left' | 'hidden';

/** Description placements, mirroring `FormField::DESCRIPTION_PLACEMENTS`. */
export type DescriptionPlacement = 'above' | 'below';

/**
 * Where a field shows its label and description, and the IDs that tie them
 * to the input.
 *
 * @since 1.6.0
 */
export interface FieldLayout {
	/**
	 * Whether the field or its form sets a placement. When false, the field
	 * components keep their `displayConfig.label_position` behaviour.
	 */
	custom: boolean;
	label: LabelPlacement;
	description: DescriptionPlacement;
	/** The input's ID, for `<label htmlFor>`. */
	inputId: string;
	/** The label's ID, for a group's `aria-labelledby`. */
	labelId: string;
	/** The description's ID, for `aria-describedby`. */
	descriptionId: string;
}

/** Maps the legacy `display.label_position` config onto a label placement. */
const LEGACY_LABEL_PLACEMENTS: Record<string, LabelPlacement> = {
	above: 'top',
	beside: 'left',
	hidden: 'hidden',
};

/**
 * Get where a field shows its label and description.
 *
 * The render payload's `label_placement` and `description_placement` hold
 * the field's setting or its form's default (see
 * `FormField::getConfiguredLabelPlacement()`).
 *
 * @since 1.6.0
 */
export function getFieldLayout( field: FormField, displayConfig?: DisplayConfig ): FieldLayout {
	const label = field.label_placement ?? LEGACY_LABEL_PLACEMENTS[displayConfig?.label_position ?? 'above'] ?? 'top';

	return {
		custom: Boolean( field.label_placement || field.description_placement ),
		label,
		description: field.description_placement ?? 'below',
		inputId: `field-${field.name}`,
		labelId: `field-${field.name}-label`,
		descriptionId: `field-${field.name}-description`,
	};
}

/**
 * Get the `aria-describedby` value for an input in a custom layout: the
 * description (when the field has one) and the error the input component
 * renders as `{inputId}-error`.
 *
 * @since 1.6.0
 */
export function getLayoutDescribedBy( field: FormField, layout: FieldLayout, error?: string ): string | undefined {
	return [field.help_text ? layout.descriptionId : undefined, error ? `${layout.inputId}-error` : undefined]
		.filter( Boolean )
		.join( ' ' ) || undefined;
}

/** Label, hint and ID props for an input component that renders its own label and hint. */
export interface InputLabelProps {
	id?: string;
	label?: string;
	'aria-label'?: string;
	'aria-describedby'?: string;
	hint?: string;
	inline: boolean;
}

/**
 * Gets the label props for an input component.
 *
 * In a custom layout the component renders neither label nor hint (the
 * layout does) and its input gets the IDs they reference. Otherwise it
 * keeps the `displayConfig.label_position` behaviour.
 */
export function getInputLabelProps(
	field: FormField,
	layout: FieldLayout,
	displayConfig: DisplayConfig,
	error?: string,
): InputLabelProps {
	if ( layout.custom ) {
		return {
			id: layout.inputId,
			'aria-describedby': getLayoutDescribedBy( field, layout, error ),
			inline: false,
		};
	}

	return {
		label: displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined,
		'aria-label': displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined,
		hint: field.help_text ?? undefined,
		inline: displayConfig.label_position === 'beside',
	};
}

/**
 * The IDs of the notes a text-like input shows under it (since 1.6.0): its
 * mask's expected format, its counter and its prefix and suffix description.
 * Mirrors `FormField::getDescribedBy()`.
 */
export function getInputNoteIds( field: FormField ): string[] {
	return [
		getInputMaskHint( field ) !== undefined ? `field-${field.name}-mask` : undefined,
		showsCounter( field ) ? `field-${field.name}-counter` : undefined,
		field.affix_description ? `field-${field.name}-affix` : undefined,
	].filter( ( id ): id is string => id !== undefined );
}

/**
 * Adds note IDs to an input component's `aria-describedby` (since 1.6.0).
 *
 * The input components describe their input by its hint and error,
 * `{id}-hint` and `{id}-error`; passing `aria-describedby` replaces that, so
 * those IDs are kept here, which needs a known input ID.
 */
export function withInputNotes( props: InputLabelProps, field: FormField, error: string | undefined, noteIds: string[] ): InputLabelProps {
	if ( noteIds.length === 0 ) {
		return props;
	}

	const id = props.id ?? `field-${field.name}`;
	const own = props['aria-describedby'] ?? [
		props.hint && !error ? `${id}-hint` : undefined,
		error ? `${id}-error` : undefined,
	].filter( Boolean ).join( ' ' );

	return { ...props, id, 'aria-describedby': [own, ...noteIds].filter( Boolean ).join( ' ' ) };
}

/** Label, hint and ID props for a single checkbox or toggle, whose label sits beside the box. */
export interface CheckLabelProps {
	id?: string;
	label?: string;
	'aria-label'?: string;
	'aria-describedby'?: string;
	hint?: string;
}

/**
 * Gets the label props for a single checkbox or toggle.
 *
 * Its label stays beside the box in a custom layout, except a hidden label,
 * which the layout renders `sr-only` (pass `renderLabel` when the
 * placement is `hidden`). The layout renders the description.
 */
export function getCheckLabelProps(
	field: FormField,
	layout: FieldLayout,
	displayConfig: DisplayConfig,
	error?: string,
): CheckLabelProps {
	if ( layout.custom ) {
		return {
			id: layout.inputId,
			label: layout.label === 'hidden' ? undefined : ( field.label ?? undefined ),
			'aria-describedby': getLayoutDescribedBy( field, layout, error ),
		};
	}

	return {
		label: displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined,
		'aria-label': displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined,
		hint: field.help_text ?? undefined,
	};
}
