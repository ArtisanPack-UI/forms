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
	allowsMultipleFiles,
	allowsOtherChoice,
	getConfirmationKey,
	getFileAllowedTypes,
	getFileMaxCount,
	getFileMaxSize,
	getFileMaxTotalSize,
	getOtherChoiceLabel,
	getRatingMax,
	OTHER_CHOICE_MAX_LENGTH,
	OTHER_CHOICE_VALUE,
	ratingAllowsHalf,
	requiresEmailConfirmation,
} from './fieldSettings';
import { countWords, getMaxWords } from './counter';
import {
	earliestBirthdate,
	getAgeLimit,
	getDateLimits,
	isAllowedDay,
	latestBirthdate,
	parseIsoDate,
} from './dateConstraints';
import { validateColor } from './color';
import { isAcceptedEmailDomain } from './emailDomains';
import { t } from './i18n';
import { isListField, validateListField } from './list';
import { getInputMask, getInputMaskHint, maskToRegExp } from './mask';
import { isMatrixField, validateMatrixField } from './matrix';
import { validateNps } from './nps';
import { validateRanking } from './ranking';
import { validateSignature } from './signature';
import { validateSlider } from './slider';
import { getNumberFormat, isIntegerOnly, normalizeFormattedNumber } from './numberFormat';
import { getPhoneFormat, isPlausiblePhoneNumber } from './phone';
import { richTextToPlainText, usesRichText } from './richText';
import { getAllowedUrlSchemes, isAllowedUrl, withUrlScheme } from './url';

/** Layout field types that do not collect data. */
const LAYOUT_FIELDS = new Set( ['section', 'heading', 'paragraph', 'divider', 'html'] );

