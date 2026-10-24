/**
 * Submit hooks context.
 *
 * Gives field components the form's submit hook registry (see
 * `createSubmitHooks()`), so a field can add values to a submission just
 * before it is sent — such as an invisible CAPTCHA's token — and learn how
 * the submission went. `FormRenderer` provides the registry `useForm`
 * creates; outside a form the context is null and fields register nothing.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { createContext, useContext } from 'react';

import type { SubmitHooks } from '../../../shared/captcha';

/**
 * The submit hook registry of the form a field renders in, or null outside
 * a form.
 *
 * @since 1.6.0
 */
export const SubmitHooksContext = createContext<SubmitHooks | null>( null );

/**
 * Get the submit hook registry of the form the calling field renders in.
 *
 * @since 1.6.0
 *
 * @returns The registry, or null when no form provides one.
 */
export function useSubmitHooks(): SubmitHooks | null {
	return useContext( SubmitHooksContext );
}
