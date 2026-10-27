/**
 * List (repeater) field helpers.
 *
 * Client-side mirror of `ListFieldType`. The server parses the field's
 * columns and sends them, with the row limits and row label, as `field.list`.
 * A list's value is an array of rows, each a map of column keys to text,
 * submitted as `data[{field}][{row}][{column}]`; its errors are keyed
 * `{field}` for the row count and `{field}.{row}.{column}` for a cell.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField, ListColumn } from '../types/artisanpack-forms';
import { t } from './i18n';

/** The longest value a cell accepts, mirroring `ListFieldType::CELL_MAX_LENGTH`. */
export const LIST_CELL_MAX_LENGTH = 255;

/** The most rows a list may take, mirroring `ListFieldType::MAX_ROWS`. */
export const LIST_MAX_ROWS = 100;

/** One row of a list: column keys to text. */
export type ListRow = Record<string, string>;

/** Whether a field is a list. */
export function isListField( field: FormField ): boolean {
	return field.type === 'list';
}

/** Get a list field's columns. */
export function getListColumns( field: FormField ): ListColumn[] {
	return field.list?.columns ?? [];
}

/** Get the most rows a list takes. */
export function getListMaxRows( field: FormField ): number {
	return field.list?.max_rows ?? LIST_MAX_ROWS;
}

/** Get the fewest filled rows a list takes once any row is filled, or undefined. */
export function getListMinRows( field: FormField ): number | undefined {
	return field.list?.min_rows ?? undefined;
}

/** Get the name of one row ("Attendee"), used in the add and remove buttons. */
export function getListRowLabel( field: FormField ): string {
	return field.list?.row_label || t( 'Row' );
}

/** Get an empty row. */
export function getEmptyListRow( field: FormField ): ListRow {
	return Object.fromEntries( getListColumns( field ).map( ( column ) => [column.key, ''] ) );
}

/** Get the rows a list starts with: as many empty rows as its minimum, and at least one. */
export function getInitialListRows( field: FormField ): ListRow[] {
	return Array.from( { length: Math.max( 1, getListMinRows( field ) ?? 1 ) }, () => getEmptyListRow( field ) );
}

/** Read a value as a list's rows, with every column's cell as text. */
export function toListRows( field: FormField, value: unknown ): ListRow[] {
	if ( !Array.isArray( value ) ) {
		return [];
	}

	return value
		.filter( ( row ): row is Record<string, unknown> => row !== null && typeof row === 'object' && !Array.isArray( row ) )
		.map( ( row ) => Object.fromEntries( getListColumns( field ).map( ( column ) => {
			const cell = row[column.key];

			return [column.key, typeof cell === 'string' || typeof cell === 'number' ? String( cell ) : ''];
		} ) ) );
}

/** Whether a row has any filled cell. */
function isFilledRow( row: ListRow ): boolean {
	return Object.values( row ).some( ( cell ) => cell.trim() !== '' );
}

/**
 * Validate a list, mirroring `ListFieldType::rules()`: blank rows are
 * ignored; a required list needs a filled row; at least the min and at most
 * the max rows once any is filled; and each cell's column type.
 *
 * @returns Error arrays keyed `{field}` and `{field}.{row}.{column}` (empty when valid).
 */
export function validateListField( field: FormField, value: unknown ): Record<string, string[]> {
	const rows = toListRows( field, value );
	const filled = rows.map( ( row, index ) => ( { row, index } ) ).filter( ( { row } ) => isFilledRow( row ) );
	const label = field.label ?? field.name;
	const errors: Record<string, string[]> = {};
	const min = getListMinRows( field );
	const max = getListMaxRows( field );

	if ( filled.length === 0 ) {
		if ( field.is_required ) {
			errors[field.name] = [field.required_message ?? t( 'Please fill in at least one row of :field.', { field: label } )];
		}

		return errors;
	}

	if ( filled.length > max ) {
		errors[field.name] = [field.error_message ?? ( max === 1
			? t( ':field may have no more than :count row.', { field: label, count: max } )
			: t( ':field may have no more than :count rows.', { field: label, count: max } ) )];
	} else if ( min !== undefined && filled.length < min ) {
		errors[field.name] = [field.error_message ?? ( min === 1
			? t( 'Please fill in at least :count row of :field.', { field: label, count: min } )
			: t( 'Please fill in at least :count rows of :field.', { field: label, count: min } ) )];
	}

	for ( const { row, index } of filled ) {
		for ( const column of getListColumns( field ) ) {
			const cell = ( row[column.key] ?? '' ).trim();
			const replacements = { field: label, column: column.label };
			let message: string | undefined;

			if ( cell === '' ) {
				continue;
			}

			if ( column.type === 'select' && !column.options.some( ( option ) => option.value === cell ) ) {
				message = t( ':field: please choose a valid :column.', replacements );
			} else if ( column.type === 'number' ) {
				message = Number.isFinite( Number( cell ) ) ? undefined : t( ':field: :column must be a number.', replacements );
			} else if ( cell.length > LIST_CELL_MAX_LENGTH ) {
				message = t( ':field: :column may not be longer than :max characters.', { ...replacements, max: LIST_CELL_MAX_LENGTH } );
			} else if ( column.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( cell ) ) {
				message = t( ':field: :column must be a valid email address.', replacements );
			}

			if ( message !== undefined ) {
				errors[`${field.name}.${index}.${column.key}`] = [field.error_message ?? message];
			}
		}
	}

	return errors;
}
