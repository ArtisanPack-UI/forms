/**
 * `v-described-by` directive.
 *
 * Sets `aria-describedby` on the native input, select or textarea inside a
 * form component, for fields whose description the field layout renders
 * (see `FieldLayout.vue`). The @artisanpack-ui/vue inputs only describe their
 * control by their own hint and error, and do not pass the attribute through.
 * An undefined value leaves the component's own attribute alone.
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

/** Set the attribute when a value is given. */
function apply( el: HTMLElement, ids: string | undefined ): void {
	const control = findControl( el );

	if ( control && ids !== undefined ) {
		control.setAttribute( 'aria-describedby', ids );
	}
}

export const vDescribedBy: Directive<HTMLElement, string | undefined> = {
	mounted: ( el, binding ) => apply( el, binding.value ),
	updated: ( el, binding ) => apply( el, binding.value ),
};
