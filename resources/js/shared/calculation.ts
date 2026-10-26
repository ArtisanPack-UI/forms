/**
 * Calculation field helpers.
 *
 * Client-side mirror of `Calculation` and `CalculationFieldType`: a formula
 * such as `{quantity} * {price}` of numbers, field merge tags, `+ - * /` and
 * parentheses, read by a small recursive descent parser (never `eval`). The
 * renderers show the result as the visitor types; the server works it out
 * again on submit and ignores the value sent (see
 * `SubmissionService::applyCalculations()`).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';
import { getHiddenFields } from './conditionalLogic';
import { t } from './i18n';
import { getNumberFormat, normalizeFormattedNumber } from './numberFormat';

/** The longest formula accepted, mirroring `Calculation::MAX_LENGTH`. */
export const CALCULATION_MAX_LENGTH = 1000;

/** The deepest nesting accepted, mirroring `Calculation::MAX_DEPTH`. */
const CALCULATION_MAX_DEPTH = 32;

/** The decimal places used when the field sets none, mirroring `CalculationFieldType::DEFAULT_DECIMALS`. */
const DEFAULT_DECIMALS = 2;

/** One token, mirroring `Calculation::TOKEN_PATTERN`. */
const TOKEN_PATTERN = /\s*(?:(\d+(?:\.\d+)?|\.\d+)|\{([A-Za-z0-9_-]+)\}|([-+*/()]))/y;

/** A formula token. */
export interface CalculationToken {
	type: 'number' | 'field' | 'operator';
	value: string;
}

/** Whether a field is a calculation. */
export function isCalculationField( field: FormField ): boolean {
	return field.type === 'calculation';
}

/** Get a calculation's formula and decimal places. */
export function getCalculationSettings( field: FormField ): { formula: string; decimals: number } {
	return {
		formula: field.calculation?.formula ?? '',
		decimals: field.calculation?.decimals ?? DEFAULT_DECIMALS,
	};
}

/**
 * Split a formula into tokens, or null when it holds anything else or is too
 * long. Mirrors `Calculation::tokenize()`.
 */
export function tokenizeFormula( formula: string ): CalculationToken[] | null {
	if ( formula.length > CALCULATION_MAX_LENGTH ) {
		return null;
	}

	const tokens: CalculationToken[] = [];
	let offset = 0;

	while ( offset < formula.length ) {
		if ( formula.slice( offset ).trim() === '' ) {
			break;
		}

		TOKEN_PATTERN.lastIndex = offset;
		const match = TOKEN_PATTERN.exec( formula );

		if ( !match ) {
			return null;
		}

		offset = TOKEN_PATTERN.lastIndex;

		if ( match[1] !== undefined ) {
			tokens.push( { type: 'number', value: match[1] } );
		} else if ( match[2] !== undefined ) {
			tokens.push( { type: 'field', value: match[2] } );
		} else {
			tokens.push( { type: 'operator', value: match[3] } );
		}
	}

	return tokens;
}

/** Get the field names a formula's merge tags refer to, each once. Mirrors `Calculation::references()`. */
export function getFormulaReferences( formula: string ): string[] {
	const names: string[] = [];

	for ( const token of tokenizeFormula( formula ) ?? [] ) {
		if ( token.type === 'field' && !names.includes( token.value ) ) {
			names.push( token.value );
		}
	}

	return names;
}

/**
 * Read a field value as a number: the value, the sum of a list's numbers, 1
 * for true, otherwise 0. Mirrors `Calculation::toNumber()`.
 */
export function toCalculationNumber( value: unknown ): number {
	if ( typeof value === 'number' ) {
		return Number.isFinite( value ) ? value : 0;
	}

	if ( value === true ) {
		return 1;
	}

	if ( typeof value === 'string' ) {
		const text = value.trim();

		// `is_numeric()` accepts what `Number()` does, less hex, binary and `Infinity`
		if ( text === '' || !/^[+-]?(\d+\.?\d*|\.\d+)(e[+-]?\d+)?$/i.test( text ) ) {
			return 0;
		}

		const num = Number( text );

		return Number.isFinite( num ) ? num : 0;
	}

	if ( Array.isArray( value ) ) {
		return value.reduce<number>( ( sum, item ) => sum + ( Array.isArray( item ) ? 0 : toCalculationNumber( item ) ), 0 );
	}

	return 0;
}

/** Parses and evaluates one formula, mirroring the `Calculation` parser. */
class FormulaParser {
	private position = 0;

	private readonly tokens: CalculationToken[];

	private readonly resolve: ( name: string ) => unknown;

	/** Whether the formula is only being checked, so a division by zero is not an error. */
	private readonly checking: boolean;

