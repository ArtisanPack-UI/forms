/**
 * Likert / matrix field helpers.
 *
 * Client-side mirror of `MatrixFieldType`. The server parses the field's
 * rows and columns and sends them as `field.matrix`. A grid's value maps
 * each row key to a column value (`''` while unanswered), submitted as
 * `data[{field}][{row}]`; its errors are keyed `{field}` and
 * `{field}.{row}`. A single scale's value is one column value.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField, MatrixColumn, MatrixRow } from '../types/artisanpack-forms';
import { t } from './i18n';

/** Whether a field is a matrix. */
export function isMatrixField( field: FormField ): boolean {
	return field.type === 'matrix';
}

/** Get a matrix's rows (statements); empty for a single scale. */
export function getMatrixRows( field: FormField ): MatrixRow[] {
	return field.matrix?.single ? [] : ( field.matrix?.rows ?? [] );
}

/** Get a matrix's columns (the scale). */
export function getMatrixColumns( field: FormField ): MatrixColumn[] {
	return field.matrix?.columns ?? [];
}

/** Whether a matrix is a single scale rather than a grid. */
export function isSingleScale( field: FormField ): boolean {
	return getMatrixRows( field ).length === 0;
}

/** Read a grid's value as answers keyed by row, `''` for each unanswered row. */
export function toMatrixAnswers( field: FormField, value: unknown ): Record<string, string> {
	const answers = value !== null && typeof value === 'object' && !Array.isArray( value ) ? value as Record<string, unknown> : {};

	return Object.fromEntries( getMatrixRows( field ).map( ( row ) => {
		const answer = answers[row.key];

		return [row.key, typeof answer === 'string' || typeof answer === 'number' ? String( answer ) : ''];
	} ) );
}

/**
 * Get the value a matrix starts with: every row unanswered, or an empty
 * scale. Mirrors `MatrixFieldType::initialValue()`.
 */
export function getInitialMatrixValue( field: FormField, source?: unknown ): Record<string, string> | string {
	if ( isSingleScale( field ) ) {
		return typeof source === 'string' ? source : '';
	}

	return toMatrixAnswers( field, source );
}

/**
 * Validate a matrix, mirroring `MatrixFieldType::rules()`: each answer must
 * be one of the columns, and a required matrix needs every row answered (or
 * its scale chosen).
 *
 * @returns Error arrays keyed `{field}` and `{field}.{row}` (empty when valid).
 */
export function validateMatrixField( field: FormField, value: unknown ): Record<string, string[]> {
	const label = field.label ?? field.name;
	const values = new Set( getMatrixColumns( field ).map( ( column ) => column.value ) );
	const errors: Record<string, string[]> = {};

	if ( isSingleScale( field ) ) {
		const choice = typeof value === 'string' || typeof value === 'number' ? String( value ).trim() : '';

		if ( choice === '' ) {
			if ( field.is_required ) {
				errors[field.name] = [field.required_message ?? t( 'The :field field is required.', { field: label } )];
			}
		} else if ( !values.has( choice ) ) {
			errors[field.name] = [field.error_message ?? t( 'Please choose one of the available answers for :field.', { field: label } )];
		}

		return errors;
	}

	const answers = toMatrixAnswers( field, value );

	for ( const row of getMatrixRows( field ) ) {
		const answer = answers[row.key].trim();
		const replacements = { field: label, row: row.label };

		if ( answer === '' ) {
			if ( field.is_required ) {
				errors[`${field.name}.${row.key}`] = [field.required_message ?? t( ':field: please answer ":row".', replacements )];
			}
		} else if ( !values.has( answer ) ) {
			errors[`${field.name}.${row.key}`] = [field.error_message ?? t( ':field: please choose a valid answer for ":row".', replacements )];
		}
	}

	return errors;
}
