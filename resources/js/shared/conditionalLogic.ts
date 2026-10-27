/**
 * Conditional logic evaluation engine.
 *
 * Client-side port of the PHP ConditionalLogicService.
 * Evaluates field visibility based on form data and conditional rules.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { normalizeColor } from './color';
import { formatCompositeForCondition } from './composite';
import { isListField, toListRows } from './list';
import { getNumberFormat, normalizeFormattedNumber } from './numberFormat';
import { blankIfEmptyRichText, usesRichText } from './richText';
import { applySectionVisibility } from './sections';
import { getAllowedUrlSchemes, withUrlScheme } from './url';
import type {
	ConditionalLogic,
	ConditionalLogicRule,
	ConditionalOperator,
	FormField,
} from '../types/artisanpack-forms';

/**
 * Builds a mapping of field UUIDs to field names and vice versa.
 */
function buildFieldMaps( fields: FormField[] ): {
	uuidToName: Record<string, string>;
	nameToUuid: Record<string, string>;
} {
	const uuidToName: Record<string, string> = {};
	const nameToUuid: Record<string, string> = {};

	for ( const field of fields ) {
		uuidToName[field.uuid] = field.name;
		nameToUuid[field.name] = field.uuid;
	}

	return { uuidToName, nameToUuid };
}

/**
 * Resolves a field reference (UUID or name) to a field name and, for a
 * composite field's sub-input (`{field}.{key}`), the sub-input key. A
 * reference that names a field as a whole is never split. Mirrors
 * `ConditionalLogicService::resolveFieldReference()`.
 */
function resolveFieldReference(
	fieldRef: string,
	uuidToName: Record<string, string>,
	formData: Record<string, unknown>,
): [string, string | undefined] {
	if ( uuidToName[fieldRef] !== undefined || Object.prototype.hasOwnProperty.call( formData, fieldRef ) ) {
		return [uuidToName[fieldRef] ?? fieldRef, undefined];
	}

	const dot = fieldRef.indexOf( '.' );

	if ( dot > 0 && dot < fieldRef.length - 1 ) {
		const base = fieldRef.slice( 0, dot );

		return [uuidToName[base] ?? base, fieldRef.slice( dot + 1 )];
	}

	return [fieldRef, undefined];
}

/**
 * Checks if a value is empty (null, undefined, empty string, or empty array).
 * An unchecked checkbox, toggle or consent (`false`) is empty, as on the
 * server; a number, including `0`, is not (since 1.6.0).
 */
function isEmpty( value: unknown ): boolean {
	if ( value === null || value === undefined ) {
		return true;
	}

	if ( typeof value === 'boolean' ) {
		return !value;
	}

	if ( typeof value === 'string' ) {
		return value.trim() === '';
	}

	if ( Array.isArray( value ) ) {
		return value.length === 0;
	}

	return false;
}

/**
 * Checks if a checkbox value is considered "checked".
 */
function isChecked( value: unknown ): boolean {
	if ( typeof value === 'boolean' ) {
		return value;
	}

	if ( typeof value === 'string' ) {
		return ['true', '1', 'yes', 'on'].includes( value.toLowerCase() );
	}

	return Boolean( value );
}

/**
 * Checks if a value is safe for numeric comparison.
 * Rejects null, undefined, booleans, arrays, and empty strings.
 */
function isNumericComparable( value: unknown ): boolean {
	if ( value === null || value === undefined ) {
		return false;
	}

	if ( typeof value === 'boolean' || Array.isArray( value ) ) {
		return false;
	}

	if ( typeof value === 'string' && value.trim() === '' ) {
		return false;
	}

	return !isNaN( Number( value ) );
}

/**
 * Compares two values for equality, handling booleans, numbers, and strings.
 */
function compareEquals( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( typeof fieldValue === 'boolean' ) {
		if ( typeof ruleValue === 'string' ) {
			return fieldValue === ( ['true', '1', 'yes', 'on'].includes( ruleValue.toLowerCase() ) );
		}

		return fieldValue === Boolean( ruleValue );
	}

	if ( isNumericComparable( fieldValue ) && isNumericComparable( ruleValue ) ) {
		return Number( fieldValue ) === Number( ruleValue );
	}

	return String( fieldValue ?? '' ) === String( ruleValue ?? '' );
}

