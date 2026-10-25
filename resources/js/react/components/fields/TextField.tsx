/**
 * Text-based field components.
 *
 * Renders text, email, phone, number, url, textarea, hidden,
 * and time input fields using @artisanpack-ui/react components. A read-only
 * field keeps its value focusable and announced via `readOnly`. Inputs expose
 * the field's `autocomplete` token (WCAG 1.3.5). An email field with
 * confirmation on asks for the address twice, a URL typed without a scheme
 * gets `https://` when the input loses focus, a textarea may use the rich
 * text editor and a time field may use dropdowns (since 1.6.0).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { useState } from 'react';
import { Input, Textarea } from '@artisanpack-ui/react/form';

import { getSubLabelPlacement } from '../../../shared/composite';
import { getLengthLimit } from '../../../shared/counter';
import { getTimeInputMode } from '../../../shared/dateTime';
import {
	getConfirmationKey,
	getFieldLayout,
	getInputLabelProps,
	getInputNoteIds,
	getLayoutDescribedBy,
	getTextareaRows,
	getTimeStep,
	isReadOnlyField,
	requiresEmailConfirmation,
	withInputNotes,
} from '../../../shared/fieldSettings';
import type { FieldLayout as FieldLayoutState } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { applyMask, getInputMask } from '../../../shared/mask';
import { richTextToPlainText, usesRichText } from '../../../shared/richText';
import { getAllowedUrlSchemes, withUrlScheme } from '../../../shared/url';
import { getAffixes, getNumberFormat, isIntegerOnly } from '../../../shared/numberFormat';
import {
	composePhoneNumber,
	findPhoneCountry,
	getPhoneCountries,
	getPhoneDefaultCountry,
	splitPhoneNumber,
} from '../../../shared/phone';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';

import { TimePartsField } from './DateTimeParts';
import { FieldLayout } from './FieldLayout';
import { InputNotes } from './InputNotes';
import { RichTextEditor } from './RichTextEditor';
import type { FieldComponentProps } from './types';

/**
 * Gets an input's label props with its notes (mask format, counter, prefix
 * and suffix) added to `aria-describedby` (since 1.6.0).
 */
function getNotedLabelProps( field: FormField, layout: FieldLayoutState, displayConfig: DisplayConfig, error?: string ) {
	return withInputNotes(
		getInputLabelProps( field, layout, displayConfig, error ),
		field,
		error,
		getInputNoteIds( field ),
	);
}

/**
 * Renders a single-line text input field.
 */
export function TextField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const text = String( value ?? '' );
	const mask = getInputMask( field );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getNotedLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="text"
				value={text}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( mask ? applyMask( e.target.value, mask, text ) : e.target.value )}
				minLength={getLengthLimit( field, 'min' )}
				maxLength={getLengthLimit( field, 'max' )}
				pattern={field.validation_rules?.pattern ?? undefined}
			/>
			<InputNotes field={field} value={text} />
		</FieldLayout>
	);
}

/**
 * Renders an email input field, or the email and confirmation pair when the
 * field asks for the address twice.
 */
export function EmailField( props: FieldComponentProps ) {
	const { field, value, error, onChange, displayConfig } = props;

	if ( requiresEmailConfirmation( field ) ) {
		return <ConfirmedEmailField {...props} />;
	}

	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="email"
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
			/>
		</FieldLayout>
	);
}

/**
 * Renders an email field with confirmation: a `<fieldset>` whose `<legend>`
 * is the field label, holding the email input and a confirm input submitted
 * as `{name}_confirmation`, each with its own sub-label. A mismatch is the
 * field's error, shown once under the pair. Pasting is never blocked.
 * Mirrors the Blade `email` partial.
 *
 * @since 1.6.0
 */
