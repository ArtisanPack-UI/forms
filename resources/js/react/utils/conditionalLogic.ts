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

import type {
	ConditionalLogic,
	ConditionalLogicRule,
	ConditionalOperator,
	FormField,
} from '../../types/artisanpack-forms';

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
 * Resolves a field reference (UUID or name) to a field name.
 */
function resolveFieldName(
	fieldRef: string,
	uuidToName: Record<string, string>,
): string {
	return uuidToName[fieldRef] ?? fieldRef;
}

/**
 * Checks if a value is empty (null, undefined, empty string, or empty array).
 */
function isEmpty( value: unknown ): boolean {
	if ( value === null || value === undefined ) {
		return true;
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
 * Compares two values for equality, handling booleans, numbers, and strings.
 */
function compareEquals( fieldValue: unknown, ruleValue: unknown ): boolean {
	if ( typeof fieldValue === 'boolean' ) {
		if ( typeof ruleValue === 'string' ) {
			return fieldValue === ( ['true', '1', 'yes', 'on'].includes( ruleValue.toLowerCase() ) );
		}

		return fieldValue === Boolean( ruleValue );
	}

	const fieldNum = Number( fieldValue );
	const ruleNum = Number( ruleValue );

	if ( !isNaN( fieldNum ) && !isNaN( ruleNum ) && fieldValue !== '' && ruleValue !== '' ) {
		return fieldNum === ruleNum;
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
	const a = Number( fieldValue );
	const b = Number( ruleValue );

	if ( isNaN( a ) || isNaN( b ) ) {
		return false;
	}

	return a > b;
}

/**
 * Compares numeric values with less than.
 */
function compareLessThan( fieldValue: unknown, ruleValue: unknown ): boolean {
	const a = Number( fieldValue );
	const b = Number( ruleValue );

	if ( isNaN( a ) || isNaN( b ) ) {
		return false;
	}

	return a < b;
}

/**
 * Compares numeric values with greater than or equal.
 */
function compareGreaterOrEqual( fieldValue: unknown, ruleValue: unknown ): boolean {
	const a = Number( fieldValue );
	const b = Number( ruleValue );

	if ( isNaN( a ) || isNaN( b ) ) {
		return false;
	}

	return a >= b;
}

/**
 * Compares numeric values with less than or equal.
 */
function compareLessOrEqual( fieldValue: unknown, ruleValue: unknown ): boolean {
	const a = Number( fieldValue );
	const b = Number( ruleValue );

	if ( isNaN( a ) || isNaN( b ) ) {
		return false;
	}

	return a <= b;
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
			return true;
	}
}

/**
 * Evaluates a single conditional logic rule against form data.
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

	const fieldName = resolveFieldName( fieldRef, uuidToName );
	const fieldValue = formData[fieldName] ?? null;

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

	const results = logic.rules.map( ( rule ) =>
		evaluateRule( rule, formData, uuidToName ),
	);

	const conditionsMet =
		logicType === 'all'
			? results.every( Boolean )
			: results.some( Boolean );

	return action === 'show' ? conditionsMet : !conditionsMet;
}

/**
 * Evaluates conditional logic for all fields in a form.
 *
 * @returns A map of field names to their hidden state (true = hidden).
 */
export function getHiddenFields(
	fields: FormField[],
	formData: Record<string, unknown>,
): Record<string, boolean> {
	const { uuidToName } = buildFieldMaps( fields );
	const hidden: Record<string, boolean> = {};

	for ( const field of fields ) {
		const isVisible = evaluateFieldVisibility(
			field.conditional_logic,
			formData,
			uuidToName,
		);

		hidden[field.name] = !isVisible;
	}

	return hidden;
}
