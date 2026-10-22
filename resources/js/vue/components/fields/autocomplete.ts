/**
 * `v-autocomplete` directive.
 *
 * Sets a field's `autocomplete` token (WCAG 1.3.5) on the native input,
 * select or textarea inside a form component. The @artisanpack-ui/vue inputs
 * do not pass unknown attributes through to their native control, so binding
 * `autocomplete` on the component would land on its wrapper.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { Directive } from 'vue';

/** Find the native control a component renders. */
function findControl( el: HTMLElement ): HTMLElement | null {
	if ( el.matches( 'input, select, textarea' ) ) {
		return el;
	}

	return el.querySelector( 'input:not([type="hidden"]), select, textarea' );
}

/** Set or remove the attribute. */
function apply( el: HTMLElement, token: string | null | undefined ): void {
	const control = findControl( el );

	if ( !control ) {
		return;
	}

	if ( token ) {
		control.setAttribute( 'autocomplete', token );
	} else {
		control.removeAttribute( 'autocomplete' );
	}
}

export const vAutocomplete: Directive<HTMLElement, string | null | undefined> = {
	mounted: ( el, binding ) => apply( el, binding.value ),
	updated: ( el, binding ) => apply( el, binding.value ),
};
