/**
 * Choice-based field components.
 *
 * Renders select, radio, checkbox, toggle, checkbox_group, and select_multiple
 * fields using @artisanpack-ui/react components. A read-only choice is marked
 * `aria-readonly` and its native inputs are disabled. Radio and checkbox
 * groups can offer an "Other" choice that reveals a text input.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { Checkbox, Select, Toggle } from '@artisanpack-ui/react/form';

import {
	allowsOtherChoice,
	getCheckLabelProps,
	getFieldLayout,
	getInputLabelProps,
	getLayoutDescribedBy,
	getOtherChoiceLabel,
	getToggleColor,
	getToggleSizeClass,
	isOtherChoiceSelected,
	isReadOnlyField,
	OTHER_CHOICE_MAX_LENGTH,
	OTHER_CHOICE_VALUE,
} from '../../../shared/fieldSettings';
import type { FieldLayout as FieldLayoutSettings } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import type { FieldOption, OptionsFieldConfig } from '../../../types/artisanpack-forms';
import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/**
 * Maps form field options to the format expected by Select/Radio components.
 */
function mapOptions( field: FieldComponentProps['field'] ): FieldOption[] {
	const config = field.field_config as OptionsFieldConfig | null;

	return field.options ?? config?.options ?? [];
}

/**
 * Normalizes a value to a boolean for checkbox/toggle fields.
 * Handles string representations from API responses correctly.
 */
function isChecked( value: unknown ): boolean {
	if ( typeof value === 'boolean' ) {
		return value;
	}

	if ( typeof value === 'number' ) {
		return value === 1;
	}

	if ( typeof value === 'string' ) {
		return ['true', '1', 'yes', 'on'].includes( value.toLowerCase() );
	}

	return false;
}

/**
 * Gets the IDs a choice group's label, description and error use.
 *
 * In a custom layout the FieldLayout renders the label and description;
 * otherwise the group renders them itself.
 */
function getGroupIds( field: FieldComponentProps['field'], layout: FieldLayoutSettings, error?: string ) {
	const labelId = field.label ? ( layout.custom ? layout.labelId : `${field.name}-label` ) : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text && !error ? `${field.name}-hint` : undefined );

	return { labelId, errorId, hintId, describedBy: [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined };
}

/** Props for the OtherChoiceInput component. */
interface OtherChoiceInputProps {
	field: FieldComponentProps['field'];
	value: string;
	error?: string;
	readOnly: boolean;
	onChange?: ( text: string ) => void;
}

/**
 * The text input revealed by a selected "Other" choice. It follows the
 * choices, so it is the next tab stop after the "Other" radio or checkbox.
 *
 * @since 1.6.0
 */
function OtherChoiceInput( { field, value, error, readOnly, onChange }: OtherChoiceInputProps ) {
	const inputId = `field-${field.name}-other-text`;

	return (
		<div className="mt-2">
			<label htmlFor={inputId} className="sr-only">
				{t( ':label, please specify', { label: getOtherChoiceLabel( field ) } )}
			</label>
			<input
				id={inputId}
				type="text"
				className={`input input-bordered w-full ${error ? 'input-error' : ''}`}
				value={value}
				maxLength={OTHER_CHOICE_MAX_LENGTH}
				placeholder={t( 'Please specify' )}
				required
				aria-required="true"
				aria-invalid={error ? true : undefined}
				aria-describedby={error ? `${field.name}-error` : undefined}
				readOnly={readOnly}
				onChange={( e ) => onChange?.( e.target.value )}
			/>
		</div>
	);
}

/**
 * Renders a select dropdown field.
 */
export function SelectField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const options = mapOptions( field );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Select
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? 'Select an option...'}
				error={error}
				required={field.is_required}
				className={field.css_classes ?? undefined}
				options={options}
				optionValue="value"
				optionLabel="label"
				onChange={( e ) => onChange( e.target.value )}
				disabled={readOnly}
				aria-readonly={readOnly ? 'true' : undefined}
				autoComplete={field.autocomplete ?? undefined}
			/>
		</FieldLayout>
	);
}