/** A `datetime-local` value, with optional seconds (or the stored `Y-m-d H:i` form). */
const DATE_TIME_PATTERN = /^(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/;

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

/**
 * Validators for types whose value is checked against their own settings
 * rather than `validation_rules` (since 1.6.0).
 */
const TYPE_VALIDATORS: Record<string, ( field: FormField, value: unknown ) => string[]> = {
	slider: validateSlider,
	nps: validateNps,
	ranking: validateRanking,
	signature: validateSignature,
	color: validateColor,
};

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

	if ( field.type === 'checkbox_group' || field.type === 'select_multiple' || field.type === 'ranking' ) {
		return !Array.isArray( value ) || value.length === 0;
	}

	// An emptied rich text editor still holds markup such as `<p><br></p>`
	if ( usesRichText( field ) && typeof value === 'string' ) {
		return richTextToPlainText( value ) === '';
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

	// Slider, color, NPS, ranking and signature values are checked against their own settings (since 1.6.0)
	if ( Object.prototype.hasOwnProperty.call( TYPE_VALIDATORS, field.type ) ) {
		return TYPE_VALIDATORS[field.type]( field, value );
	}

	if ( usesRichText( field ) && typeof value === 'string' && richTextToPlainText( value ) === '' ) {
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

			validateFileListRules( fileList, field, errors );

			for ( const file of fileList ) {
				validateFileRules( file, field, errors );
			}
		}
	}

	// Input masks, phone formats and number formats read `field_config` too
	if ( typeof value === 'string' ) {
		const mask = getInputMask( field );

		if ( mask && !maskToRegExp( mask ).test( value ) ) {
			errors.push( t( 'Please match the expected format. :hint', { hint: getInputMaskHint( field ) ?? mask } ) );
		}

		if ( field.type === 'phone' && !isPlausiblePhoneNumber( value, getPhoneFormat( field ) ) ) {
			errors.push( t( 'Please enter a valid phone number.' ) );
		}

		// Email and URL checks read their own settings, so they run without validation rules (since 1.6.0)
		if ( field.type === 'email' ) {
			if ( !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
				errors.push( 'Please enter a valid email address.' );
			} else if ( !isAcceptedEmailDomain( field, value ) ) {
				errors.push( t( 'Please use an email address from an accepted domain.' ) );
			}
		}

		// The server adds `https://` to an address typed without a scheme, so check it with one
		if ( field.type === 'url' ) {
			const schemes = getAllowedUrlSchemes( field );

			if ( !isAllowedUrl( withUrlScheme( value, schemes ), schemes ) ) {
				errors.push( 'Please enter a valid URL.' );
			}
		}

		// Date formats, disabled days and age limits read their own settings too (since 1.6.0)
		if ( field.type === 'date' ) {
			validateDateField( field, value, errors );
		}

		if ( field.type === 'datetime' ) {
			validateDateTimeField( field, value, errors );
		}
	}

	if ( field.type === 'number' && ( typeof value === 'string' || typeof value === 'number' ) ) {
		const format = getNumberFormat( field );
		const plain = format ? normalizeFormattedNumber( String( value ), format ) : String( value );
		const num = Number( plain );

		if ( plain.trim() === '' || isNaN( num ) ) {
			errors.push( 'Please enter a valid number.' );
		} else {
			if ( isIntegerOnly( field ) && !Number.isInteger( num ) ) {
				errors.push( t( 'Please enter a whole number.' ) );
			}

			if ( rules ) {
				validateNumericRules( num, rules, errors );
			}
		}
	}

	if ( !rules ) {
		return errors;
	}

	// Rich text limits count its text, not its markup (since 1.6.0)
	const text = typeof value === 'string' && usesRichText( field ) ? richTextToPlainText( value ) : value;

	// Min/max length for text-like fields only (not number, date, time, etc.)
	if ( typeof text === 'string' && TEXT_LENGTH_FIELDS.has( field.type ) ) {
		const minLength = numericLimit( rules.min );
		const maxLength = numericLimit( rules.max );
		const length = usesRichText( field ) ? Array.from( text ).length : text.length;

		if ( minLength !== undefined && length < minLength ) {
			errors.push( `Must be at least ${minLength} characters.` );
		}

		if ( maxLength !== undefined && length > maxLength ) {
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

	// A textarea's word limit, mirroring the `forms_max_words` rule
	const maxWords = getMaxWords( field );

	if ( typeof text === 'string' && maxWords !== undefined && countWords( text ) > maxWords ) {
		errors.push( t( 'Must be no more than :max words.', { max: maxWords } ) );
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
 * Validates a date: a real `YYYY-MM-DD` date (the dropdown and field modes
 * submit incomplete dates with gaps), within the field's resolved limits, not
 * a disabled day and within its age limits. Mirrors `DateFieldType::rules()`
 * (since 1.6.0).
 */
function validateDateField(
	field: FormField,
	value: string,
	errors: string[],
): void {
	if ( !parseIsoDate( value ) ) {
		errors.push( 'Please enter a valid date.' );

		return;
	}

	const { min: minDate, max: maxDate } = getDateLimits( field );

	// `YYYY-MM-DD` dates compare correctly as strings
	if ( minDate !== undefined && value < minDate ) {
		errors.push( `Date must be on or after ${minDate}.` );
	}

	if ( maxDate !== undefined && value > maxDate ) {
		errors.push( `Date must be on or before ${maxDate}.` );
	}

	if ( !isAllowedDay( field, value ) ) {
		errors.push( t( 'This date is not available. Please choose another date.' ) );
	}

	const minAge = getAgeLimit( field, 'min_age' );
	const maxAge = getAgeLimit( field, 'max_age' );

	if ( minAge !== undefined && value > latestBirthdate( minAge ) ) {
		errors.push( t( 'You must be at least :age years old.', { age: minAge } ) );
	}

	if ( maxAge !== undefined && value < earliestBirthdate( maxAge ) ) {
		errors.push( t( 'You must be no older than :age years.', { age: maxAge } ) );
	}
}

/**
 * Validates a date and time value, mirroring `DateTimeFieldType`: a
 * `datetime-local` value on a day within the field's min and max dates
 * (since 1.6.0).
 */
function validateDateTimeField(
	field: FormField,
	value: string,
	errors: string[],
): void {
	const match = DATE_TIME_PATTERN.exec( value.trim() );

	if ( !match || !parseIsoDate( match[1] ) ) {
		errors.push( t( 'Please enter a valid date and time for :field.', { field: field.label ?? field.name } ) );

		return;
	}

	const { min, max } = getDateLimits( field );

	if ( min !== undefined && match[1] < min ) {
		errors.push( t( 'The :field must be on or after :date.', { field: field.label ?? field.name, date: min } ) );
	}

	if ( max !== undefined && match[1] > max ) {
		errors.push( t( 'The :field must be on or before :date.', { field: field.label ?? field.name, date: max } ) );
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
 * Validates a multi-file field's count and total size, mirroring
 * `FileFieldType::rules()` (since 1.6.0).
 */
function validateFileListRules(
	fileList: File[],
	field: FormField,
	errors: string[],
): void {
	if ( !allowsMultipleFiles( field ) ) {
		return;
	}

	const maxFiles = getFileMaxCount( field );
	const maxTotalSize = getFileMaxTotalSize( field );

	if ( maxFiles !== undefined && fileList.length > maxFiles ) {
		errors.push( t( 'You may upload no more than :max files.', { max: maxFiles } ) );
	}

	if ( maxTotalSize !== undefined && fileList.reduce( ( total, file ) => total + file.size, 0 ) > maxTotalSize * 1024 ) {
		errors.push( t( 'The files must not exceed :size KB in total.', { size: maxTotalSize } ) );
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
		} else if ( subInput.type === 'date' ) {
			message = validateDatePart( field, part, replacements );
		}

		if ( message !== undefined ) {
			errors[`${field.name}.${subInput.key}`] = [part !== '' && field.error_message ? field.error_message : message];
		}
	}

	const enabledKeys = getEnabledSubInputs( field ).map( ( subInput ) => subInput.key );

	if ( field.type === 'date_range' && enabledKeys.includes( 'start' ) && enabledKeys.includes( 'end' ) && Object.keys( errors ).length === 0 && !isDateRange( parts ) ) {
		errors[field.name] = [field.error_message ?? t( 'Please enter both dates for :field, with the end date on or after the start date.', { field: field.label ?? field.name } )];
	}

	return errors;
}

/**
 * Validates a date sub-input: a `YYYY-MM-DD` date within the field's min
 * and max dates (since 1.6.0).
 *
 * @returns The error message, or undefined when valid.
 */
function validateDatePart( field: FormField, part: string, replacements: { field: string; part: string } ): string | undefined {
	if ( !parseIsoDate( part ) ) {
		return t( ':field: :part must be a valid date.', replacements );
	}

	const { min, max } = getDateLimits( field );

	if ( min !== undefined && part < min ) {
		return t( ':field: :part is too early.', replacements );
	}

	if ( max !== undefined && part > max ) {
		return t( ':field: :part is too late.', replacements );
	}

	return undefined;
}

/**
 * Whether a date range has both dates, or neither, and ends on or after its
 * start. Mirrors `SubmissionRules::isDateRange()` (since 1.6.0).
 */
export function isDateRange( parts: Record<string, string> ): boolean {
	const start = ( parts.start ?? '' ).trim();
	const end = ( parts.end ?? '' ).trim();

	if ( start === '' && end === '' ) {
		return true;
	}

	if ( start === '' || end === '' ) {
		return false;
	}

	return !parseIsoDate( start ) || !parseIsoDate( end ) || end >= start;
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
 * A composite field's errors are keyed `{field}.{key}` (since 1.6.0), a
 * list's `{field}.{row}.{column}` and a matrix's `{field}.{row}` (since
 * 1.6.0). An email field's
 * confirmation is read from `{name}_confirmation` and a mismatch is the
 * email field's error (since 1.6.0).
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

		if ( isListField( field ) ) {
			Object.assign( errors, validateListField( field, formData[field.name] ) );

			continue;
		}

		if ( isMatrixField( field ) ) {
			Object.assign( errors, validateMatrixField( field, formData[field.name] ) );

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
