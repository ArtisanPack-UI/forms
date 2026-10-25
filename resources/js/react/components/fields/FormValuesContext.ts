/**
 * Form values context.
 *
 * Gives field components the form's fields and current values, so an HTML
 * block can show what the visitor entered in its `{field_name}` merge tags
 * (see `applyLiveMergeTags()`). `FormRenderer` provides it; outside a form
 * the context is null and merge tags are left as typed.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { createContext, useContext } from 'react';

import type { LiveMergeTagValues } from '../../../shared/liveMergeTags';
import type { FormField } from '../../../types/artisanpack-forms';

/** The fields and values of the form a field renders in. */
export interface FormValuesContextValue extends LiveMergeTagValues {
	fields: FormField[];
}

/**
 * The fields and values of the form a field renders in, or null outside a form.
 *
 * @since 1.6.0
 */
export const FormValuesContext = createContext<FormValuesContextValue | null>( null );

/**
 * Get the fields and values of the form the calling field renders in.
 *
 * @since 1.6.0
 *
 * @returns The fields and values, or null when no form provides them.
 */
export function useFormValues(): FormValuesContextValue | null {
	return useContext( FormValuesContext );
}
