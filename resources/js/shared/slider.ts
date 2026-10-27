/**
 * Slider field helpers.
 *
 * Client-side mirror of `SliderFieldType`. The server resolves the field's
 * limits and value label and sends them as `field.slider`. A slider always
 * has a value: it starts at its default value within its limits, or at its
 * minimum.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField, SliderSettings } from '../types/artisanpack-forms';
import { t } from './i18n';

/** Whether a field is a slider. */
export function isSliderField( field: FormField ): boolean {
	return field.type === 'slider';
}

/** Get a slider's limits and value label, falling back to `SliderFieldType`'s defaults. */
export function getSliderSettings( field: FormField ): SliderSettings {
	return {
		min: field.slider?.min ?? 0,
		max: field.slider?.max ?? 100,
		step: field.slider?.step ?? 1,
		prefix: field.slider?.prefix ?? '',
		suffix: field.slider?.suffix ?? '',
		show_value: field.slider?.show_value ?? true,
	};
}

/**
 * Format a value with the slider's prefix and suffix, such as "$40", "75%" or
 * "3 years": a prefix ending, or a suffix starting, with a letter or digit is
 * spaced from the value. Mirrors `SliderFieldType::valueText()`.
 */
export function formatSliderValue( field: FormField, value: unknown ): string {
	const { prefix, suffix } = getSliderSettings( field );
	const before = /[\p{L}\p{N}]$/u.test( prefix ) ? `${prefix} ` : prefix;
	const after = /^[\p{L}\p{N}]/u.test( suffix ) ? ` ${suffix}` : suffix;

	return `${before}${String( value ?? '' )}${after}`;
}

/**
 * Get the value a slider starts with: a value within its limits, or its
 * minimum. Mirrors `SliderFieldType::initialValue()`.
 */
export function getInitialSliderValue( field: FormField, source: unknown ): string {
	const { min, max } = getSliderSettings( field );
	const value = typeof source === 'number' || ( typeof source === 'string' && source.trim() !== '' ) ? Number( source ) : NaN;

	return String( Number.isFinite( value ) && value >= min && value <= max ? value : min );
}

/** Whether a number is a whole number of steps from a base, mirroring `SubmissionRules::isOnStep()`. */
export function isOnStep( value: number, base: number, step: number ): boolean {
	if ( !( step > 0 ) ) {
		return true;
	}

	const steps = ( value - base ) / step;

	return Math.abs( steps - Math.round( steps ) ) < 1e-9 * Math.max( 1, Math.abs( steps ) );
}

/**
 * Validate a slider's value, mirroring `SliderFieldType::typeRules()`: a
 * number within the limits, on a step from the minimum.
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateSlider( field: FormField, value: unknown ): string[] {
	const { min, max, step } = getSliderSettings( field );
	const label = field.label ?? field.name;
	const num = Number( value );

	if ( value === null || value === undefined || String( value ).trim() === '' || !Number.isFinite( num ) ) {
		return [t( 'Please choose a value for :field.', { field: label } )];
	}

	if ( num < min || num > max ) {
		return [t( 'The :field must be between :min and :max.', { field: label, min: formatSliderValue( field, min ), max: formatSliderValue( field, max ) } )];
	}

	if ( !isOnStep( num, min, step ) ) {
		return [t( 'The :field must be in steps of :step.', { field: label, step } )];
	}

	return [];
}