/**
 * Checks if a string field value contains the rule value.
 */
function compareContains( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( typeof fieldValue !== 'string' || typeof ruleValue !== 'string' ) {
		return false;
	}

	return fieldValue.includes( ruleValue );
}

/**
 * Checks if a string field value starts with the rule value.
 */
function compareStartsWith( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( typeof fieldValue !== 'string' || typeof ruleValue !== 'string' ) {
		return false;
	}

	return fieldValue.startsWith( ruleValue );
}

/**
 * Checks if a string field value ends with the rule value.
 */
function compareEndsWith( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( typeof fieldValue !== 'string' || typeof ruleValue !== 'string' ) {
		return false;
	}

	return fieldValue.endsWith( ruleValue );
}

/**
 * Compares numeric values with greater than.
 */
function compareGreaterThan( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( !isNumericComparable( fieldValue ) || !isNumericComparable( ruleValue ) ) {
		return false;
	}

	return Number( fieldValue ) > Number( ruleValue );
}

/**
 * Compares numeric values with less than.
 */
function compareLessThan( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( !isNumericComparable( fieldValue ) || !isNumericComparable( ruleValue ) ) {
		return false;
	}

	return Number( fieldValue ) < Number( ruleValue );
}

/**
 * Compares numeric values with greater than or equal.
 */
function compareGreaterOrEqual( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( !isNumericComparable( fieldValue ) || !isNumericComparable( ruleValue ) ) {
		return false;
	}

	return Number( fieldValue ) >= Number( ruleValue );
}

/**
 * Compares numeric values with less than or equal.
 */
function compareLessOrEqual( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( !isNumericComparable( fieldValue ) || !isNumericComparable( ruleValue ) ) {
		return false;
	}

	return Number( fieldValue ) <= Number( ruleValue );
}

/**
 * Checks if a field value is in a comma-separated list.
 */
function compareIn( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( typeof ruleValue !== 'string' ) {
		return false;
	}

	const list = ruleValue.split( ',' ).map( ( item ) => item.trim() );

	return list.includes( String( fieldValue ) );
}

/**
 * Checks if an array field value includes a specific item.
 */
function compareIncludes( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( !Array.isArray( fieldValue ) ) {
		return false;
	}

	return fieldValue.map( String ).includes( String( ruleValue ) );
}

/**
 * Compares a field value against a rule value using the specified operator.
 */
export function compareValues(
	fieldValue: unknown,
	operator: ConditionalOperator,
	ruleValue: unknown,
): boolean {
	switch ( operator ) {
		case 'equals':
			return compareEquals( fieldValue, ruleValue );
		case 'not_equals':
			return !compareEquals( fieldValue, ruleValue );
		case 'contains':
			return compareContains( fieldValue, ruleValue );
		case 'not_contains':
			return !compareContains( fieldValue, ruleValue );
		case 'starts_with':
			return compareStartsWith( fieldValue, ruleValue );
		case 'ends_with':
			return compareEndsWith( fieldValue, ruleValue );
		case 'is_empty':
			return isEmpty( fieldValue );
		case 'is_not_empty':
			return !isEmpty( fieldValue );
		case 'greater_than':
			return compareGreaterThan( fieldValue, ruleValue );
		case 'less_than':
			return compareLessThan( fieldValue, ruleValue );
		case 'greater_or_equal':
			return compareGreaterOrEqual( fieldValue, ruleValue );
		case 'less_or_equal':
			return compareLessOrEqual( fieldValue, ruleValue );
		case 'in':
			return compareIn( fieldValue, ruleValue );
		case 'not_in':
			return !compareIn( fieldValue, ruleValue );
		case 'checked':
			return isChecked( fieldValue );
		case 'unchecked':
			return !isChecked( fieldValue );
		case 'includes':
			return compareIncludes( fieldValue, ruleValue );
		case 'not_includes':
			return !compareIncludes( fieldValue, ruleValue );
		default:
			return false;
	}
}

/**
 * Evaluates a single conditional logic rule against form data. A rule may
 * test one of a composite field's sub-inputs as `{field}.{key}` (since 1.6.0).
 */
