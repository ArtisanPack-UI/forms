/**
 * Date field limits, disabled days and age limits.
 *
 * Mirrors `DateConstraints` and `FormField::getDateLimits()` on the server.
 * A min or max date is an absolute `YYYY-MM-DD` date or relative to today:
 * `today`, or a signed number of days, weeks, months or years such as
 * `+30 days` or `-18 years`. Months and years do not overflow (a month after
 * 31 January is the end of February). Days are read in the visitor's
 * timezone; the server reads them in the application's.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

/** A date's parts, with a 1-based month. */
interface DateParts {
	year: number;
	month: number;
	day: number;
}

/** The earliest and latest allowed dates, as `YYYY-MM-DD`; undefined when unbounded. */
export interface DateBounds {
	min?: string;
	max?: string;
}

/** Matches a relative date expression. */
const RELATIVE_PATTERN = /^(?:today|([+-])\s*(\d{1,4})\s*(day|week|month|year)s?)$/i;

/** Read a valid `YYYY-MM-DD` date's parts, or undefined. */
export function parseIsoDate( value: string ): DateParts | undefined {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value );

	if ( !match ) {
		return undefined;
	}

	const parts = { year: Number( match[1] ), month: Number( match[2] ), day: Number( match[3] ) };

	return parts.month >= 1 && parts.month <= 12 && parts.day >= 1 && parts.day <= daysInMonth( parts.year, parts.month )
		? parts
		: undefined;
}

/** Format date parts as `YYYY-MM-DD`. */
export function formatIsoDate( parts: DateParts ): string {
	return `${String( parts.year ).padStart( 4, '0' )}-${String( parts.month ).padStart( 2, '0' )}-${String( parts.day ).padStart( 2, '0' )}`;
}

/** Get the number of days in a month. */
export function daysInMonth( year: number, month: number ): number {
	return new Date( Date.UTC( year, month, 0 ) ).getUTCDate();
}

/** Get today's date in the visitor's timezone. */
function today(): DateParts {
	const now = new Date();

	return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
}

/** Add days to a date. */
function addDays( parts: DateParts, days: number ): DateParts {
	const date = new Date( Date.UTC( parts.year, parts.month - 1, parts.day + days ) );

	return { year: date.getUTCFullYear(), month: date.getUTCMonth() + 1, day: date.getUTCDate() };
}

/** Add months to a date without overflowing into the next month. */
function addMonths( parts: DateParts, months: number ): DateParts {
	const index = parts.year * 12 + ( parts.month - 1 ) + months;
	const year = Math.floor( index / 12 );
	const month = index - year * 12 + 1;

	return { year, month, day: Math.min( parts.day, daysInMonth( year, month ) ) };
}

/**
 * Parse a min or max date typed in the builder into a valid date or a
 * normalized expression (`today`, `+30 days`), or undefined when invalid.
 * Mirrors `DateConstraints::parseExpression()`.
 */
export function parseDateExpression( raw: string ): string | undefined {
	const value = raw.trim();

	if ( parseIsoDate( value ) ) {
		return value;
	}

	const match = RELATIVE_PATTERN.exec( value );

	if ( !match ) {
		return undefined;
	}

	if ( match[1] === undefined || Number( match[2] ) === 0 ) {
		return 'today';
	}

	const amount = Number( match[2] );

	return `${match[1]}${amount} ${match[3].toLowerCase()}${amount === 1 ? '' : 's'}`;
}

/** Resolve a min or max date to the `YYYY-MM-DD` date it stands for, or undefined. */
export function resolveDateExpression( expression: unknown, from: DateParts = today() ): string | undefined {
	if ( typeof expression !== 'string' ) {
		return undefined;
	}

	const parsed = parseDateExpression( expression );

	if ( parsed === undefined ) {
		return undefined;
	}

	if ( parsed !== 'today' && parseIsoDate( parsed ) ) {
		return parsed;
	}

	const match = RELATIVE_PATTERN.exec( parsed );

	if ( parsed === 'today' || !match || match[1] === undefined ) {
		return formatIsoDate( from );
	}

	const amount = ( match[1] === '-' ? -1 : 1 ) * Number( match[2] );

	switch ( match[3].toLowerCase() ) {
		case 'day':
			return formatIsoDate( addDays( from, amount ) );
		case 'week':
			return formatIsoDate( addDays( from, amount * 7 ) );
		case 'month':
			return formatIsoDate( addMonths( from, amount ) );
		default:
			return formatIsoDate( addMonths( from, amount * 12 ) );
	}
}

/** Combine limits into the latest minimum and earliest maximum. */
function combine( minimums: Array<string | undefined>, maximums: Array<string | undefined> ): DateBounds {
	const mins = minimums.filter( ( date ): date is string => date !== undefined ).sort();
	const maxs = maximums.filter( ( date ): date is string => date !== undefined ).sort();

	return { min: mins[mins.length - 1], max: maxs[0] };
}

/** Get a date field's minimum or maximum age, or undefined. */
export function getAgeLimit( field: FormField, key: 'min_age' | 'max_age' ): number | undefined {
	const raw: unknown = field.type === 'date' ? field.validation_rules?.[key] : undefined;
	const age = Number( raw );

	return raw !== null && raw !== undefined && raw !== '' && Number.isInteger( age ) && age >= 0 ? age : undefined;
}

