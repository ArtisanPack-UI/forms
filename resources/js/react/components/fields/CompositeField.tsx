/**
 * Composite (multi-input) field component.
 *
 * Renders a field made of several inputs stored together, such as a name or
 * an address: a `<fieldset>` whose `<legend>` is the field label, with one
 * labelled input per enabled sub-input carrying its `autocomplete` token and
 * its own error, and a field-level summary when any part is invalid.
 * Sub-labels sit below or above their input, or are visually hidden. A
 * `select` sub-input renders its options and a `wide` one spans the row.
 * Each input carries `data-forms-part="{key}"` and the fieldset
 * `data-forms-composite="{type}"`, so address autocomplete providers can
 * find them. Mirrors the Blade `composite` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import {
	getEnabledSubInputs,
	getRequiredSubInputKeys,
	getSubInputId,
	getSubLabelPlacement,
	SUB_INPUT_MAX_LENGTH,
	toCompositeValue,
} from '../../../shared/composite';
import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import type { FieldComponentProps } from './types';

/**
 * Renders a composite field.
 */
export function CompositeField( { field, value, error, subErrors = {}, onChange, displayConfig }: FieldComponentProps ) {
	const parts = toCompositeValue( value );
	const subInputs = getEnabledSubInputs( field );
	const required = new Set( getRequiredSubInputKeys( field ) );
	const subLabelPlacement = getSubLabelPlacement( field );
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const errorCount = subInputs.filter( ( subInput ) => subErrors[subInput.key] ).length;
	const hasErrors = Boolean( error ) || errorCount > 0;
	const hintId = field.help_text ? `${field.name}-hint` : undefined;
	const errorId = hasErrors ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const summary = error || ( errorCount === 1
		? t( 'Please fix :count highlighted part of :field.', { count: errorCount, field: field.label ?? field.name } )
		: t( 'Please fix :count highlighted parts of :field.', { count: errorCount, field: field.label ?? field.name } ) );
	const hint = hintId ? <p id={hintId} className="fieldset-label">{field.help_text}</p> : null;

	const handleChange = ( key: string, text: string ) => {
		onChange( { ...parts, [key]: text } );
	};

	return (
		<fieldset
			id={`field-${field.name}`}
			className={`fieldset ${field.css_classes ?? ''}`}
			data-forms-composite={field.type}
			aria-describedby={describedBy}
		>
			{field.label && (
				<legend className={`fieldset-legend ${hiddenLabel ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
				</legend>
			)}

			{layout.description === 'above' && hint}

			<div className={`grid grid-cols-1 gap-3 ${subInputs.length > 1 ? 'sm:grid-cols-2' : ''}`}>
				{subInputs.map( ( subInput ) => {
					const inputId = getSubInputId( field, subInput.key );
					const subError = subErrors[subInput.key];
					const isRequired = required.has( subInput.key );
					const label = (
						<label
							htmlFor={inputId}
							className={subLabelPlacement === 'below' ? 'fieldset-label text-xs' : `label text-sm ${subLabelPlacement === 'hidden' ? 'sr-only' : ''}`}
						>
							{subInput.label}
							{isRequired && <span className="text-error ml-1" aria-hidden="true">*</span>}
						</label>
					);

					const isWide = Boolean( subInput.wide ) && subInputs.length > 1;

					return (
						<div key={subInput.key} className={`flex flex-col gap-1 ${isWide ? 'sm:col-span-2' : ''}`}>
							{subLabelPlacement !== 'below' && label}
							{subInput.type === 'select' ? (
								<select
									id={inputId}
									name={`${field.name}[${subInput.key}]`}
									data-forms-part={subInput.key}
									value={parts[subInput.key] ?? ''}
									autoComplete={subInput.autocomplete ?? undefined}
									required={isRequired}
									aria-required={isRequired ? 'true' : undefined}
									aria-invalid={subError ? true : undefined}
									aria-describedby={subError ? `${inputId}-error` : undefined}
									className={`select select-bordered w-full ${subError ? 'select-error' : ''}`}
									onChange={( e ) => handleChange( subInput.key, e.target.value )}
								>
									<option value="">{subInput.placeholder || t( 'Select…' )}</option>
									{( subInput.options ?? [] ).map( ( option ) => (
										<option key={option.value} value={option.value}>{option.label}</option>
									) )}
								</select>
							) : (
								<input
									id={inputId}
									name={`${field.name}[${subInput.key}]`}
									data-forms-part={subInput.key}
									value={parts[subInput.key] ?? ''}
									autoComplete={subInput.autocomplete ?? undefined}
									required={isRequired}
									aria-required={isRequired ? 'true' : undefined}
									aria-invalid={subError ? true : undefined}
									aria-describedby={subError ? `${inputId}-error` : undefined}
									type={subInput.type}
									className={`input input-bordered w-full ${subError ? 'input-error' : ''}`}
									placeholder={subInput.placeholder ?? undefined}
									maxLength={subInput.type === 'number' ? undefined : SUB_INPUT_MAX_LENGTH}
									onChange={( e ) => handleChange( subInput.key, e.target.value )}
								/>
							)}
							{subLabelPlacement === 'below' && label}
							{subError && <p id={`${inputId}-error`} className="text-xs text-error">{subError}</p>}
						</div>
					);
				} )}
			</div>

			{layout.description !== 'above' && hint}

			{hasErrors && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{summary}</p>
			)}
		</fieldset>
	);
}
