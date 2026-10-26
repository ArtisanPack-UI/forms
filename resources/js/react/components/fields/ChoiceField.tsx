/**
 * Choice-based field components.
 *
 * Renders select, radio, checkbox, toggle, checkbox_group, and select_multiple
 * fields using @artisanpack-ui/react components. A read-only choice is marked
 * `aria-readonly` and its native inputs are disabled. Radio and checkbox
 * groups can offer an "Other" choice that reveals a text input, arrange their
 * choices in columns or inline and show them in a random order; a checkbox
 * group can offer "Select All". A searchable select or multi-select renders
 * a combobox (since 1.6.0). Radio and checkbox groups can show their choices
 * as image cards, each named by its label (since 1.6.0).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

import { Checkbox, Select, Toggle } from '@artisanpack-ui/react/form';

import {
	getChoiceImageAlt,
	getChoiceImageUrl,
	getChoiceLayoutClasses,
	getFieldOptions,
	getRenderOptions,
	isSearchableField,
	offersSelectAll,
	showsImageChoices,
} from '../../../shared/choices';

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
import { Combobox } from './Combobox';
import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';
import type { FieldOption } from '../../../types/artisanpack-forms';

/** Classes of an image choice card, mirroring the Blade `image-choice` partial (since 1.6.0). */
const IMAGE_CHOICE_CLASSES = 'flex cursor-pointer flex-col overflow-hidden rounded-box border-2 border-base-300 bg-base-100 transition-colors hover:border-primary/50 has-[:checked]:border-primary has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60';

/** Props for one image choice card. */
interface ImageChoiceProps {
	option: FieldOption;
	/** The label's ID, which the input's `aria-labelledby` names. */
	labelId: string;
	/** The radio or checkbox. */
	children: ReactNode;
}

/**
 * Renders one image choice (since 1.6.0): the option's image above its input
 * and label. The input is named by the label alone, so the label stays the
 * accessible name; the image's alt text describes what it shows.
 */
function ImageChoice( { option, labelId, children }: ImageChoiceProps ) {
	const image = getChoiceImageUrl( option );

	return (
		<label className={IMAGE_CHOICE_CLASSES}>
			{image
				? <img src={image} alt={getChoiceImageAlt( option )} className="aspect-square w-full bg-base-200 object-cover" loading="lazy" />
				: <span className="block aspect-square w-full bg-base-200" aria-hidden="true" />}
			<span className="flex items-center gap-2 p-3">
				{children}
				<span id={labelId} className="label-text">{option.label}</span>
			</span>
		</label>
	);
}

/**
 * Normalizes a value to a boolean for checkbox/toggle fields.
 * Handles string representations from API responses correctly.
 */
export function isChecked( value: unknown ): boolean {
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
 * Renders a searchable select or multi-select as a combobox, with its own
 * label, hint and error unless a custom layout places them.
 *
 * @since 1.6.0
 */
function SearchableSelect( { field, value, error, onChange, displayConfig, multiple }: FieldComponentProps & { multiple: boolean } ) {
	const layout = getFieldLayout( field, displayConfig );
	const inputId = layout.inputId;
	const labelId = field.label && !layout.custom ? `${field.name}-label` : undefined;
	const hintId = !layout.custom && field.help_text && !error ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = layout.custom
		? [getLayoutDescribedBy( field, layout ), errorId].filter( Boolean ).join( ' ' ) || undefined
		: [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<FieldLayout field={field} layout={layout}>
			<div className="fieldset">
				{labelId && (
					<label
						id={labelId}
						htmlFor={inputId}
						className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}
					>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</label>
				)}
				<Combobox
					id={inputId}
					options={getFieldOptions( field )}
					value={value}
					multiple={multiple}
					onChange={onChange}
					placeholder={field.placeholder ?? undefined}
					required={field.is_required}
					readOnly={isReadOnlyField( field )}
					invalid={Boolean( error )}
					describedBy={describedBy}
					listLabel={field.label ?? field.name}
					className={field.css_classes ?? undefined}
				/>
				{hintId && <p id={hintId} className="fieldset-label">{field.help_text}</p>}
				{error && <p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>}
			</div>
		</FieldLayout>
	);
}

/**
 * Renders a select dropdown field, or a combobox when search is on.
 */
