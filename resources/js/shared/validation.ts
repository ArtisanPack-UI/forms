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

import { hasCaptchaWidget, runsOnSubmit } from './captcha';
import {
	getEnabledSubInputs,
	getRequiredSubInputKeys,
	isCompositeField,
	SUB_INPUT_MAX_LENGTH,
	toCompositeValue,
} from './composite';
import {
	ACCEPTANCE_FIELD_TYPES,
	allowsOtherChoice,
	getConfirmationKey,
	getFileAllowedTypes,
	getFileMaxSize,
	getOtherChoiceLabel,
	getRatingMax,
	OTHER_CHOICE_MAX_LENGTH,
	OTHER_CHOICE_VALUE,
	ratingAllowsHalf,
	requiresEmailConfirmation,
} from './fieldSettings';
import { t } from './i18n';

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
 * Checks whether a required field is missing its value.
 *
 * Mirrors the server's presence rules: an unchecked checkbox or toggle fails
 * `accepted` even after it was checked once, and a rating of 0 ("no rating")
 * counts as missing.
 */
function isMissing(
	field: FormField,
	value: unknown,
	files?: Record<string, File | File[]>,
): boolean {
	if ( field.type === 'file' ) {
		const fileValue = files?.[field.name];

		return !fileValue || ( Array.isArray( fileValue ) && fileValue.length === 0 );
	}

	if ( ACCEPTANCE_FIELD_TYPES.has( field.type ) ) {
		return value !== true && value !== 1 && value !== '1' && value !== 'true' && value !== 'on' && value !== 'yes';
	}

	if ( field.type === 'rating' ) {
		return !( Number( value ) > 0 );
	}

	if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
		return !Array.isArray( value ) || value.length === 0;
	}

	return value === null || value === undefined || String( value ).trim() === '';
}

/**
 * Validates a single field value against its field definition.
 *
 * A field's custom messages replace the built-in ones, as on the server:
 * `required_message` when a required field is empty, and `error_message`
 * for any other failure (see `FormField::buildValidationMessages()`).
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateField(
	field: FormField,
	value: unknown,
	files?: Record<string, File | File[]>,
): string[] {
	if ( LAYOUT_FIELDS.has( field.type ) ) {
		return [];
	}

	if ( field.is_required && isMissing( field, value, files ) ) {
		return [field.required_message ?? `${field.label ?? field.name} is required.`];
	}

	const errors = validateValue( field, value, files );

	return errors.length > 0 && field.error_message ? [field.error_message] : errors;
}

/**
 * Validates a present value against the field's type and limits.
 *
 * @returns An array of error messages (empty if valid).
 */