export function evaluateRule(
	rule: ConditionalLogicRule,
	formData: Record<string, unknown>,
	uuidToName: Record<string, string>,
): boolean {
	const fieldRef = rule.field;

	if ( !fieldRef ) {
		return true;
	}

	const [fieldName, subKey] = resolveFieldReference( fieldRef, uuidToName, formData );
	let fieldValue: unknown = formData[fieldName] ?? null;

	if ( subKey !== undefined ) {
		fieldValue = fieldValue && typeof fieldValue === 'object' && !Array.isArray( fieldValue )
			? ( fieldValue as Record<string, unknown> )[subKey] ?? null
			: null;
	} else {
		// A rule on a whole composite field compares its formatted value.
		fieldValue = formatCompositeForCondition( fieldValue );
	}

	return compareValues( fieldValue, rule.operator, rule.value ?? '' );
}

/**
 * Evaluates conditional logic for a single field.
 *
 * @returns True if the field should be visible.
 */
export function evaluateFieldVisibility(
	logic: ConditionalLogic | null,
	formData: Record<string, unknown>,
	uuidToName: Record<string, string>,
): boolean {
	if ( !logic || !logic.rules || logic.rules.length === 0 ) {
		return true;
	}

	const action = logic.action ?? 'show';
	const logicType = logic.logic ?? 'all';

	const results = logic.rules.map( ( rule: ConditionalLogicRule ) =>
		evaluateRule( rule, formData, uuidToName ),
	);

	const conditionsMet =
		logicType === 'all'
			? results.every( Boolean )
			: results.some( Boolean );

	return action === 'show' ? conditionsMet : !conditionsMet;
}

/**
 * Put values in the shape the server evaluates rules against, after
 * `SubmissionService::normalizeSubmittedValues()`: a number typed in its
 * format is a plain decimal (`1,000` is `1000`), a URL typed without a
 * scheme gets one, a color is `#rrggbb`, emptied rich text is blank, a list
 * keeps only its filled rows (none is empty), and a date and time is
 * `YYYY-MM-DD HH:MM` (since 1.6.0).
 */
function normalizeConditionValues( fields: FormField[], formData: Record<string, unknown> ): Record<string, unknown> {
	const values = { ...formData };

	for ( const field of fields ) {
		if ( !Object.prototype.hasOwnProperty.call( values, field.name ) ) {
			continue;
		}

		const value = values[field.name];
		const format = field.type === 'number' ? getNumberFormat( field ) : undefined;

		if ( format !== undefined ) {
			if ( typeof value === 'string' ) {
				values[field.name] = normalizeFormattedNumber( value, format );
			} else if ( typeof value === 'number' && format === 'currency' ) {
				values[field.name] = normalizeFormattedNumber( String( value ), format );
			}
		} else if ( field.type === 'url' && typeof value === 'string' ) {
			values[field.name] = withUrlScheme( value, getAllowedUrlSchemes( field ) );
		} else if ( usesRichText( field ) && typeof value === 'string' ) {
			values[field.name] = blankIfEmptyRichText( value );
		} else if ( field.type === 'color' && typeof value === 'string' ) {
			values[field.name] = normalizeColor( value );
		} else if ( isListField( field ) ) {
			const rows = toListRows( field, value ).filter( ( row ) => Object.values( row ).some( ( cell ) => cell.trim() !== '' ) );

			values[field.name] = rows.length > 0 ? rows : null;
		} else if ( field.type === 'datetime' && typeof value === 'string' ) {
			values[field.name] = value.replace( 'T', ' ' );
		}
	}

	return values;
}

/**
 * Evaluates conditional logic for all fields in a form. A hidden section
 * hides every field it groups (since 1.6.0; see `applySectionVisibility()`).
 *
 * @returns A map of field names to their hidden state (true = hidden).
 */
export function getHiddenFields(
	fields: FormField[],
	formData: Record<string, unknown>,
): Record<string, boolean> {
	const { uuidToName } = buildFieldMaps( fields );
	const hidden: Record<string, boolean> = {};
	const values = normalizeConditionValues( fields, formData );

	for ( const field of fields ) {
		// A CAPTCHA is always shown, mirroring `ConditionalLogicService::evaluateField()`.
		const isVisible = field.type === 'captcha' || evaluateFieldVisibility(
			field.conditional_logic,
			values,
			uuidToName,
		);

		hidden[field.name] = !isVisible;
	}

	return applySectionVisibility( fields, hidden );
}
