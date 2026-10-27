/**
 * Rich text editor for textarea fields.
 *
 * A labelled `role="toolbar"` of formatting buttons above a `contenteditable`
 * `role="textbox"`, mirroring the Livewire `rich-text` partial. One toolbar
 * button is in the tab order at a time; the arrow keys, Home and End move
 * between them, and each toggle reports its state with `aria-pressed`. The
 * field submits the editor's HTML, which the server sanitizes with `kses()`
 * when it is stored and shown.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useRef, useState } from 'react';

import { getTextareaRows, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import {
	getPressedCommands,
	getRichTextCommands,
	getToolbarFocusIndex,
	runRichTextCommand,
} from '../../../shared/richText';
import type { RichTextCommand } from '../../../shared/richText';
import type { FormField } from '../../../types/artisanpack-forms';
import { fieldIdKey } from '../../../shared/domIds';

/** Props for the RichTextEditor component. */
export interface RichTextEditorProps {
	field: FormField;
	value: string;
	error?: string;
	/** The ID of the element that labels the editor. */
	labelId?: string;
	/** The IDs of the hint, error and notes that describe the editor. */
	describedBy?: string;
	onChange: ( html: string ) => void;
}

/**
 * Renders the rich text editor.
 */
export function RichTextEditor( { field, value, error, labelId, describedBy, onChange }: RichTextEditorProps ) {
	const editorRef = useRef<HTMLDivElement>( null );
	const toolbarRef = useRef<HTMLDivElement>( null );
	const [current, setCurrent] = useState( 0 );
	const [pressed, setPressed] = useState<Partial<Record<RichTextCommand['command'], boolean>>>( {} );
	const commands = getRichTextCommands();
	const readOnly = isReadOnlyField( field );
	const editorId = `field-${fieldIdKey( field )}`;
	const fieldName = field.label ?? field.name;

	// Follow value changes from outside the editor (a reset or prefill) without moving the caret while typing.
	useEffect( () => {
		const editor = editorRef.current;

		if ( editor && editor.innerHTML !== value ) {
			editor.innerHTML = value;
		}
	}, [value] );

	useEffect( () => {
		document.execCommand( 'defaultParagraphSeparator', false, 'p' );

		const refresh = (): void => {
			if ( editorRef.current ) {
				const next = getPressedCommands( editorRef.current );

				if ( Object.keys( next ).length > 0 ) {
					setPressed( next );
				}
			}
		};

		document.addEventListener( 'selectionchange', refresh );

		return () => document.removeEventListener( 'selectionchange', refresh );
	}, [] );

	const sync = (): void => {
		if ( editorRef.current ) {
			onChange( editorRef.current.innerHTML );
		}
	};

	const run = ( command: RichTextCommand['command'] ): void => {
		if ( !editorRef.current ) {
			return;
		}

		runRichTextCommand( editorRef.current, command );
		sync();
		setPressed( getPressedCommands( editorRef.current ) );
	};

	const moveFocus = ( key: string ): boolean => {
		const next = getToolbarFocusIndex( key, current, commands.length );

		if ( next === undefined ) {
			return false;
		}

		setCurrent( next );
		toolbarRef.current?.querySelectorAll( 'button' )[next]?.focus();

		return true;
	};

	return (
		<div className="flex flex-col">
			<div
				ref={toolbarRef}
				role="toolbar"
				className="flex flex-wrap gap-1 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-1"
				aria-label={t( 'Formatting for :field', { field: fieldName } )}
				aria-controls={editorId}
				onKeyDown={( e ) => {
					if ( moveFocus( e.key ) ) {
						e.preventDefault();
					}
				}}
			>
				{commands.map( ( command, index ) => (
					<button
						key={command.command}
						type="button"
						className={`btn btn-ghost btn-sm min-w-8 ${command.className}`}
						aria-label={command.label}
						aria-pressed={pressed[command.command] ? 'true' : 'false'}
						tabIndex={current === index ? 0 : -1}
						disabled={readOnly}
						onFocus={() => setCurrent( index )}
						onMouseDown={( e ) => e.preventDefault()}
						onClick={() => run( command.command )}
					>
						<span aria-hidden="true">{command.text}</span>
					</button>
				) )}
			</div>
			<div
				ref={editorRef}
				id={editorId}
				role="textbox"
				aria-multiline="true"
				aria-labelledby={labelId}
				aria-label={labelId ? undefined : fieldName}
				aria-describedby={describedBy}
				aria-required={field.is_required ? 'true' : undefined}
				aria-readonly={readOnly ? 'true' : undefined}
				aria-invalid={error ? true : undefined}
				data-placeholder={field.placeholder ?? undefined}
				contentEditable={!readOnly}
				suppressContentEditableWarning
				className={`prose prose-sm max-w-none rounded-b-box border bg-base-100 px-4 py-2 focus:outline-2 focus:outline-offset-2 focus:outline-primary empty:before:text-base-content/50 empty:before:content-[attr(data-placeholder)] ${error ? 'border-error' : 'border-base-300'} ${field.css_classes ?? ''}`}
				style={{ minHeight: `${getTextareaRows( field ) * 1.5}rem` }}
				onInput={sync}
				onBlur={sync}
			/>
		</div>
	);
}
