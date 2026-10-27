/**
 * The notes shown under a text-like input (since 1.6.0).
 *
 * Mirrors the Livewire `input-extras` and `counter` partials: the input
 * mask's expected format, the number field's prefix and suffix for screen
 * readers, and a live character or word counter. Each note's ID is in the
 * input's `aria-describedby` (see `getInputNoteIds()`). The counter updates
 * as the visitor types; a polite live region repeats it once typing pauses,
 * so screen readers are not interrupted on every keystroke.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useRef, useState } from 'react';

import type { FormField } from '../../../types/artisanpack-forms';
import { COUNTER_ANNOUNCE_DELAY, getCounterState, showsCounter } from '../../../shared/counter';
import { getInputMaskHint } from '../../../shared/mask';
import { fieldIdKey } from '../../../shared/domIds';

interface InputNotesProps {
	field: FormField;
	/** The input's current value, for the counter. */
	value: string;
}

/**
 * Renders a field's input notes.
 */
export function InputNotes( { field, value }: InputNotesProps ) {
	const maskHint = getInputMaskHint( field );

	return (
		<>
			{maskHint && (
				<p id={`field-${fieldIdKey( field )}-mask`} className="fieldset-label mt-1">{maskHint}</p>
			)}
			{field.affix_description && (
				<span id={`field-${fieldIdKey( field )}-affix`} className="sr-only">{field.affix_description}</span>
			)}
			{showsCounter( field ) && <InputCounter field={field} value={value} />}
		</>
	);
}

/**
 * Renders a live character or word counter.
 */
function InputCounter( { field, value }: InputNotesProps ) {
	const counter = getCounterState( field, value );
	const [announcement, setAnnouncement] = useState( '' );
	const typed = useRef( false );

	// Announce changes only, not the count the field starts with.
	useEffect( () => {
		if ( !typed.current ) {
			typed.current = true;

			return;
		}

		const timer = setTimeout( () => setAnnouncement( counter.text ), COUNTER_ANNOUNCE_DELAY );

		return () => clearTimeout( timer );
	}, [counter.text] );

	return (
		<>
			<p
				id={`field-${fieldIdKey( field )}-counter`}
				className={`fieldset-label mt-1 justify-end ${counter.overLimit ? 'text-error' : ''}`}
			>
				{counter.text}
			</p>
			<span className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</span>
		</>
	);
}