/** Get the latest birthdate of someone at least an age, today. */
export function latestBirthdate( age: number, from: DateParts = today() ): string {
	return formatIsoDate( addMonths( from, -age * 12 ) );
}

/** Get the earliest birthdate of someone at most an age, today. */
export function earliestBirthdate( age: number, from: DateParts = today() ): string {
	return formatIsoDate( addDays( addMonths( from, -( age + 1 ) * 12 ), 1 ) );
}

/** The field types with min and max dates. */
const DATE_LIMIT_TYPES = new Set( ['date', 'datetime', 'date_range'] );

/**
 * Read bounds the server resolved (`date_limits` / `date_bounds`), or
 * undefined when it sent none.
 */
function readServerBounds( raw: unknown ): DateBounds | undefined {
	if ( !raw || typeof raw !== 'object' ) {
		return undefined;
	}

	const { min, max } = raw as { min?: unknown; max?: unknown };
	const read = ( value: unknown ): string | undefined => ( typeof value === 'string' && parseIsoDate( value ) ? value : undefined );

	return { min: read( min ), max: read( max ) };
}

/**
 * Get a date field's earliest and latest allowed dates: its min and max
 * dates narrowed by the "No past dates" or "No future dates" range. Mirrors
 * `FormField::getDateLimits()`. Date and time and date range fields take the
 * min and max dates too (since 1.6.0).
 *
 * The limits the server resolved (`field.date_limits`) are used when the
 * field has them, so "today" is the server's day, not the browser's; they
 * are worked out here otherwise, such as for the builder's preview (since
 * 1.6.0).
 */
export function getDateLimits( field: FormField ): DateBounds {
	if ( !DATE_LIMIT_TYPES.has( field.type ) ) {
		return {};
	}

	const fromServer = readServerBounds( field.date_limits );

	if ( fromServer ) {
		return fromServer;
	}

	const rules = field.validation_rules;
	const range = rules?.date_range;
	const now = resolveDateExpression( 'today' );

	return combine(
		[resolveDateExpression( rules?.min_date ), range === 'no_past' ? now : undefined],
		[resolveDateExpression( rules?.max_date ), range === 'no_future' ? now : undefined],
	);
}

/**
 * Get the bounds a date field's input offers: its limits narrowed by its
 * age limits. Used for the native `min` and `max` and the year dropdown.
 * The server's bounds (`field.date_bounds`) are used when present (since
 * 1.6.0).
 */
export function getDateBounds( field: FormField ): DateBounds {
	const fromServer = DATE_LIMIT_TYPES.has( field.type ) ? readServerBounds( field.date_bounds ) : undefined;

	if ( fromServer ) {
		return fromServer;
	}

	const limits = getDateLimits( field );
	const minAge = getAgeLimit( field, 'min_age' );
	const maxAge = getAgeLimit( field, 'max_age' );

	return combine(
		[limits.min, maxAge === undefined ? undefined : earliestBirthdate( maxAge )],
		[limits.max, minAge === undefined ? undefined : latestBirthdate( minAge )],
	);
}

/**
 * Get the birthdates a date field's age limits allow: the latest for its
 * minimum age and the earliest for its maximum age. When the server sent its
 * bounds and an age limit is what sets one of them, that bound is used, so
 * "today" is the server's day (since 1.6.0).
 */
export function getAgeBirthdates( field: FormField ): { latest?: string; earliest?: string } {
	const minAge = getAgeLimit( field, 'min_age' );
	const maxAge = getAgeLimit( field, 'max_age' );
	const bounds = readServerBounds( field.date_bounds );
	const limits = bounds ? getDateLimits( field ) : {};

	return {
		latest: minAge === undefined
			? undefined
			: bounds?.max !== undefined && bounds.max !== limits.max ? bounds.max : latestBirthdate( minAge ),
		earliest: maxAge === undefined
			? undefined
			: bounds?.min !== undefined && bounds.min !== limits.min ? bounds.min : earliestBirthdate( maxAge ),
	};
}

/** Whether a date field rejects Saturdays and Sundays. */
export function disablesWeekends( field: FormField ): boolean {
	const value: unknown = field.type === 'date' ? field.validation_rules?.disable_weekends : undefined;

	return value === true || value === 1 || value === '1' || value === 'true';
}

/** Get the specific dates a date field rejects, as valid `YYYY-MM-DD` dates. */
export function getDisabledDates( field: FormField ): string[] {
	const raw: unknown = field.type === 'date' ? field.validation_rules?.disabled_dates : undefined;
	const entries = typeof raw === 'string' ? raw.split( ',' ) : Array.isArray( raw ) ? raw : [];

	return entries
		.map( ( entry ) => ( typeof entry === 'string' ? entry.trim() : '' ) )
		.filter( ( entry ) => parseIsoDate( entry ) !== undefined );
}

/** Whether a date may be chosen: not a disabled weekend day or date. */
export function isAllowedDay( field: FormField, value: string ): boolean {
	const parts = parseIsoDate( value );

	if ( !parts ) {
		return true;
	}

	const weekday = new Date( Date.UTC( parts.year, parts.month - 1, parts.day ) ).getUTCDay();

	if ( disablesWeekends( field ) && ( weekday === 0 || weekday === 6 ) ) {
		return false;
	}

	return !getDisabledDates( field ).includes( value );
}
