/**
 * Date and time input modes and display formats.
 *
 * Mirrors `DateTimeFormat` on the server. Dates are always submitted as
 * `YYYY-MM-DD` and times as 24-hour `HH:MM`, whichever input the field uses;
 * the date format orders the month, day and year inputs, and the time format
 * picks the clock of the hour dropdown.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { DateFormat, DateInputMode, FormField, TimeFormat, TimeInputMode } from '../types/artisanpack-forms';

import type { DateBounds } from './dateConstraints';

/** One part of a date: its month, day or year. */
export type DatePart = 'month' | 'day' | 'year';

/** A date's parts as the inputs hold them (strings, possibly blank). */
export type DatePartValues = Record<DatePart, string>;

/** A time's dropdown values: the hour on the field's clock, the minute and AM/PM. */
export interface TimePartValues {
	hour: string;
	minute: string;
	period: 'am' | 'pm';
}

/** The order of the month, day and year inputs for each date format. */
export const DATE_PART_ORDER: Readonly<Record<DateFormat, DatePart[]>> = {
	mdy: ['month', 'day', 'year'],
	dmy: ['day', 'month', 'year'],
	ymd: ['year', 'month', 'day'],
};

/** How many years before and after this year a year dropdown offers when the field sets no limits. */
export const YEAR_SPAN = { before: 100, after: 10 } as const;

/** Get how a date field shows dates and orders its inputs. */
export function getDateFormat( field: FormField ): DateFormat {
	const format = field.field_config?.date_format;

	return format === 'mdy' || format === 'dmy' ? format : 'ymd';
}

/** Get how a date field is filled in. */
export function getDateInputMode( field: FormField ): DateInputMode {
	const mode = field.type === 'date' ? field.field_config?.date_input : undefined;

	return mode === 'dropdowns' || mode === 'fields' ? mode : 'picker';
}

/** Get whether a time field shows a 24-hour or 12-hour clock. */
export function getTimeFormat( field: FormField ): TimeFormat {
	return String( field.field_config?.time_format ?? '' ) === '12' ? '12' : '24';
}

/** Get how a time field is filled in. */
export function getTimeInputMode( field: FormField ): TimeInputMode {
	return field.type === 'time' && field.field_config?.time_input === 'dropdowns' ? 'dropdowns' : 'picker';
}

/** Get the month names in the visitor's language, from January. */
export function getMonthNames( locale?: string ): string[] {
	const formatter = new Intl.DateTimeFormat( locale, { month: 'long', timeZone: 'UTC' } );

	return Array.from( { length: 12 }, ( _value, index ) => formatter.format( new Date( Date.UTC( 2000, index, 1 ) ) ) );
}

/** Get the years a year dropdown offers, latest first, within the field's bounds. */
export function getYearOptions( bounds: DateBounds ): number[] {
	const year = new Date().getFullYear();
	const first = bounds.min ? Number( bounds.min.slice( 0, 4 ) ) : year - YEAR_SPAN.before;
	const last = bounds.max ? Number( bounds.max.slice( 0, 4 ) ) : year + YEAR_SPAN.after;

	return first > last ? [] : Array.from( { length: last - first + 1 }, ( _value, index ) => last - index );
}

/** Get the minutes a minute dropdown offers for a time field's step in seconds. */
export function getMinuteOptions( step?: number ): number[] {
	const interval = step !== undefined && step >= 60 && step % 60 === 0 ? step / 60 : 1;

	if ( interval >= 60 ) {
		return [0];
	}

	return Array.from( { length: Math.ceil( 60 / interval ) }, ( _value, index ) => index * interval );
}

/**
 * Split a `YYYY-MM-DD` value into the parts the inputs show. Dropdowns hold
 * unpadded numbers (`3`); number fields keep the padding (`03`).
 */
export function splitDate( value: unknown, padded: boolean ): DatePartValues | undefined {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( typeof value === 'string' ? value : '' );

	if ( !match ) {
		return value === null || value === undefined || value === '' ? { year: '', month: '', day: '' } : undefined;
	}

	return padded
		? { year: match[1], month: match[2], day: match[3] }
		: { year: match[1], month: String( Number( match[2] ) ), day: String( Number( match[3] ) ) };
}

/**
 * Combine the inputs into the submitted `YYYY-MM-DD` value. Blank when every
 * part is blank; an incomplete date keeps its gaps so validation rejects it.
 */
export function combineDate( parts: DatePartValues ): string {
	const year = parts.year.trim();
	const month = parts.month.trim();
	const day = parts.day.trim();

	if ( year === '' && month === '' && day === '' ) {
		return '';
	}

	return `${year === '' ? '' : year.padStart( 4, '0' )}-${month === '' ? '' : month.padStart( 2, '0' )}-${day === '' ? '' : day.padStart( 2, '0' )}`;
}

/** Split an `HH:MM` value into the dropdown values on the field's clock. */
export function splitTime( value: unknown, twelveHour: boolean ): TimePartValues | undefined {
	const match = /^(\d{2}):(\d{2})(?::\d{2})?$/.exec( typeof value === 'string' ? value : '' );

	if ( !match ) {
		return value === null || value === undefined || value === '' ? { hour: '', minute: '', period: 'am' } : undefined;
	}

	const hour = Number( match[1] );

	return {
		hour: String( twelveHour ? ( hour % 12 === 0 ? 12 : hour % 12 ) : hour ),
		minute: String( Number( match[2] ) ),
		period: hour < 12 ? 'am' : 'pm',
	};
}

/** Combine the dropdown values into the submitted 24-hour `HH:MM` value. */
export function combineTime( parts: TimePartValues, twelveHour: boolean ): string {
	if ( parts.hour === '' && parts.minute === '' ) {
		return '';
	}

	let hour = parts.hour === '' ? undefined : Number( parts.hour );

	if ( hour !== undefined && twelveHour ) {
		hour = ( hour % 12 ) + ( parts.period === 'pm' ? 12 : 0 );
	}

	return `${hour === undefined ? '' : String( hour ).padStart( 2, '0' )}:${parts.minute === '' ? '' : parts.minute.padStart( 2, '0' )}`;
}
