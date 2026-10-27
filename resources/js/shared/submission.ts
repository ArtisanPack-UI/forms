/**
 * Submission helpers shared by the React and Vue `useForm`.
 *
 * Builds the multipart body the submit API expects (`data[...]` values) in
 * the shape Laravel's validator reads, and maps the API's validation errors
 * back to the form's fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';
import { getErrorFieldName } from './composite';
import { getConfirmationKey, requiresEmailConfirmation } from './fieldSettings';

/**
 * Convert a value to the string sent for it. Booleans (checkbox, toggle and
 * consent values) become `'1'`/`'0'`, the forms Laravel's `boolean` rule
 * accepts; `"true"`/`"false"` fail it.
 *
 * @since 1.6.0
 */
export function toFormValue( value: unknown ): string {
	if ( typeof value === 'boolean' ) {
		return value ? '1' : '0';
	}

	return String( value ?? '' );
}

/**
 * Append one field's value to a submission body under `data[name]`: list
 * rows as `data[name][i][column]`, other arrays as `data[name][]`, composite
 * maps as `data[name][part]` and anything else as a scalar. Null and
 * undefined scalars are left out.
 *
 * @since 1.6.0
 */
export function appendSubmissionValue( body: FormData, name: string, value: unknown ): void {
	if ( Array.isArray( value ) ) {
		value.forEach( ( item, index ) => {
			if ( item !== null && typeof item === 'object' ) {
				// A list's rows are maps of columns.
				for ( const [column, cell] of Object.entries( item as Record<string, unknown> ) ) {
					body.append( `data[${name}][${index}][${column}]`, toFormValue( cell ) );
				}
			} else {
				body.append( `data[${name}][]`, toFormValue( item ) );
			}
		} );
	} else if ( value !== null && typeof value === 'object' ) {
		// A composite field's value is a map of sub-inputs.
		for ( const [part, partValue] of Object.entries( value as Record<string, unknown> ) ) {
			body.append( `data[${name}][${part}]`, toFormValue( partValue ) );
		}
	} else if ( value !== null && value !== undefined ) {
		body.append( `data[${name}]`, toFormValue( value ) );
	}
}

/**
 * Map the submit API's 422 errors to the renderer's error keys.
 *
 * The `data.` and `files.` prefixes are stripped, so `data.email` becomes
 * `email` and `data.address.city` stays keyed by sub-input (`address.city`).
 * An error for one file of a multi-file field (`files.docs.0`) is shown on
 * the field (`docs`). An error that belongs to no field on the form, such
 * as the request-level `data` error, goes to `_form` so the visitor still
 * sees it.
 *
 * @since 1.6.0
 *
 * @param  raw  The response's `errors` object.
 * @param  fields  The form's fields.
 */
export function mapServerErrors( raw: unknown, fields: FormField[] ): Record<string, string[]> {
	const mapped: Record<string, string[]> = {};

	if ( !raw || typeof raw !== 'object' ) {
		return mapped;
	}

	const known = new Set<string>( ['_form'] );
	const fileFields = new Set<string>();

	for ( const field of fields ) {
		known.add( field.name );

		if ( field.type === 'file' ) {
			fileFields.add( field.name );
		}

		if ( requiresEmailConfirmation( field ) ) {
			known.add( getConfirmationKey( field ) );
		}
	}

	const add = ( key: string, messages: string[] ): void => {
		const existing = mapped[key] ?? [];

		mapped[key] = [...existing, ...messages.filter( ( message ) => !existing.includes( message ) )];
	};

	for ( const [key, value] of Object.entries( raw as Record<string, unknown> ) ) {
		const messages = ( Array.isArray( value ) ? value : [value] )
			.filter( ( message ): message is string => typeof message === 'string' && message !== '' );

		if ( messages.length === 0 ) {
			continue;
		}

		const name = key.replace( /^(data|files)\./, '' );
		const base = getErrorFieldName( name );

		if ( fileFields.has( base ) ) {
			// One file of a multi-file field (`docs.0`) is reported on the field.
			add( base, messages );
		} else {
			add( known.has( base ) ? name : '_form', messages );
		}
	}

	return mapped;
}
