/**
 * Client-side validation for form fields.
 *
 * Validates form data based on field definitions and validation rules
 * to provide instant feedback before server-side validation.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import type { FormField, ValidationRules } from '../types/artisanpack-forms';

import { ACCEPTANCE_FIELD_TYPES, getFileAllowedTypes, getFileMaxSize, getRatingMax, ratingAllowsHalf } from './fieldSettings';

/** Layout field types that do not collect data. */
const LAYOUT_FIELDS = new Set( ['heading', 'paragraph', 'divider', 'html'] );

/**
 * Read a numeric limit. `0` counts as set; null, blank and non-numeric values
 * do not. Mirrors `FormField::getValidationLimit()`.
 */
function numericLimit( value: unknown ): number | undefined {
	if ( value === null || value === undefined || value === '' ) {
		return undefined;
	}

	const num = Number( value );

	return Number.isFinite( num ) ? num : undefined;
}

/** Read a non-blank string limit (date or time), or undefined. */
function stringLimit( value: unknown ): string | undefined {
	return typeof value === 'string' && value.trim() !== '' ? value : undefined;
}

/** Field types where min/max rules apply to string length (not numeric value). */
const TEXT_LENGTH_FIELDS = new Set( ['text', 'email', 'phone', 'url', 'textarea', 'hidden'] );

/**
 * Validates a single field value against its field definition.
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateField(
	field: FormField,
	value: unknown,
	files?: Record<string, File | File[]>,
): string[] {
	const errors: string[] = [];

	if ( LAYOUT_FIELDS.has( field.type ) ) {
		return errors;
	}

	const rules = field.validation_rules;

	// Required check
	if ( field.is_required ) {
		if ( field.type === 'file' ) {
			const fileValue = files?.[field.name];

			if ( !fileValue || ( Array.isArray( fileValue ) && fileValue.length === 0 ) ) {
				errors.push( `${field.label ?? field.name} is required.` );

				return errors;
			}
		} else if ( ACCEPTANCE_FIELD_TYPES.has( field.type ) ) {
			// Mirrors the server's `accepted` rule: an unchecked box fails even
			// after it was checked once.
			if ( value !== true && value !== 1 && value !== '1' && value !== 'true' && value !== 'on' && value !== 'yes' ) {
				errors.push( `${field.label ?? field.name} is required.` );

				return errors;
			}
		} else if ( field.type === 'rating' ) {
			// 0 is the "no rating" choice, so a required rating must be above it.
			if ( !( Number( value ) > 0 ) ) {
				errors.push( `${field.label ?? field.name} is required.` );

				return errors;
			}
		} else if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
			if ( !Array.isArray( value ) || value.length === 0 ) {
				errors.push( `${field.label ?? field.name} is required.` );

				return errors;
			}
		} else if ( value === null || value === undefined || String( value ).trim() === '' ) {
			errors.push( `${field.label ?? field.name} is required.` );

			return errors;
		}
	}

	// Skip further validation if value is empty and not required (except file fields)
	if ( field.type !== 'file' && ( value === null || value === undefined || String( value ).trim() === '' ) ) {
		return errors;
	}

	// Rating validation reads `field_config`, so it runs even without validation rules
	if ( field.type === 'rating' ) {
		validateRating( field, value, errors );

		return errors;
	}

	// File validation reads `field_config`, so it runs even without validation rules
	if ( field.type === 'file' && files ) {
		const fileValue = files[field.name];

		if ( fileValue ) {
			const fileList = Array.isArray( fileValue ) ? fileValue : [fileValue];

			for ( const file of fileList ) {
				validateFileRules( file, field, errors );
			}
		}
	}

	if ( !rules ) {
		return errors;
	}

	// Type-specific validation
	if ( field.type === 'email' && typeof value === 'string' ) {
		if ( !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			errors.push( 'Please enter a valid email address.' );
		}
	}

	if ( field.type === 'url' && typeof value === 'string' ) {
		try {
			new URL( value );
		} catch {
			errors.push( 'Please enter a valid URL.' );
		}
	}

	// Min/max length for text-like fields only (not number, date, time, etc.)
	if ( typeof value === 'string' && TEXT_LENGTH_FIELDS.has( field.type ) ) {
		const minLength = numericLimit( rules.min );
		const maxLength = numericLimit( rules.max );

		if ( minLength !== undefined && value.length < minLength ) {
			errors.push( `Must be at least ${minLength} characters.` );
		}

		if ( maxLength !== undefined && value.length > maxLength ) {
			errors.push( `Must be no more than ${maxLength} characters.` );
		}
	}

	// Pattern validation for string values
	if ( typeof value === 'string' ) {
		if ( rules.pattern ) {
			try {
				const regex = new RegExp( rules.pattern );

				if ( !regex.test( value ) ) {
					errors.push( 'The value does not match the required format.' );
				}
			} catch {
				// Invalid regex pattern — skip
			}
		}
	}

	// Numeric validation
	if ( field.type === 'number' && typeof value === 'string' ) {
		const num = Number( value );

		if ( isNaN( num ) ) {
			errors.push( 'Please enter a valid number.' );
		} else {
			validateNumericRules( num, rules, errors );
		}
	}

	// Date validation
	if ( field.type === 'date' && typeof value === 'string' ) {
		validateDateRules( value, rules, errors );
	}

	// Time validation
	if ( field.type === 'time' && typeof value === 'string' ) {
		validateTimeRules( value, rules, errors );
	}

	return errors;
}

/**
 * Validates numeric min/max/step constraints.
 */
