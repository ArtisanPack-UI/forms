/**
 * Text-based field components.
 *
 * Renders text, email, phone, number, url, textarea, hidden,
 * and time input fields using @artisanpack-ui/react components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { Input, Textarea } from '@artisanpack-ui/react/form';

import type { FieldComponentProps } from './types';

/**
 * Renders a single-line text input field.
 */
export function TextField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	return (
		<Input
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			type="text"
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? undefined}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			minLength={field.validation_rules?.min}
			maxLength={field.validation_rules?.max}
			pattern={field.validation_rules?.pattern ?? undefined}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}

/**
 * Renders an email input field.
 */
export function EmailField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	return (
		<Input
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			type="email"
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? undefined}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}

/**
 * Renders a phone input field.
 */
export function PhoneField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	return (
		<Input
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			type="tel"
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? undefined}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			pattern={field.validation_rules?.pattern ?? undefined}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}

/**
 * Renders a number input field.
 */
export function NumberField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	return (
		<Input
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			type="number"
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? undefined}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			min={field.validation_rules?.min}
			max={field.validation_rules?.max}
			step={field.validation_rules?.step}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}

/**
 * Renders a URL input field.
 */
export function UrlField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	return (
		<Input
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			type="url"
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? 'https://'}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}

/**
 * Renders a multi-line textarea field.
 */
export function TextareaField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	return (
		<Textarea
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			value={String( value ?? '' )}
			placeholder={field.placeholder ?? undefined}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			minLength={field.validation_rules?.min}
			maxLength={field.validation_rules?.max}
			inline={displayConfig.label_position === 'beside'}
		/>
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
	return (
		<Input
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			type="time"
			value={String( value ?? '' )}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			min={field.validation_rules?.min_time ?? undefined}
			max={field.validation_rules?.max_time ?? undefined}
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}
