/**
 * Submit hook injection for Vue field components.
 *
 * `FormRenderer` provides its form's submit hook registry (see
 * `createSubmitHooks()`) under `SUBMIT_HOOKS_KEY`, so a field such as the
 * CAPTCHA can add values to the submission and learn how it went.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { inject } from 'vue';
import type { InjectionKey } from 'vue';

import type { SubmitHooks } from '../../shared/captcha';

/**
 * The injection key a form's submit hook registry is provided under.
 *
 * @since 1.6.0
 */
export const SUBMIT_HOOKS_KEY: InjectionKey<SubmitHooks> = Symbol( 'artisanpack-forms-submit-hooks' );

/**
 * Get the submit hook registry of the form a component renders in, or null
 * outside a form renderer.
 *
 * @since 1.6.0
 */
export function useSubmitHooks(): SubmitHooks | null {
	return inject( SUBMIT_HOOKS_KEY, null );
}