function ConfirmedEmailField( { field, value, error, onChange, displayConfig, confirmValue = '', onConfirmChange }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const subLabelPlacement = getSubLabelPlacement( field );
	const labels = field.email_confirmation;
	const readOnly = isReadOnlyField( field );
	const hintId = field.help_text ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const hint = hintId ? <p id={hintId} className="fieldset-label">{field.help_text}</p> : null;
	const inputs = [
		{
			id: `field-${field.name}`,
			name: field.name,
			label: labels?.email ?? '',
			placeholder: field.placeholder,
			value: String( value ?? '' ),
			change: ( text: string ) => onChange( text ),
		},
		{
			id: `field-${getConfirmationKey( field )}`,
			name: getConfirmationKey( field ),
			label: labels?.confirm ?? '',
			placeholder: labels?.confirm_placeholder ?? null,
			value: confirmValue,
			change: ( text: string ) => onConfirmChange?.( text ),
		},
	];

	return (
		<fieldset id={`fieldset-${field.name}`} className={`fieldset ${field.css_classes ?? ''}`} aria-describedby={describedBy}>
			{field.label && (
				<legend className={`fieldset-legend ${hiddenLabel ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
				</legend>
			)}

			{layout.description === 'above' && hint}

			<div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
				{inputs.map( ( input ) => {
					const label = (
						<label
							htmlFor={input.id}
							className={subLabelPlacement === 'below' ? 'fieldset-label text-xs' : `label text-sm ${subLabelPlacement === 'hidden' ? 'sr-only' : ''}`}
						>
							{input.label}
						</label>
					);

					return (
						<div key={input.id} className="flex flex-col gap-1">
							{subLabelPlacement !== 'below' && label}
							<input
								id={input.id}
								type="email"
								name={input.name}
								className={`input input-bordered w-full ${error ? 'input-error' : ''}`}
								value={input.value}
								placeholder={input.placeholder ?? undefined}
								autoComplete={field.autocomplete ?? undefined}
								readOnly={readOnly}
								required={field.is_required}
								aria-required={field.is_required ? 'true' : undefined}
								aria-invalid={error ? true : undefined}
								aria-describedby={errorId}
								onChange={( e ) => input.change( e.target.value )}
							/>
							{subLabelPlacement === 'below' && label}
						</div>
					);
				} )}
			</div>

			{layout.description !== 'above' && hint}

			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}
		</fieldset>
	);
}

/**
 * Renders a phone input field.
 */
export function PhoneField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const text = String( value ?? '' );
	const mask = getInputMask( field );
	const readOnly = isReadOnlyField( field );
	const countries = getPhoneCountries( field );
	const [countryCode, setCountryCode] = useState( () => findPhoneCountry( text, countries, getPhoneDefaultCountry( field ) )?.value );
	const country = countries.find( ( entry ) => entry.value === countryCode );
	const national = splitPhoneNumber( text, country );

	const input = (
		<Input
			{...getNotedLabelProps( field, layout, displayConfig, error )}
			name={field.name}
			type="tel"
			value={national}
			placeholder={field.placeholder ?? undefined}
			error={error}
			required={field.is_required}
			autoComplete={field.autocomplete ?? undefined}
			readOnly={readOnly}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( composePhoneNumber( mask ? applyMask( e.target.value, mask, national ) : e.target.value, country ) )}
			pattern={field.validation_rules?.pattern ?? undefined}
		/>
	);

	return (
		<FieldLayout field={field} layout={layout}>
			{countries.length > 0 ? (
				<div className="flex items-end gap-2">
					{/* The selected country's calling code is added to a number typed without one (since 1.6.0). */}
					<select
						id={`field-${field.name}-country`}
						className="select select-bordered w-auto max-w-48"
						value={countryCode ?? ''}
						autoComplete="country"
						aria-label={t( ':field country', { field: field.label ?? field.name } )}
						disabled={readOnly}
						onChange={( e ) => {
							const next = countries.find( ( entry ) => entry.value === e.target.value );

							setCountryCode( next?.value );
							onChange( composePhoneNumber( national, next ) );
						}}
					>
						{countries.map( ( entry ) => (
							<option key={entry.value} value={entry.value}>{entry.label}</option>
						) )}
					</select>
					<div className="grow">{input}</div>
				</div>
			) : input}
			<InputNotes field={field} value={national} />
		</FieldLayout>
	);
}

/**
 * Renders a number input field.
 */
export function NumberField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const text = String( value ?? '' );
	const format = getNumberFormat( field );
	const integerOnly = isIntegerOnly( field );
	const { prefix, suffix } = getAffixes( field );

	// A formatted number is typed into a text input with a numeric keypad (since 1.6.0).
	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getNotedLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type={format ? 'text' : 'number'}
				inputMode={format ? ( integerOnly ? 'numeric' : 'decimal' ) : undefined}
				value={text}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				prefix={prefix}
				suffix={suffix}
				onChange={( e ) => onChange( e.target.value )}
				min={format ? undefined : field.validation_rules?.min}
				max={format ? undefined : field.validation_rules?.max}
				step={format ? undefined : field.validation_rules?.step ?? ( integerOnly ? 1 : undefined )}
			/>
			<InputNotes field={field} value={text} />
		</FieldLayout>
	);
}

/**
 * Renders a URL input field.
 */
export function UrlField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="url"
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? 'https://'}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				onBlur={( e ) => {
					const withScheme = withUrlScheme( e.target.value, getAllowedUrlSchemes( field ) );

					if ( withScheme !== e.target.value ) {
						onChange( withScheme );
					}
				}}
			/>
		</FieldLayout>
	);
}

/**
 * Renders a multi-line textarea field, or the rich text editor when the
 * field uses it (since 1.6.0).
 */
export function TextareaField( props: FieldComponentProps ) {
	const { field, value, error, onChange, displayConfig } = props;

	if ( usesRichText( field ) ) {
		return <RichTextareaField {...props} />;
	}

	const layout = getFieldLayout( field, displayConfig );
	const text = String( value ?? '' );

	return (
		<FieldLayout field={field} layout={layout}>
			<Textarea
				{...getNotedLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				value={text}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				minLength={getLengthLimit( field, 'min' )}
				maxLength={getLengthLimit( field, 'max' )}
				rows={getTextareaRows( field )}
			/>
			<InputNotes field={field} value={text} />
		</FieldLayout>
	);
}

/**
 * Renders a textarea's rich text editor with its label, description, error
 * and counter. The editor is labelled by the label's ID, like a group.
 *
 * @since 1.6.0
 */
function RichTextareaField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const html = String( value ?? '' );
	const labelHidden = displayConfig.label_position === 'hidden';
	const labelId = field.label ? ( layout.custom ? layout.labelId : `${field.name}-label` ) : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const hintId = !layout.custom && field.help_text ? `${field.name}-hint` : undefined;
	const describedBy = [
		layout.custom ? getLayoutDescribedBy( field, layout ) : hintId,
		errorId,
		...getInputNoteIds( field ),
	].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<FieldLayout field={field} layout={layout} group>
			<div className="fieldset">
				{!layout.custom && field.label && (
					<span id={labelId} className={`fieldset-legend ${labelHidden ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</span>
				)}
				<RichTextEditor
					field={field}
					value={html}
					error={error}
					labelId={labelId}
					describedBy={describedBy}
					onChange={( next ) => onChange( next )}
				/>
				{hintId && <p id={hintId} className="fieldset-label">{field.help_text}</p>}
				{error && <p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>}
				<InputNotes field={field} value={richTextToPlainText( html )} />
			</div>
		</FieldLayout>
	);
}

/**
 * Renders a hidden input field.
 */
export function HiddenField( { field, value }: Pick<FieldComponentProps, 'field' | 'value'> ) {
	return (
		<input
			type="hidden"
			name={field.name}
			value={String( value ?? '' )}
			readOnly
		/>
	);
}

/**
 * Renders a time input field, or hour and minute dropdowns in the
 * `dropdowns` mode (since 1.6.0).
 */
export function TimeField( props: FieldComponentProps ) {
	const { field, value, error, onChange, displayConfig } = props;

	if ( getTimeInputMode( field ) === 'dropdowns' ) {
		return <TimePartsField {...props} />;
	}

	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="time"
				value={String( value ?? '' )}
				error={error}
				required={field.is_required}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				min={field.validation_rules?.min_time ?? undefined}
				max={field.validation_rules?.max_time ?? undefined}
				step={getTimeStep( field )}
			/>
		</FieldLayout>
	);
}
