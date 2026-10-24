/**
 * `v-input-attrs` directive.
 *
 * Sets attributes such as `maxlength`, `minlength` and `inputmode` on the
 * native input or textarea inside a form component (since 1.6.0). The
 * @artisanpack-ui/vue inputs do not pass unknown attributes through to their
 * native control, so binding them on the component would land on its
 * wrapper. An undefined or null value removes the attribute.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { Directive } from 'vue';

/** The attributes to set, keyed by name. */
export type InputAttrs = Record<string, string | number | null | undefined>;

/** Find the native control a component renders. */
function findControl( el: HTMLElement ): HTMLElement | null {
	if ( el.matches( 'input, textarea' ) ) {
		return el;
	}

	return el.querySelector( 'input:not([type="hidden"]), textarea' );
}

/** Set or remove each attribute. */
function apply( el: HTMLElement, attrs: InputAttrs | undefined ): void {
	const control = findControl( el );

	if ( !control || !attrs ) {
		return;
	}

	for ( const [name, value] of Object.entries( attrs ) ) {
		if ( value === undefined || value === null ) {
			control.removeAttribute( name );
		} else {
			control.setAttribute( name, String( value ) );
		}
	}
}

export const vInputAttrs: Directive<HTMLElement, InputAttrs | undefined> = {
	mounted: ( el, binding ) => apply( el, binding.value ),
	updated: ( el, binding ) => apply( el, binding.value ),
};
