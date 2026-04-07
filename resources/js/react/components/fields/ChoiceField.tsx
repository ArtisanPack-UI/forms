/**
 * Choice-based field components.
 *
 * Renders select, radio, checkbox, checkbox_group, and select_multiple
 * fields using @artisanpack-ui/react components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { Checkbox, Radio, Select, Toggle } from '@artisanpack-ui/react/form';

import type { FieldOption, OptionsFieldConfig } from '../../../types/artisanpack-forms';
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
 * Renders a select dropdown field.
 */
export function SelectField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const options = mapOptions( field );

	return (
		<Select
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? undefined ) : undefined}
			name={field.name}
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? 'Select an option...'}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			options={options}
			optionValue="value"
			optionLabel="label"
			onChange={( e ) => onChange( e.target.value )}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}

/**
 * Renders a radio button group field.
 */
export function RadioField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const options = mapOptions( field );

	return (
		<Radio
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? undefined ) : undefined}
			name={field.name}
			value={String( value ?? '' )}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			options={options}
			optionValue="value"
			optionLabel="label"
			onChange={( e ) => onChange( e.target.value )}
		/>
	);
}

/**
 * Renders a single checkbox field.
 */
export function CheckboxField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const checked = isChecked( value );

	return (
		<Checkbox
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? undefined ) : undefined}
			name={field.name}
			checked={checked}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.checked )}
			right
		/>
	);
}

/**
 * Renders a checkbox group (multi-select checkboxes) field.
 */
export function CheckboxGroupField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const options = mapOptions( field );
	const selectedValues = Array.isArray( value ) ? value.map( String ) : [];

	const handleChange = ( optionValue: string, checked: boolean ) => {
		const updated = checked
			? [...selectedValues, optionValue]
			: selectedValues.filter( ( v ) => v !== optionValue );

		onChange( updated );
	};

	const hintId = field.help_text && !error ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<fieldset className="fieldset" aria-describedby={describedBy}>
			{field.label && (
				<legend className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1">*</span>}
				</legend>
			)}
			<div className={`flex flex-col gap-2 ${field.css_classes ?? ''}`}>
				{options.map( ( option ) => (
					<Checkbox
						key={option.value}
						label={option.label}
						name={`${field.name}[]`}
						checked={selectedValues.includes( option.value )}
						onChange={( e ) => handleChange( option.value, e.target.checked )}
						right
					/>
				) )}
			</div>
			{field.help_text && !error && (
				<p id={hintId} className="fieldset-label">{field.help_text}</p>
			)}
			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}
		</fieldset>
	);
}

/**
 * Renders a multi-select dropdown field.
 */
export function SelectMultipleField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const options = mapOptions( field );
	const selectedValues = Array.isArray( value ) ? value.map( String ) : [];
	const legendId = field.label ? `${field.name}-label` : undefined;
	const hintId = field.help_text && !error ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	const handleChange = ( e: React.ChangeEvent<HTMLSelectElement> ) => {
		const selected = Array.from( e.target.selectedOptions, ( opt ) => opt.value );
		onChange( selected );
	};

	return (
		<fieldset className="fieldset">
			{field.label && (
				<legend
					id={legendId}
					className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}
				>
					{field.label}
					{field.is_required && <span className="text-error ml-1">*</span>}
				</legend>
			)}
			<select
				name={field.name}
				multiple
				value={selectedValues}
				onChange={handleChange}
				required={field.is_required}
				className={`select select-bordered w-full min-h-32 ${field.css_classes ?? ''} ${error ? 'select-error' : ''}`}
				aria-invalid={error ? true : undefined}
				aria-labelledby={legendId}
				aria-describedby={describedBy}
			>
				{options.map( ( option ) => (
					<option key={option.value} value={option.value}>
						{option.label}
					</option>
				) )}
			</select>
			{field.help_text && !error && (
				<p id={hintId} className="fieldset-label">{field.help_text}</p>
			)}
			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}
		</fieldset>
	);
}

/**
 * Renders a toggle switch field (alias for checkbox with toggle styling).
 */
export function ToggleField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const checked = isChecked( value );

	return (
		<Toggle
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? undefined ) : undefined}
			name={field.name}
			checked={checked}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.checked )}
			right
		/>
	);
}
