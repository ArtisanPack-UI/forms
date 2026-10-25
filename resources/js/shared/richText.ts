/**
 * Rich text textarea support.
 *
 * Mirrors `RichText` on the server: limits count the text of rich text, not
 * its markup. The server sanitizes the HTML with `kses()` when it is stored
 * and shown, so the editors only need to produce it.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

import { t } from './i18n';

/** A toolbar command of the rich text editor. */
export interface RichTextCommand {
	/** The `document.execCommand()` command. */
	command: 'bold' | 'italic' | 'underline' | 'insertUnorderedList' | 'insertOrderedList' | 'createLink';
	/** The button's accessible name, translated. */
	label: string;
	/** The text shown on the button, hidden from screen readers. */
	text: string;
	/** Classes that style the button text. */
	className: string;
}

/** Get the editor's toolbar, in order. Mirrors the Livewire `rich-text` partial. */
export function getRichTextCommands(): RichTextCommand[] {
	return [
		{ command: 'bold', label: t( 'Bold' ), text: 'B', className: 'font-bold' },
		{ command: 'italic', label: t( 'Italic' ), text: 'I', className: 'italic' },
		{ command: 'underline', label: t( 'Underline' ), text: 'U', className: 'underline' },
		{ command: 'insertUnorderedList', label: t( 'Bulleted list' ), text: '•', className: '' },
		{ command: 'insertOrderedList', label: t( 'Numbered list' ), text: '1.', className: '' },
		{ command: 'createLink', label: t( 'Link' ), text: t( 'Link' ), className: 'underline' },
	];
}

/**
 * Run a toolbar command on the editor's selection. A link asks for its
 * address; a blank address removes the link.
 */
export function runRichTextCommand( editor: HTMLElement, command: RichTextCommand['command'] ): void {
	editor.focus();

	if ( command !== 'createLink' ) {
		document.execCommand( command );

		return;
	}

	const url = window.prompt( t( 'Link address (leave blank to remove the link)' ), '' );

	if ( url === null ) {
		return;
	}

	if ( url.trim() === '' ) {
		document.execCommand( 'unlink' );
	} else {
		document.execCommand( 'createLink', false, url.trim() );
	}
}

/** Get which toolbar commands are on for the selection inside the editor. */
export function getPressedCommands( editor: HTMLElement ): Partial<Record<RichTextCommand['command'], boolean>> {
	const selection = document.getSelection();

	if ( !selection || !selection.anchorNode || !editor.contains( selection.anchorNode ) ) {
		return {};
	}

	const pressed: Partial<Record<RichTextCommand['command'], boolean>> = {};

	for ( const { command } of getRichTextCommands() ) {
		pressed[command] = command === 'createLink'
			? Boolean( selection.anchorNode.parentElement?.closest( 'a' ) )
			: document.queryCommandState( command );
	}

	return pressed;
}

/**
 * Get the toolbar button index a key moves focus to (arrow keys, Home and
 * End), or undefined for other keys.
 */
export function getToolbarFocusIndex( key: string, current: number, count: number ): number | undefined {
	switch ( key ) {
		case 'ArrowRight':
			return ( current + 1 ) % count;
		case 'ArrowLeft':
			return ( current - 1 + count ) % count;
		case 'Home':
			return 0;
		case 'End':
			return count - 1;
		default:
			return undefined;
	}
}

/** Whether a textarea uses the rich text editor. */
export function usesRichText( field: FormField ): boolean {
	const value = field.field_config?.rich_text;

	return field.type === 'textarea' && ( value === true || value === 1 || value === '1' || value === 'true' );
}

/** Decode HTML entities, in the browser or (for tests) a small fallback. */
function decodeEntities( text: string ): string {
	if ( typeof document !== 'undefined' ) {
		const decoder = document.createElement( 'textarea' );

		decoder.innerHTML = text;

		return decoder.value;
	}

	return text
		.replace( /&nbsp;/g, ' ' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;/g, "'" )
		.replace( /&amp;/g, '&' );
}

/**
 * Convert rich text HTML to plain text: line breaks and the ends of
 * paragraphs, list items and quotes become new lines, tags are removed and
 * entities decoded. Mirrors `RichText::toPlainText()`.
 */
export function richTextToPlainText( html: string ): string {
	const stripped = html
		.replace( /<br\s*\/?>/gi, '\n' )
		.replace( /<\/(p|div|li|blockquote|h[1-6])\s*>/gi, '\n' )
		.replace( /<[^>]*>/g, '' );

	return decodeEntities( stripped )
		.replace( /\r\n?/g, '\n' )
		.replace( / /g, ' ' )
		.replace( /[ \t]+\n/g, '\n' )
		.replace( /\n{3,}/g, '\n\n' )
		.trim();
}

/**
 * Blank rich text that holds no text, such as the `<p><br></p>` an emptied
 * editor leaves, so required checks see it as empty.
 */
export function blankIfEmptyRichText( html: string ): string {
	return richTextToPlainText( html ) === '' ? '' : html;
}