/**
 * Renders a radio button group field.
 *
 * Native radios in a labelled `radiogroup`, so arrow keys move between them.
 * An "Other" choice comes last and reveals a text input.
 */
export function RadioField( { field, value, error, onChange, displayConfig, otherValue = '', onOtherChange }: FieldComponentProps ) {
	const options = mapOptions( field );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const { labelId, errorId, hintId, describedBy } = getGroupIds( field, layout, error );
	const selected = String( value ?? '' );
	const allowsOther = allowsOtherChoice( field );
	const otherSelected = allowsOther && selected === OTHER_CHOICE_VALUE;
	const choices = allowsOther ? [...options, { value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( field ) }] : options;

	const radio = (
		<div className="fieldset">
			{!layout.custom && field.label && (
				<span id={labelId} className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1">*</span>}
				</span>
			)}
			<div
				role="radiogroup"
				aria-labelledby={labelId}
				aria-label={labelId ? undefined : field.name}
				aria-describedby={describedBy}
				aria-required={field.is_required ? 'true' : undefined}
				aria-invalid={error ? true : undefined}
				className={`flex flex-col gap-2 ${field.css_classes ?? ''}`}
			>
				{choices.map( ( option, index ) => {
					const optionId = `field-${field.name}-${option.value === OTHER_CHOICE_VALUE ? 'other' : index}`;

					return (
						<label key={option.value} htmlFor={optionId} className="flex items-center gap-2 cursor-pointer">
							<input
								id={optionId}
								type="radio"
								name={field.name}
								value={option.value}
								className="radio radio-primary"
								checked={selected === option.value}
								required={field.is_required && index === 0}
								disabled={readOnly}
								aria-controls={option.value === OTHER_CHOICE_VALUE && otherSelected ? `field-${field.name}-other-text` : undefined}
								onChange={() => onChange( option.value )}
							/>
							<span>{option.label}</span>
						</label>
					);
				} )}
			</div>
			{otherSelected && (
				<OtherChoiceInput field={field} value={otherValue} error={error} readOnly={readOnly} onChange={onOtherChange} />
			)}
			{!layout.custom && hintId && (
				<p id={hintId} className="fieldset-label">{field.help_text}</p>
			)}
			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}
		</div>
	);

	// A read-only group is wrapped in an element that carries `aria-readonly`;
	// `disabled` reaches every native radio.
	return (
		<FieldLayout field={field} layout={layout} group>
			{readOnly ? <div role="group" aria-readonly="true">{radio}</div> : radio}
		</FieldLayout>
	);
}

/**
 * Renders a single checkbox field.
 */
export function CheckboxField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const checked = isChecked( value );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout} renderLabel={layout.label === 'hidden'}>
			<Checkbox
				{...getCheckLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				checked={checked}
				error={error}
				required={field.is_required}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.checked )}
				disabled={readOnly}
				aria-readonly={readOnly ? 'true' : undefined}
				right
			/>
		</FieldLayout>
	);
}

/**
 * Renders a checkbox group (multi-select checkboxes) field.
 *
 * An "Other" choice comes last and reveals a text input.
 */