export function SelectField( props: FieldComponentProps ) {
	const { field, value, error, onChange, displayConfig } = props;

	if ( isSearchableField( field ) ) {
		return <SearchableSelect {...props} multiple={false} />;
	}

	const options = getFieldOptions( field );
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
	const options = getRenderOptions( field );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const { labelId, errorId, hintId, describedBy } = getGroupIds( field, layout, error );
	const selected = String( value ?? '' );
	const allowsOther = allowsOtherChoice( field );
	const otherSelected = allowsOther && selected === OTHER_CHOICE_VALUE;
	const choices = allowsOther ? [...options, { value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( field ) }] : options;
	const showsImages = showsImageChoices( field );

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
				className={`${getChoiceLayoutClasses( field )} ${field.css_classes ?? ''}`}
			>
				{choices.map( ( option, index ) => {
					const optionId = `field-${field.name}-${option.value === OTHER_CHOICE_VALUE ? 'other' : index}`;
					const asImage = showsImages && option.value !== OTHER_CHOICE_VALUE;
					const input = (
						<input
							id={optionId}
							type="radio"
							name={field.name}
							value={option.value}
							className={asImage ? 'radio radio-primary radio-sm' : 'radio radio-primary'}
							checked={selected === option.value}
							required={field.is_required && index === 0}
							disabled={readOnly}
							aria-labelledby={asImage ? `${optionId}-label` : undefined}
							aria-controls={option.value === OTHER_CHOICE_VALUE && otherSelected ? `field-${field.name}-other-text` : undefined}
							onChange={() => onChange( option.value )}
						/>
					);

					if ( asImage ) {
						return <ImageChoice key={option.value} option={option} labelId={`${optionId}-label`}>{input}</ImageChoice>;
					}

					return (
						<label key={option.value} htmlFor={optionId} className="flex items-center gap-2 cursor-pointer">
							{input}
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
 * An "Other" choice comes last and reveals a text input. A "Select All"
 * checkbox (since 1.6.0) is checked when every choice is and mixed when some
 * are; it leaves the "Other" choice alone.
 */
export function CheckboxGroupField( { field, value, error, onChange, displayConfig, otherValue = '', onOtherChange }: FieldComponentProps ) {
	const options = getRenderOptions( field );
	const selectedValues = Array.isArray( value ) ? value.map( String ) : [];
	const optionValues = options.map( ( option ) => option.value );
	const selectedCount = optionValues.filter( ( optionValue ) => selectedValues.includes( optionValue ) ).length;
	const showSelectAll = offersSelectAll( field ) && optionValues.length > 1;
	const selectAllRef = useRef<HTMLInputElement>( null );

	useEffect( () => {
		if ( selectAllRef.current ) {
			selectAllRef.current.indeterminate = selectedCount > 0 && selectedCount < optionValues.length;
		}
	}, [selectedCount, optionValues.length] );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const { labelId, errorId, hintId, describedBy } = getGroupIds( field, layout, error );
	const allowsOther = allowsOtherChoice( field );
	const otherSelected = allowsOther && isOtherChoiceSelected( selectedValues );
	const choices = allowsOther ? [...options, { value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( field ) }] : options;
	const showsImages = showsImageChoices( field );

	const handleChange = ( optionValue: string, checked: boolean ) => {
		const updated = checked
			? [...selectedValues, optionValue]
			: selectedValues.filter( ( v ) => v !== optionValue );

		onChange( updated );
	};

	const handleSelectAll = ( checked: boolean ) => {
		const others = selectedValues.filter( ( v ) => !optionValues.includes( v ) );

		onChange( checked ? [...optionValues, ...others] : others );
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
				{showSelectAll && (
					<label htmlFor={`field-${field.name}-select-all`} className="mb-2 flex cursor-pointer items-center gap-2 font-medium">
						<input
							ref={selectAllRef}
							id={`field-${field.name}-select-all`}
							type="checkbox"
							className="checkbox checkbox-primary"
							checked={selectedCount === optionValues.length}
							disabled={readOnly}
							aria-controls={optionValues.map( ( _, index ) => `field-${field.name}-${index}` ).join( ' ' )}
							onChange={( e ) => handleSelectAll( e.target.checked )}
						/>
						<span>{t( 'Select All' )}</span>
					</label>
				)}
				<div className={`${getChoiceLayoutClasses( field )} ${field.css_classes ?? ''}`}>
					{choices.map( ( option, index ) => showsImages && option.value !== OTHER_CHOICE_VALUE ? (
						<ImageChoice key={option.value} option={option} labelId={`field-${field.name}-${index}-label`}>
							<input
								id={`field-${field.name}-${index}`}
								type="checkbox"
								name={`${field.name}[]`}
								value={option.value}
								className="checkbox checkbox-primary checkbox-sm"
								checked={selectedValues.includes( option.value )}
								disabled={readOnly}
								aria-labelledby={`field-${field.name}-${index}-label`}
								onChange={( e ) => handleChange( option.value, e.target.checked )}
							/>
						</ImageChoice>
					) : (
						<Checkbox
							key={option.value}
							id={option.value === OTHER_CHOICE_VALUE ? `field-${field.name}-other` : `field-${field.name}-${index}`}
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
 * Renders a multi-select dropdown field, or a combobox when search is on.
 */
export function SelectMultipleField( props: FieldComponentProps ) {
	const { field, value, error, onChange, displayConfig } = props;

	if ( isSearchableField( field ) ) {
		return <SearchableSelect {...props} multiple />;
	}

	const options = getFieldOptions( field );
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