	constructor( tokens: CalculationToken[], resolve: ( name: string ) => unknown, checking = false ) {
		this.tokens = tokens;
		this.resolve = resolve;
		this.checking = checking;
	}

	/** Evaluate the whole formula, or null when it has no result. */
	run(): number | null {
		if ( this.tokens.length === 0 ) {
			return null;
		}

		const result = this.expression( 0 );

		if ( result === null || this.position !== this.tokens.length || !Number.isFinite( result ) ) {
			return null;
		}

		return result;
	}

	private expression( depth: number ): number | null {
		let value = this.term( depth );

		while ( value !== null && this.peekOperator( ['+', '-'] ) ) {
			const operator = this.tokens[this.position++].value;
			const right = this.term( depth );

			if ( right === null ) {
				return null;
			}

			value = operator === '+' ? value + right : value - right;
		}

		return value;
	}

	private term( depth: number ): number | null {
		let value = this.factor( depth );

		while ( value !== null && this.peekOperator( ['*', '/'] ) ) {
			const operator = this.tokens[this.position++].value;
			const right = this.factor( depth );

			if ( right === null ) {
				return null;
			}

			if ( operator === '/' && right === 0 ) {
				if ( this.checking ) {
					continue;
				}

				return null;
			}

			value = operator === '*' ? value * right : value / right;
		}

		return value;
	}

	private factor( depth: number ): number | null {
		if ( depth > CALCULATION_MAX_DEPTH ) {
			return null;
		}

		const token = this.tokens[this.position];

		if ( token === undefined ) {
			return null;
		}

		this.position++;

		if ( token.type === 'number' ) {
			return Number( token.value );
		}

		if ( token.type === 'field' ) {
			return toCalculationNumber( this.resolve( token.value ) );
		}

		if ( token.value === '-' || token.value === '+' ) {
			const value = this.factor( depth + 1 );

			return value === null || token.value === '+' ? value : -value;
		}

		if ( token.value === '(' ) {
			const value = this.expression( depth + 1 );

			if ( value === null || !this.peekOperator( [')'] ) ) {
				return null;
			}

			this.position++;

			return value;
		}

		return null;
	}

	private peekOperator( operators: string[] ): boolean {
		const token = this.tokens[this.position];

		return token !== undefined && token.type === 'operator' && operators.includes( token.value );
	}
}

/**
 * Evaluate a formula, or null when it is not valid, divides by zero or
 * overflows. Mirrors `Calculation::evaluate()`.
 */
export function evaluateFormula( formula: string, resolve: ( name: string ) => unknown ): number | null {
	const tokens = tokenizeFormula( formula );

	return tokens === null ? null : new FormulaParser( tokens, resolve ).run();
}

/**
 * Check a formula and explain the first problem, or return undefined when it
 * is valid. Mirrors `Calculation::check()`.
 *
 * @param fieldNames The names a merge tag may use; omit to accept any.
 */
export function checkFormula( formula: string, fieldNames?: string[] ): string | undefined {
	if ( formula.trim() === '' ) {
		return t( 'Enter a formula, such as {quantity} * {price}.' );
	}

	if ( formula.length > CALCULATION_MAX_LENGTH ) {
		return t( 'The formula may not be longer than :max characters.', { max: CALCULATION_MAX_LENGTH } );
	}

	const tokens = tokenizeFormula( formula );

	if ( tokens === null ) {
		return t( 'The formula may only use numbers, field merge tags such as {price}, + - * / and parentheses.' );
	}

	if ( fieldNames ) {
		const unknown = getFormulaReferences( formula ).find( ( name ) => !fieldNames.includes( name ) );

		if ( unknown !== undefined ) {
			return t( 'The formula refers to {:name}, which is not a field on this form.', { name: unknown } );
		}
	}

	return new FormulaParser( tokens, () => 1, true ).run() === null
		? t( 'The formula is incomplete or has an operator or parenthesis out of place.' )
		: undefined;
}

/**
 * Format a result with a fixed number of decimal places, rounding half away
 * from zero, or `''` for no result. Mirrors `Calculation::format()`.
 */
export function formatCalculation( result: number | null, decimals: number ): string {
	if ( result === null ) {
		return '';
	}

	const factor = 10 ** decimals;
	// `round()` in PHP rounds the value as printed (1.005 to 1.01), so nudge by
	// a relative epsilon before rounding half away from zero
	const rounded = Math.sign( result ) * Math.round( Math.abs( result ) * factor * ( 1 + Number.EPSILON ) ) / factor;
	const formatted = rounded.toFixed( decimals );

	return Number( formatted ) === 0 ? ( 0 ).toFixed( decimals ) : formatted;
}

