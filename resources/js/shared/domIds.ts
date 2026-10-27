/**
 * Element IDs for a form instance's fields.
 *
 * The React and Vue renderers give every form they render an ID prefix
 * (`FormField.id_prefix`), so two forms on one page that share a field name,
 * such as a newsletter form and a contact form with an `email` field, do not
 * produce duplicate IDs, and labels, `aria-describedby` and focus moves
 * stay within their own form.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

/** Counts the form instances given an ID prefix without a framework ID. */
let instanceCount = 0;

/**
 * The key a field's element IDs are built from: its name, after its form
 * instance's ID prefix. Fields rendered outside a form instance, such as in
 * the builder's preview, keep their name.
 *
 * @since 1.6.0
 */
export function fieldIdKey( field: Pick<FormField, 'name' | 'id_prefix'> ): string {
	return `${field.id_prefix ?? ''}${field.name}`;
}

/**
 * Make a form instance's ID prefix: from a framework ID (React's `useId()`)
 * stripped to the characters IDs and CSS selectors take, or from a counter.
 *
 * @since 1.6.0
 */
export function createIdPrefix( frameworkId?: string ): string {
	const id = frameworkId?.replace( /[^A-Za-z0-9_-]/g, '' ) || String( ++instanceCount );

	return `apf${id}-`;
}

/**
 * Give each of a form's fields its instance's ID prefix (see `fieldIdKey()`).
 *
 * @since 1.6.0
 */
export function withIdPrefix( fields: FormField[], prefix: string ): FormField[] {
	return fields.map( ( field ) => ( { ...field, id_prefix: prefix } ) );
}
