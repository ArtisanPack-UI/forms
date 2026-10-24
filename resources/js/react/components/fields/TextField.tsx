/**
 * Text-based field components.
 *
 * Renders text, email, phone, number, url, textarea, hidden,
 * and time input fields using @artisanpack-ui/react components. A read-only
 * field keeps its value focusable and announced via `readOnly`. Inputs expose
 * the field's `autocomplete` token (WCAG 1.3.5). An email field with
 * confirmation on asks for the address twice (since 1.6.0).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { Input, Textarea } from '@artisanpack-ui/react/form';

import { getSubLabelPlacement } from '../../../shared/composite';
import {
	getConfirmationKey,
	getFieldLayout,
	getInputLabelProps,
	getTextareaRows,
	getTimeStep,
	isReadOnlyField,
	requiresEmailConfirmation,
} from '../../../shared/fieldSettings';

import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/**
 * Renders a single-line text input field.
 */
export function TextField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="text"
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				minLength={field.validation_rules?.min}
				maxLength={field.validation_rules?.max}
				pattern={field.validation_rules?.pattern ?? undefined}
			/>
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

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="tel"
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				pattern={field.validation_rules?.pattern ?? undefined}
			/>
		</FieldLayout>
	);
}

/**
 * Renders a number input field.
 */
export function NumberField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="number"
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				min={field.validation_rules?.min}
				max={field.validation_rules?.max}
				step={field.validation_rules?.step}
			/>
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
			/>
		</FieldLayout>
	);
}

/**
 * Renders a multi-line textarea field.
 */
export function TextareaField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );

	return (
		<FieldLayout field={field} layout={layout}>
			<Textarea
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				value={String( value ?? '' )}
				placeholder={field.placeholder ?? undefined}
				error={error}
				required={field.is_required}
				autoComplete={field.autocomplete ?? undefined}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				minLength={field.validation_rules?.min}
				maxLength={field.validation_rules?.max}
				rows={getTextareaRows( field )}
			/>
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
 * Renders a time input field.
 */
export function TimeField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
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
