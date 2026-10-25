/**
 * Date and time fields filled in with separate inputs.
 *
 * A date field in the `dropdowns` or `fields` mode shows month, day and year
 * dropdowns or number inputs, ordered by its date format; a time field in
 * the `dropdowns` mode shows hour and minute dropdowns, with AM/PM on a
 * 12-hour clock. Each group is a `<fieldset>` whose `<legend>` is the field
 * label, and the parts are combined into the `YYYY-MM-DD` or 24-hour `HH:MM`
 * value the field submits. Mirrors the Blade `date` and `time` partials.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { getDateBounds } from '../../../shared/dateConstraints';
import {
	combineDate,
	combineTime,
	DATE_PART_ORDER,
	getDateFormat,
	getDateInputMode,
	getMinuteOptions,
	getMonthNames,
	getTimeFormat,
	getYearOptions,
	splitDate,
	splitTime,
} from '../../../shared/dateTime';
import type { DatePart, DatePartValues, TimePartValues } from '../../../shared/dateTime';
import { getFieldLayout, getTimeStep, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import type { FormField } from '../../../types/artisanpack-forms';

import type { FieldComponentProps } from './types';

/** Props for the PartsFieldset component. */
interface PartsFieldsetProps extends Pick<FieldComponentProps, 'field' | 'error' | 'displayConfig'> {
	children: ReactNode;
}

/**
 * Renders the fieldset around a date or time field's inputs: the legend, the
 * description above or below them and the error.
 */
