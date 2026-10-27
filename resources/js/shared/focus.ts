/**
 * Focus moves for the React and Vue renderers.
 *
 * Mirrors the Livewire renderer: on a new step, focus moves to the first
 * field of the step (or its first invalid field), and when validation fails,
 * to the first invalid field or the form's error summary, so keyboard and
 * screen reader users land where they need to act (WCAG 2.4.3, 3.3.1).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

/** The controls a visitor fills in, as Livewire's `focusFirstField()` finds them. */
export const FIELD_CONTROL_SELECTOR = [
	'input:not([type="hidden"]):not([disabled]):not([tabindex="-1"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[contenteditable="true"]',
].join( ', ' );

/** Marks a form's error summary, which takes focus when no field is invalid. */
export const ERROR_SUMMARY_ATTRIBUTE = 'data-forms-error-summary';

/**
 * Focus an element, or the first control inside it when it is a group such as
 * a fieldset or radiogroup.
 */
function focusElement( element: Element | null ): boolean {
	if ( !( element instanceof HTMLElement ) ) {
		return false;
	}

	const target = element.matches( FIELD_CONTROL_SELECTOR ) || element.tabIndex >= 0 || element.hasAttribute( 'tabindex' )
		? element
		: element.querySelector<HTMLElement>( FIELD_CONTROL_SELECTOR ) ?? element;

	target.focus();

	return document.activeElement === target;
}

/**
 * Focus the first invalid field inside a container, or else its error
 * summary.
 *
 * @since 1.6.0
 *
 * @returns Whether something took focus.
 */
export function focusFirstInvalid( root: HTMLElement | null | undefined ): boolean {
	if ( !root ) {
		return false;
	}

	return Array.from( root.querySelectorAll( '[aria-invalid="true"]' ) ).some( focusElement )
		|| focusElement( root.querySelector( `[${ERROR_SUMMARY_ATTRIBUTE}]` ) );
}

/**
 * Focus the first field a visitor can fill in inside a container.
 *
 * @since 1.6.0
 *
 * @returns Whether a field took focus.
 */
export function focusFirstField( root: HTMLElement | null | undefined ): boolean {
	// A control that cannot take focus, such as a CAPTCHA's hidden textarea, is skipped
	return root ? Array.from( root.querySelectorAll( FIELD_CONTROL_SELECTOR ) ).some( focusElement ) : false;
}
