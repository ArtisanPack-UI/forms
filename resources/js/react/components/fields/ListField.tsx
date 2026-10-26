/**
 * List (repeater) field component.
 *
 * Renders rows of the field's columns that visitors add and remove, in a
 * `<fieldset>` whose `<legend>` is the field label (since 1.6.0; see
 * `shared/list.ts`). Each row is a group named for its row ("Attendee 2"),
 * with one labelled input per column; the column labels show as a header row
 * from the `sm` breakpoint up, where the inputs' own labels are kept for
 * screen readers only. After a row is added its first input takes focus,
 * after one is removed the add button does, and a polite live region
 * announces each change. Mirrors the Blade `list` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useRef, useState } from 'react';

import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import {
	getEmptyListRow,
	getInitialListRows,
	getListColumns,
	getListMaxRows,
	getListRowLabel,
	LIST_CELL_MAX_LENGTH,
	toListRows,
} from '../../../shared/list';
import type { ListRow } from '../../../shared/list';

import type { FieldComponentProps } from './types';

/**
 * Renders a list field.
 */
export function ListField( { field, value, error, subErrors = {}, onChange, displayConfig }: FieldComponentProps ) {
	const columns = getListColumns( field );
	const stored = toListRows( field, value );
	const rows = stored.length > 0 ? stored : getInitialListRows( field );
	const maxRows = getListMaxRows( field );
	const rowLabel = getListRowLabel( field );
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const cellErrorCount = Object.keys( subErrors ).length;
	const hasErrors = Boolean( error ) || cellErrorCount > 0;
	const hintId = field.help_text ? `${field.name}-hint` : undefined;
	const errorId = hasErrors ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const gridStyle = { gridTemplateColumns: `repeat(${Math.max( 1, columns.length )}, minmax(0, 1fr)) auto` };
	const hint = hintId ? <p id={hintId} className="fieldset-label">{field.help_text}</p> : null;
	const summary = error || ( cellErrorCount === 1
		? t( 'Please fix :count highlighted entry in :field.', { count: cellErrorCount, field: field.label ?? field.name } )
		: t( 'Please fix :count highlighted entries in :field.', { count: cellErrorCount, field: field.label ?? field.name } ) );

	const [announcement, setAnnouncement] = useState( '' );
	const [focusTarget, setFocusTarget] = useState<{ row: number } | 'add' | null>( null );
	const addButton = useRef<HTMLButtonElement>( null );

	// Move focus once the rows have re-rendered.
	useEffect( () => {
		if ( focusTarget === 'add' ) {
			addButton.current?.focus();
		} else if ( focusTarget && columns[0] ) {
			document.getElementById( `field-${field.name}-${focusTarget.row}-${columns[0].key}` )?.focus();
		}

		if ( focusTarget ) {
			setFocusTarget( null );
		}
	}, [focusTarget, columns, field.name] );

	const update = ( next: ListRow[] ) => onChange( next );

	const changeCell = ( index: number, key: string, text: string ) => {
		update( rows.map( ( row, position ) => ( position === index ? { ...row, [key]: text } : row ) ) );
	};

	const addRow = () => {
		if ( rows.length >= maxRows ) {
			return;
		}

		update( [...rows, getEmptyListRow( field )] );
		setAnnouncement( t( ':row :number added.', { row: rowLabel, number: rows.length + 1 } ) );
		setFocusTarget( { row: rows.length } );
	};

	const removeRow = ( index: number ) => {
		const next = rows.filter( ( _row, position ) => position !== index );

		update( next.length > 0 ? next : [getEmptyListRow( field )] );
		setAnnouncement( t( ':row :number removed.', { row: rowLabel, number: index + 1 } ) );
		setFocusTarget( 'add' );
	};

	return (
		<fieldset
			id={`field-${field.name}`}
			className={`fieldset ${field.css_classes ?? ''}`}
			data-forms-list={field.name}
			aria-describedby={describedBy}
		>
			{field.label && (
				<legend className={`fieldset-legend ${hiddenLabel ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
				</legend>
			)}

			{layout.description === 'above' && hint}

			<div className="hidden gap-2 sm:grid" style={gridStyle} aria-hidden="true">
				{columns.map( ( column ) => <span key={column.key} className="label text-sm">{column.label}</span> )}
				<span className="w-8" />
			</div>

			<div className="flex flex-col gap-3 sm:gap-2">
				{rows.map( ( row, index ) => {
					const rowId = `field-${field.name}-row-${index}`;

					return (
						<div
							key={index}
							role="group"
							aria-labelledby={`${rowId}-label`}
							className="flex flex-col gap-2 rounded-box bg-base-200 p-3 sm:grid sm:items-start sm:bg-transparent sm:p-0"
							style={gridStyle}
						>
							<span id={`${rowId}-label`} className="sr-only">{`${rowLabel} ${index + 1}`}</span>

							{columns.map( ( column ) => {
								const inputId = `field-${field.name}-${index}-${column.key}`;
								const cellError = subErrors[`${index}.${column.key}`];
								const shared = {
									id: inputId,
									value: row[column.key] ?? '',
									'aria-invalid': cellError ? true : undefined,
									'aria-describedby': cellError ? `${inputId}-error` : undefined,
								};

								return (
									<div key={column.key} className="flex flex-col gap-1">
										<label htmlFor={inputId} className="label text-sm sm:sr-only">{column.label}</label>
										{column.type === 'select' ? (
											<select
												{...shared}
												className={`select select-bordered w-full ${cellError ? 'select-error' : ''}`}
												onChange={( e ) => changeCell( index, column.key, e.target.value )}
											>
												<option value="">{t( 'Select…' )}</option>
												{column.options.map( ( option ) => (
													<option key={option.value} value={option.value}>{option.label}</option>
												) )}
											</select>
										) : (
											<input
												{...shared}
												type={column.type}
												className={`input input-bordered w-full ${cellError ? 'input-error' : ''}`}
												maxLength={column.type === 'number' ? undefined : LIST_CELL_MAX_LENGTH}
												onChange={( e ) => changeCell( index, column.key, e.target.value )}
											/>
										)}
										{cellError && <p id={`${inputId}-error`} className="text-xs text-error">{cellError}</p>}
									</div>
								);
							} )}

							<button
								type="button"
								className="btn btn-ghost btn-sm btn-square self-end sm:self-start"
								aria-label={t( 'Remove :row :number', { row: rowLabel, number: index + 1 } )}
								onClick={() => removeRow( index )}
							>
								<span aria-hidden="true">✕</span>
							</button>
						</div>
					);
				} )}
			</div>

			<div className="mt-2 flex items-center gap-3">
				<button
					ref={addButton}
					type="button"
					className="btn btn-sm btn-outline"
					disabled={rows.length >= maxRows}
					onClick={addRow}
				>
					<span aria-hidden="true">+</span> {t( 'Add :row', { row: rowLabel } )}
				</button>
				{rows.length >= maxRows && (
					<span className="text-xs text-base-content/60">
						{maxRows === 1 ? t( 'Up to :count row.', { count: maxRows } ) : t( 'Up to :count rows.', { count: maxRows } )}
					</span>
				)}
			</div>

			{layout.description !== 'above' && hint}

			{hasErrors && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{summary}</p>
			)}

			<div className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</div>
		</fieldset>
	);
}