/**
 * Work out every calculation field's value from the other values. A
 * calculation may use another's result; one in a cycle has no result.
 * Numbers typed in a number field's format are read as plain decimals.
 * Mirrors `SubmissionService::applyCalculations()`.
 */
export function applyCalculations( fields: FormField[], values: Record<string, unknown> ): Record<string, unknown> {
	const calculations = new Map( fields.filter( isCalculationField ).map( ( field ) => [field.name, field] ) );

	if ( calculations.size === 0 ) {
		return values;
	}

	const formats = new Map( fields.filter( ( field ) => field.type === 'number' ).map( ( field ) => [field.name, getNumberFormat( field )] ) );
	const results = new Map<string, string>();
	const stack: string[] = [];
	const cyclic = new Set<string>();

	const resolve = ( name: string ): unknown => {
		const calculation = calculations.get( name );

		if ( calculation === undefined ) {
			const value = Object.prototype.hasOwnProperty.call( values, name ) ? values[name] : undefined;
			const format = formats.get( name );

			return format && typeof value === 'string' ? normalizeFormattedNumber( value, format ) : value;
		}

		const cached = results.get( name );

		if ( cached !== undefined ) {
			return cached;
		}

		const index = stack.indexOf( name );

		// A calculation reached again while it is being worked out is in a cycle
		if ( index !== -1 ) {
			stack.slice( index ).forEach( ( member ) => cyclic.add( member ) );

			return undefined;
		}

		const { formula, decimals } = getCalculationSettings( calculation );

		stack.push( name );
		const result = formatCalculation( evaluateFormula( formula, resolve ), decimals );
		stack.pop();

		const value = cyclic.has( name ) ? '' : result;
		results.set( name, value );

		return value;
	};

	const next = { ...values };

	for ( const name of calculations.keys() ) {
		next[name] = resolve( name );
	}

	return next;
}

/**
 * Work out the calculations and conditional logic together, mirroring
 * `FormRenderer::evaluateConditionalLogic()`: calculations first, so
 * conditions can use their results, then again without the values of the
 * fields conditional logic hides, since those are not submitted.
 */
export function resolveCalculatedValues(
	fields: FormField[],
	values: Record<string, unknown>,
): { values: Record<string, unknown>; hiddenFields: Record<string, boolean> } {
	const calculated = applyCalculations( fields, values );
	const hiddenFields = getHiddenFields( fields, calculated );

	if ( calculated === values || !Object.values( hiddenFields ).some( Boolean ) ) {
		return { values: calculated, hiddenFields };
	}

	const visible = Object.fromEntries( Object.entries( calculated ).filter( ( [name] ) => !hiddenFields[name] ) );
	const recalculated = applyCalculations( fields, visible );
	const merged = { ...calculated };

	for ( const field of fields.filter( isCalculationField ) ) {
		merged[field.name] = recalculated[field.name];
	}

	return { values: merged, hiddenFields: getHiddenFields( fields, merged ) };
}

/** The types a formula cannot read a number from, mirroring `CalculationFieldType::UNREADABLE_TYPES`. */
const UNREADABLE_TYPES = new Set( ['file', 'signature', 'captcha', 'section', 'heading', 'paragraph', 'divider', 'html'] );

/**
 * Get the fields a calculation's formula may use, for the builder's field
 * picker: every field but the calculation itself, administrative fields
 * and the types a formula cannot read. Mirrors `CalculationFieldType::formulaFields()`.
 */
export function getFormulaFields( field: FormField, fields: FormField[] ): Array<{ name: string; label: string }> {
	return fields
		.filter( ( candidate ) => candidate.id !== field.id && candidate.name !== field.name && !UNREADABLE_TYPES.has( candidate.type ) && !candidate.is_administrative )
		.map( ( candidate ) => {
			const adminLabel = ( candidate.field_config as { admin_label?: unknown } | null )?.admin_label;

			return { name: candidate.name, label: ( typeof adminLabel === 'string' && adminLabel.trim() !== '' ? adminLabel.trim() : '' ) || candidate.label || candidate.name };
		} );
}

/**
 * Insert a merge tag into a formula input at its cursor (or in place of its
 * selection), keep focus there, and return the new formula.
 */
export function insertFormulaTag( input: HTMLTextAreaElement | HTMLInputElement, tag: string ): string {
	const start = input.selectionStart ?? input.value.length;
	const end = input.selectionEnd ?? start;
	const formula = input.value.slice( 0, start ) + tag + input.value.slice( end );

	input.value = formula;
	input.focus();
	input.setSelectionRange( start + tag.length, start + tag.length );

	return formula;
}