function validateValue(
	field: FormField,
	value: unknown,
	files?: Record<string, File | File[]>,
): string[] {
	const errors: string[] = [];
	const rules = field.validation_rules;

	// Skip further validation if value is empty and not required (except file fields)
	if ( field.type !== 'file' && ( value === null || value === undefined || String( value ).trim() === '' ) ) {
		return errors;
	}

	// A selected "Other" choice needs its text, mirroring the `forms_other_choice` rule.
	// Callers validate values with the text already resolved (see `resolveOtherChoices()`).
	if ( allowsOtherChoice( field ) ) {
		const items = Array.isArray( value ) ? value : [value];

		if ( items.some( ( item ) => item === OTHER_CHOICE_VALUE || String( item ).length > OTHER_CHOICE_MAX_LENGTH ) ) {
			errors.push( `Please specify ${getOtherChoiceLabel( field )} (up to ${OTHER_CHOICE_MAX_LENGTH} characters).` );

			return errors;
		}
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
 * Validates a composite field, one sub-input at a time.
 *
 * Mirrors `CompositeFieldType::rules()`: the field's required inputs must be
 * filled in, email and URL inputs must be valid, number inputs numeric and
 * text no longer than `SUB_INPUT_MAX_LENGTH`. The field's custom required
 * and error messages replace the built-in ones.
 *
 * @since 1.6.0
 *
 * @returns Error arrays keyed `{field}.{key}` (empty when valid).
 */
export function validateCompositeField( field: FormField, value: unknown ): Record<string, string[]> {
	const parts = toCompositeValue( value );
	const required = new Set( getRequiredSubInputKeys( field ) );
	const errors: Record<string, string[]> = {};

	for ( const subInput of getEnabledSubInputs( field ) ) {
		const part = ( parts[subInput.key] ?? '' ).trim();
		const replacements = { field: field.label ?? field.name, part: subInput.label };
		let message: string | undefined;

		if ( part === '' ) {
			message = required.has( subInput.key )
				? field.required_message ?? t( ':field: :part is required.', replacements )
				: undefined;
		} else if ( subInput.type === 'select' && ( subInput.options ?? [] ).length > 0 && !( subInput.options ?? [] ).some( ( option ) => option.value === part ) ) {
			message = t( ':field: please choose a valid :part.', replacements );
		} else if ( subInput.type === 'number' ) {
			message = Number.isFinite( Number( part ) ) ? undefined : t( ':field: :part must be a number.', replacements );
		} else if ( part.length > SUB_INPUT_MAX_LENGTH ) {
			message = t( ':field: :part may not be longer than :max characters.', { ...replacements, max: SUB_INPUT_MAX_LENGTH } );
		} else if ( subInput.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( part ) ) {
			message = t( ':field: :part must be a valid email address.', replacements );
		} else if ( subInput.type === 'url' && !isValidUrl( part ) ) {
			message = t( ':field: :part must be a valid URL.', replacements );
		}

		if ( message !== undefined ) {
			errors[`${field.name}.${subInput.key}`] = [part !== '' && field.error_message ? field.error_message : message];
		}
	}

	return errors;
}

/** Whether a string parses as an absolute URL. */
function isValidUrl( value: string ): boolean {
	try {
		new URL( value );

		return true;
	} catch {
		return false;
	}
}

/**
 * Validates an email field's confirmation, mirroring Laravel's `confirmed`
 * rule: a filled-in email must match `{name}_confirmation`. The field's
 * custom error message replaces the built-in one (since 1.6.0).
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateEmailConfirmation( field: FormField, value: unknown, confirmation: unknown ): string[] {
	const email = value === null || value === undefined ? '' : String( value );

	if ( email.trim() === '' || email === String( confirmation ?? '' ) ) {
		return [];
	}

	return [field.error_message ?? t( 'The email addresses entered for :field do not match.', { field: field.label ?? field.name } )];
}

/**
 * Validates a CAPTCHA field: a token is required while its provider is set
 * up, except for invisible widgets, which fetch it when the form is
 * submitted. The server checks the token itself (since 1.6.0).
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateCaptchaField( field: FormField, value: unknown ): string[] {
	if ( !hasCaptchaWidget( field ) || runsOnSubmit( field.captcha ) ) {
		return [];
	}

	return typeof value === 'string' && value !== ''
		? []
		: [field.required_message ?? t( 'Please complete the verification check.' )];
}

/**
 * Validates all visible fields in a form.
 *
 * A composite field's errors are keyed `{field}.{key}` (since 1.6.0). An
 * email field's confirmation is read from `{name}_confirmation` and a
 * mismatch is the email field's error (since 1.6.0).
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

		if ( isCompositeField( field ) ) {
			Object.assign( errors, validateCompositeField( field, formData[field.name] ) );

			continue;
		}

		if ( field.type === 'captcha' ) {
			const captchaErrors = validateCaptchaField( field, formData[field.name] );

			if ( captchaErrors.length > 0 ) {
				errors[field.name] = captchaErrors;
			}

			continue;
		}

		const fieldErrors = validateField( field, formData[field.name], files );

		if ( fieldErrors.length === 0 && requiresEmailConfirmation( field ) ) {
			fieldErrors.push( ...validateEmailConfirmation( field, formData[field.name], formData[getConfirmationKey( field )] ) );
		}

		if ( fieldErrors.length > 0 ) {
			errors[field.name] = fieldErrors;
		}
	}

	return errors;
}