function PartsFieldset( { field, error, displayConfig, children }: PartsFieldsetProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const hintId = field.help_text ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const hint = hintId ? <p id={hintId} className="fieldset-label">{field.help_text}</p> : null;

	return (
		<fieldset id={`field-${field.name}`} className={`fieldset ${field.css_classes ?? ''}`} aria-describedby={describedBy}>
			{field.label && (
				<legend className={`fieldset-legend ${hiddenLabel ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
				</legend>
			)}

			{layout.description === 'above' && hint}

			<div className="flex flex-wrap gap-3">{children}</div>

			{layout.description !== 'above' && hint}

			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}
		</fieldset>
	);
}

/** Props for one labelled part select. */
interface PartSelectProps {
	id: string;
	label: string;
	value: string;
	options: Array<{ value: string; label: string }>;
	field: FormField;
	error?: string;
	placeholder?: boolean;
	autoComplete?: string;
	className?: string;
	onChange: ( value: string ) => void;
}

/**
 * Renders one labelled dropdown of a date or time field.
 */
function PartSelect( { id, label, value, options, field, error, placeholder = true, autoComplete, className = 'w-24', onChange }: PartSelectProps ) {
	return (
		<div className={`flex flex-col gap-1 ${className}`}>
			<label htmlFor={id} className="label text-sm">{label}</label>
			<select
				id={id}
				className={`select select-bordered w-full ${error ? 'select-error' : ''}`}
				value={value}
				autoComplete={autoComplete}
				disabled={isReadOnlyField( field )}
				required={field.is_required && placeholder}
				aria-required={field.is_required && placeholder ? 'true' : undefined}
				aria-invalid={error ? true : undefined}
				onChange={( e ) => onChange( e.target.value )}
			>
				{placeholder && <option value="">{label}</option>}
				{options.map( ( option ) => (
					<option key={option.value} value={option.value}>{option.label}</option>
				) )}
			</select>
		</div>
	);
}

/** The translated label of a date part. */
function datePartLabel( part: DatePart ): string {
	switch ( part ) {
		case 'month':
			return t( 'Month' );
		case 'day':
			return t( 'Day' );
		default:
			return t( 'Year' );
	}
}

/**
 * Renders a date field as month, day and year dropdowns or number inputs.
 */
export function DatePartsField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const mode = getDateInputMode( field );
	const padded = mode === 'fields';
	const [parts, setParts] = useState<DatePartValues>( () => splitDate( value, padded ) ?? { year: '', month: '', day: '' } );

	// Follow value changes from outside (a reset or prefill), keeping a half-typed date.
	useEffect( () => {
		const next = splitDate( value, padded );

		if ( next && combineDate( next ) !== combineDate( parts ) ) {
			setParts( next );
		}
	}, [value, padded, parts] );

	const update = ( part: DatePart, partValue: string ): void => {
		const next = { ...parts, [part]: partValue };

		setParts( next );
		onChange( combineDate( next ) );
	};

	const months = getMonthNames();
	const options: Record<DatePart, Array<{ value: string; label: string }>> = {
		month: months.map( ( name, index ) => ( { value: String( index + 1 ), label: name } ) ),
		day: Array.from( { length: 31 }, ( _value, index ) => ( { value: String( index + 1 ), label: String( index + 1 ) } ) ),
		year: getYearOptions( getDateBounds( field ) ).map( ( year ) => ( { value: String( year ), label: String( year ) } ) ),
	};
	const placeholders: Record<DatePart, string> = { month: t( 'MM' ), day: t( 'DD' ), year: t( 'YYYY' ) };
	const birthdate = field.autocomplete === 'bday';
	const readOnly = isReadOnlyField( field );

	return (
		<PartsFieldset field={field} error={error} displayConfig={displayConfig}>
			{DATE_PART_ORDER[getDateFormat( field )].map( ( part ) => {
				const id = `field-${field.name}-${part}`;
				const autoComplete = birthdate ? `bday-${part}` : undefined;

				if ( mode === 'dropdowns' ) {
					return (
						<PartSelect
							key={part}
							id={id}
							label={datePartLabel( part )}
							value={parts[part]}
							options={options[part]}
							field={field}
							error={error}
							autoComplete={autoComplete}
							className={part === 'month' ? 'grow basis-40' : 'w-24'}
							onChange={( partValue ) => update( part, partValue )}
						/>
					);
				}

				return (
					<div key={part} className="flex w-24 flex-col gap-1">
						<label htmlFor={id} className="label text-sm">{datePartLabel( part )}</label>
						<input
							id={id}
							type="text"
							inputMode="numeric"
							className={`input input-bordered w-full ${error ? 'input-error' : ''}`}
							value={parts[part]}
							maxLength={part === 'year' ? 4 : 2}
							placeholder={placeholders[part]}
							autoComplete={autoComplete}
							readOnly={readOnly}
							required={field.is_required}
							aria-required={field.is_required ? 'true' : undefined}
							aria-invalid={error ? true : undefined}
							onChange={( e ) => update( part, e.target.value.replace( /\D/g, '' ) )}
						/>
					</div>
				);
			} )}
		</PartsFieldset>
	);
}

/**
 * Renders a time field as hour and minute dropdowns, with AM/PM on a 12-hour clock.
 */
export function TimePartsField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const twelveHour = getTimeFormat( field ) === '12';
	const [parts, setParts] = useState<TimePartValues>( () => splitTime( value, twelveHour ) ?? { hour: '', minute: '', period: 'am' } );

	useEffect( () => {
		const next = splitTime( value, twelveHour );

		if ( next && combineTime( next, twelveHour ) !== combineTime( parts, twelveHour ) ) {
			setParts( next );
		}
	}, [value, twelveHour, parts] );

	const update = ( next: TimePartValues ): void => {
		setParts( next );
		onChange( combineTime( next, twelveHour ) );
	};

	const hours = twelveHour ? Array.from( { length: 12 }, ( _value, index ) => index + 1 ) : Array.from( { length: 24 }, ( _value, index ) => index );
	const pad = ( number: number ): string => String( number ).padStart( 2, '0' );

	return (
		<PartsFieldset field={field} error={error} displayConfig={displayConfig}>
			<PartSelect
				id={`field-${field.name}-hour`}
				label={t( 'Hour' )}
				value={parts.hour}
				options={hours.map( ( hour ) => ( { value: String( hour ), label: twelveHour ? String( hour ) : pad( hour ) } ) )}
				field={field}
				error={error}
				onChange={( hour ) => update( { ...parts, hour } )}
			/>
			<PartSelect
				id={`field-${field.name}-minute`}
				label={t( 'Minute' )}
				value={parts.minute}
				options={getMinuteOptions( getTimeStep( field ) ).map( ( minute ) => ( { value: String( minute ), label: pad( minute ) } ) )}
				field={field}
				error={error}
				onChange={( minute ) => update( { ...parts, minute } )}
			/>
			{twelveHour && (
				<PartSelect
					id={`field-${field.name}-period`}
					label={t( 'AM or PM' )}
					value={parts.period}
					options={[{ value: 'am', label: t( 'AM' ) }, { value: 'pm', label: t( 'PM' ) }]}
					field={field}
					error={error}
					placeholder={false}
					onChange={( period ) => update( { ...parts, period: period === 'pm' ? 'pm' : 'am' } )}
				/>
			)}
		</PartsFieldset>
	);
}