export function CheckboxGroupField( { field, value, error, onChange, displayConfig, otherValue = '', onOtherChange }: FieldComponentProps ) {
	const options = mapOptions( field );
	const selectedValues = Array.isArray( value ) ? value.map( String ) : [];
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const { labelId, errorId, hintId, describedBy } = getGroupIds( field, layout, error );
	const allowsOther = allowsOtherChoice( field );
	const otherSelected = allowsOther && isOtherChoiceSelected( selectedValues );
	const choices = allowsOther ? [...options, { value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( field ) }] : options;

	const handleChange = ( optionValue: string, checked: boolean ) => {
		const updated = checked
			? [...selectedValues, optionValue]
			: selectedValues.filter( ( v ) => v !== optionValue );

		onChange( updated );
	};

	return (
		<FieldLayout field={field} layout={layout} group>
			<fieldset
				className="fieldset"
				aria-labelledby={layout.custom ? labelId : undefined}
				aria-describedby={describedBy}
				aria-readonly={readOnly ? 'true' : undefined}
			>
				{!layout.custom && field.label && (
					<legend className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</legend>
				)}
				<div className={`flex flex-col gap-2 ${field.css_classes ?? ''}`}>
					{choices.map( ( option ) => (
						<Checkbox
							key={option.value}
							id={option.value === OTHER_CHOICE_VALUE ? `field-${field.name}-other` : undefined}
							label={option.label}
							name={`${field.name}[]`}
							checked={selectedValues.includes( option.value )}
							onChange={( e ) => handleChange( option.value, e.target.checked )}
							disabled={readOnly}
							aria-controls={option.value === OTHER_CHOICE_VALUE && otherSelected ? `field-${field.name}-other-text` : undefined}
							right
						/>
					) )}
				</div>
				{otherSelected && (
					<OtherChoiceInput field={field} value={otherValue} error={error} readOnly={readOnly} onChange={onOtherChange} />
				)}
				{!layout.custom && hintId && (
					<p id={hintId} className="fieldset-label">{field.help_text}</p>
				)}
				{error && (
					<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
				)}
			</fieldset>
		</FieldLayout>
	);
}

/**
 * Renders a multi-select dropdown field.
 */
export function SelectMultipleField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const options = mapOptions( field );
	const selectedValues = Array.isArray( value ) ? value.map( String ) : [];
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const legendId = field.label && !layout.custom ? `${field.name}-label` : undefined;
	const hintId = field.help_text && !error ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = layout.custom
		? [getLayoutDescribedBy( field, layout ), errorId].filter( Boolean ).join( ' ' ) || undefined
		: [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	const handleChange = ( e: React.ChangeEvent<HTMLSelectElement> ) => {
		const selected = Array.from( e.target.selectedOptions, ( opt ) => opt.value );
		onChange( selected );
	};

	return (
		<FieldLayout field={field} layout={layout}>
			<fieldset className="fieldset">
				{legendId && (
					<legend
						id={legendId}
						className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}
					>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</legend>
				)}
				<select
					id={layout.custom ? layout.inputId : undefined}
					name={field.name}
					multiple
					value={selectedValues}
					onChange={handleChange}
					required={field.is_required}
					className={`select select-bordered w-full min-h-32 ${field.css_classes ?? ''} ${error ? 'select-error' : ''}`}
					aria-invalid={error ? true : undefined}
					aria-labelledby={legendId}
					aria-describedby={describedBy}
					disabled={readOnly}
					aria-readonly={readOnly ? 'true' : undefined}
				>
					{options.map( ( option ) => (
						<option key={option.value} value={option.value}>
							{option.label}
						</option>
					) )}
				</select>
				{!layout.custom && field.help_text && !error && (
					<p id={hintId} className="fieldset-label">{field.help_text}</p>
				)}
				{error && (
					<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
				)}
			</fieldset>
		</FieldLayout>
	);
}

/**
 * Renders a toggle (switch) field.
 *
 * A checkbox with `role="switch"`, whose checked state is announced as on or
 * off. Size and color come from the field's settings. A read-only toggle is
 * marked `aria-readonly` and disabled.
 *
 * @since 1.6.0
 */
export function ToggleField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const checked = isChecked( value );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout} renderLabel={layout.label === 'hidden'}>
			<Toggle
				{...getCheckLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				checked={checked}
				error={error}
				required={field.is_required}
				color={getToggleColor( field )}
				className={[getToggleSizeClass( field ), field.css_classes ?? ''].filter( Boolean ).join( ' ' ) || undefined}
				onChange={( e ) => onChange( e.target.checked )}
				disabled={readOnly}
				aria-readonly={readOnly ? 'true' : undefined}
				right
			/>
		</FieldLayout>
	);
}
