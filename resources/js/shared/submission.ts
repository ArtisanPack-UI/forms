/**
 * Submission helpers shared by the React and Vue `useForm`.
 *
 * Builds the multipart body the submit API expects (`data[...]` values) in
 * the shape Laravel's validator reads.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

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