function validateNumericRules(
	num: number,
	rules: ValidationRules,
	errors: string[],
): void {
	const min = numericLimit( rules.min );
	const max = numericLimit( rules.max );
	const step = numericLimit( rules.step );

	if ( min !== undefined && num < min ) {
		errors.push( `Must be at least ${min}.` );
	}

	if ( max !== undefined && num > max ) {
		errors.push( `Must be no more than ${max}.` );
	}

	if ( step !== undefined && step > 0 ) {
		const base = min ?? 0;
		const remainder = Math.abs( ( num - base ) % step );
		const epsilon = 1e-9;

		if ( remainder > epsilon && Math.abs( remainder - step ) > epsilon ) {
			errors.push( `Must be a multiple of ${step}.` );
		}
	}
}

/**
 * Validates date min/max constraints.
 */
function validateDateRules(
	value: string,
	rules: ValidationRules,
	errors: string[],
): void {
	const date = new Date( value );

	if ( isNaN( date.getTime() ) ) {
		errors.push( 'Please enter a valid date.' );

		return;
	}

	const minDate = stringLimit( rules.min_date );
	const maxDate = stringLimit( rules.max_date );

	if ( minDate !== undefined && date < new Date( minDate ) ) {
		errors.push( `Date must be on or after ${minDate}.` );
	}

	if ( maxDate !== undefined && date > new Date( maxDate ) ) {
		errors.push( `Date must be on or before ${maxDate}.` );
	}
}

/**
 * Normalizes a time string to "HH:MM:SS" for reliable comparison.
 */
function normalizeTime( time: string ): string {
	const parts = time.split( ':' );

	while ( parts.length < 3 ) {
		parts.push( '00' );
	}

	return parts.map( ( p ) => p.padStart( 2, '0' ) ).join( ':' );
}

/** Matches valid time strings: HH:MM or HH:MM:SS with valid ranges. */
const TIME_FORMAT_REGEX = /^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/;

/**
 * Validates time min/max constraints.
 */
function validateTimeRules(
	value: string,
	rules: ValidationRules,
	errors: string[],
): void {
	if ( !TIME_FORMAT_REGEX.test( value ) ) {
		errors.push( 'Please enter a valid time.' );

		return;
	}

	const normalized = normalizeTime( value );
	const minTime = stringLimit( rules.min_time );
	const maxTime = stringLimit( rules.max_time );

	if ( minTime !== undefined && TIME_FORMAT_REGEX.test( minTime ) && normalized < normalizeTime( minTime ) ) {
		errors.push( `Time must be on or after ${minTime}.` );
	}

	if ( maxTime !== undefined && TIME_FORMAT_REGEX.test( maxTime ) && normalized > normalizeTime( maxTime ) ) {
		errors.push( `Time must be on or before ${maxTime}.` );
	}
}

/**
 * Validates a rating: a number from 0 to the field's max, in whole or half
 * stars. Mirrors `RatingFieldType::typeRules()`.
 */
function validateRating( field: FormField, value: unknown, errors: string[] ): void {
	const rating = Number( value );
	const max = getRatingMax( field );

	if ( !Number.isFinite( rating ) || rating < 0 || rating > max ) {
		errors.push( `Please choose a rating from 0 to ${max}.` );

		return;
	}

	const step = ratingAllowsHalf( field ) ? 0.5 : 1;

	if ( Math.abs( rating / step - Math.round( rating / step ) ) > 1e-9 ) {
		errors.push( step === 1 ? 'Please choose a whole star.' : 'Please choose a whole or half star.' );
	}
}

/**
 * Validates file size and extension constraints.
 */
function validateFileRules(
	file: File,
	field: FormField,
	errors: string[],
): void {
	const maxSize = getFileMaxSize( field );

	if ( maxSize !== undefined && file.size > maxSize * 1024 ) {
		errors.push( `${field.label ?? field.name}: File must be no larger than ${maxSize} KB.` );
	}

	const allowedTypes = getFileAllowedTypes( field );

	if ( allowedTypes.length > 0 ) {
		const dot = file.name.lastIndexOf( '.' );
		const extension = dot >= 0 ? file.name.slice( dot + 1 ).toLowerCase() : '';

		if ( !allowedTypes.includes( extension ) ) {
			errors.push( `${field.label ?? field.name}: File type "${extension || file.type}" is not allowed.` );
		}
	}
}

/**
 * Validates all visible fields in a form.
 *
 * @returns A map of field names to their error arrays.
 */
export function validateFields(
	fields: FormField[],
	formData: Record<string, unknown>,
	hiddenFields: Record<string, boolean>,
	files?: Record<string, File | File[]>,
): Record<string, string[]> {
	const errors: Record<string, string[]> = {};

	for ( const field of fields ) {
		if ( LAYOUT_FIELDS.has( field.type ) ) {
			continue;
		}

		if ( hiddenFields[field.name] ) {
			continue;
		}

		const fieldErrors = validateField( field, formData[field.name], files );

		if ( fieldErrors.length > 0 ) {
			errors[field.name] = fieldErrors;
		}
	}

	return errors;
}
