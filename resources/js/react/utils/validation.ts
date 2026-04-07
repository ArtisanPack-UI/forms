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

import type { FormField, ValidationRules } from '../../types/artisanpack-forms';

/** Layout field types that do not collect data. */
const LAYOUT_FIELDS = new Set( ['heading', 'paragraph', 'divider', 'html'] );

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
		if ( rules.min !== undefined && value.length < rules.min ) {
			errors.push( `Must be at least ${rules.min} characters.` );
		}

		if ( rules.max !== undefined && value.length > rules.max ) {
			errors.push( `Must be no more than ${rules.max} characters.` );
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

	// File validation
	if ( field.type === 'file' && files ) {
		const fileValue = files[field.name];

		if ( fileValue ) {
			const fileList = Array.isArray( fileValue ) ? fileValue : [fileValue];

			for ( const file of fileList ) {
				validateFileRules( file, rules, field, errors );
			}
		}
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
	if ( rules.min !== undefined && num < rules.min ) {
		errors.push( `Must be at least ${rules.min}.` );
	}

	if ( rules.max !== undefined && num > rules.max ) {
		errors.push( `Must be no more than ${rules.max}.` );
	}

	if ( rules.step !== undefined && rules.step > 0 ) {
		const base = rules.min ?? 0;
		const remainder = Math.abs( ( num - base ) % rules.step );
		const epsilon = 1e-9;

		if ( remainder > epsilon && Math.abs( remainder - rules.step ) > epsilon ) {
			errors.push( `Must be a multiple of ${rules.step}.` );
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

	if ( rules.min_date ) {
		const minDate = new Date( rules.min_date );

		if ( date < minDate ) {
			errors.push( `Date must be on or after ${rules.min_date}.` );
		}
	}

	if ( rules.max_date ) {
		const maxDate = new Date( rules.max_date );

		if ( date > maxDate ) {
			errors.push( `Date must be on or before ${rules.max_date}.` );
		}
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

/**
 * Validates time min/max constraints.
 */
function validateTimeRules(
	value: string,
	rules: ValidationRules,
	errors: string[],
): void {
	const normalized = normalizeTime( value );

	if ( rules.min_time && normalized < normalizeTime( rules.min_time ) ) {
		errors.push( `Time must be on or after ${rules.min_time}.` );
	}

	if ( rules.max_time && normalized > normalizeTime( rules.max_time ) ) {
		errors.push( `Time must be on or before ${rules.max_time}.` );
	}
}

/**
 * Validates file size and MIME type constraints.
 */
function validateFileRules(
	file: File,
	rules: ValidationRules,
	field: FormField,
	errors: string[],
): void {
	if ( rules.max_size ) {
		const maxBytes = rules.max_size * 1024;

		if ( file.size > maxBytes ) {
			errors.push( `${field.label ?? field.name}: File must be no larger than ${rules.max_size} KB.` );
		}
	}

	if ( rules.allowed_types && rules.allowed_types.length > 0 ) {
		if ( !rules.allowed_types.includes( file.type ) ) {
			errors.push( `${field.label ?? field.name}: File type "${file.type}" is not allowed.` );
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
