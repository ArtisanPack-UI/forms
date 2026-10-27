/**
 * Form values injection for Vue field components.
 *
 * `FormRenderer` provides its form's fields and current values under
 * `FORM_VALUES_KEY`, so an HTML block can show what the visitor entered in
 * its `{field_name}` merge tags (see `applyLiveMergeTags()`).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { inject } from 'vue';
import type { InjectionKey, Ref } from 'vue';

import type { LiveMergeTagValues } from '../../shared/liveMergeTags';
import type { FormField } from '../../types/artisanpack-forms';

/** The fields and values of the form a component renders in. */
export interface FormValues extends LiveMergeTagValues {
	fields: FormField[];
}

/**
 * The injection key a form's fields and values are provided under.
 *
 * @since 1.6.0
 */
export const FORM_VALUES_KEY: InjectionKey<Ref<FormValues>> = Symbol( 'artisanpack-forms-values' );

/**
 * Get the fields and values of the form a component renders in, or null
 * outside a form renderer.
 *
 * @since 1.6.0
 */
export function useFormValues(): Ref<FormValues> | null {
	return inject( FORM_VALUES_KEY, null );
}
