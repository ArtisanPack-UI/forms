/**
 * Ranking field component.
 *
 * Renders the field's choices as a numbered list that visitors put in order
 * of preference, in a `<fieldset>` whose `<legend>` is the field label
 * (since 1.6.0; see `shared/ranking.ts`). Each choice has "Move up" and
 * "Move down" buttons and can be dragged with a mouse. After a move, focus
 * stays on the moved choice's button and a polite live region announces its
 * new position. Mirrors the Blade `ranking` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useState } from 'react';

import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { getRankingChoices, getRankingOrder, moveRankingItem } from '../../../shared/ranking';

import type { FieldComponentProps } from './types';

/** Where focus goes after a move: the moved choice's button in that direction. */
interface FocusTarget {
	position: number;
	direction: 'up' | 'down';
}

/**
 * Renders a ranking field.
 */
export function RankingField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const labels = Object.fromEntries( getRankingChoices( field ).map( ( choice ) => [choice.value, choice.label] ) );
	const order = getRankingOrder( field, value );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const idPrefix = `field-${field.name}-`;
	const instructionsId = readOnly ? undefined : `${idPrefix}instructions`;
	const hintId = field.help_text ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [instructionsId, hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const hint = hintId ? <p id={hintId} className="fieldset-label">{field.help_text}</p> : null;

	const [announcement, setAnnouncement] = useState( '' );
	const [dragging, setDragging] = useState<number | null>( null );
	const [focusTarget, setFocusTarget] = useState<FocusTarget | null>( null );

	// Keep focus on the moved choice once the list has re-rendered.
	useEffect( () => {
		if ( !focusTarget ) {
			return;
		}

		const other = focusTarget.direction === 'up' ? 'down' : 'up';
		let button = document.getElementById( `${idPrefix}${focusTarget.position}-${focusTarget.direction}` ) as HTMLButtonElement | null;

		if ( !button || button.disabled ) {
			button = document.getElementById( `${idPrefix}${focusTarget.position}-${other}` ) as HTMLButtonElement | null;
		}

		button?.focus();
		setFocusTarget( null );
	}, [focusTarget, idPrefix] );

	const move = ( from: number, to: number, keepFocus: boolean ) => {
		const next = moveRankingItem( order, from, to );

		if ( next === order ) {
			return;
		}

		onChange( next );
		setAnnouncement( t( ':choice moved to position :position of :total.', {
			choice: labels[order[from]] ?? order[from],
			position: to + 1,
			total: order.length,
		} ) );

		if ( keepFocus ) {
			setFocusTarget( { position: to, direction: to < from ? 'up' : 'down' } );
		}
	};

	return (
		<fieldset
			id={`field-${field.name}`}
			className={`fieldset ${field.css_classes ?? ''}`}
			data-forms-ranking={field.name}
			aria-describedby={describedBy}
		>
			{field.label && (
				<legend className={`fieldset-legend ${hiddenLabel ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
				</legend>
			)}

			{layout.description === 'above' && hint}

			{instructionsId && (
				<p id={instructionsId} className="fieldset-label">
					{t( 'Put the choices in order, most preferred first: drag them, or use the Move up and Move down buttons.' )}
				</p>
			)}

			<ol className="flex flex-col gap-2">
				{order.map( ( choice, index ) => {
					const label = labels[choice] ?? choice;

					return (
						<li
							key={choice}
							className={`flex items-center gap-3 rounded-box border border-base-300 bg-base-100 p-2 ${dragging === index ? 'opacity-50' : ''}`}
							draggable={!readOnly}
							onDragStart={( e ) => {
								setDragging( index );
								e.dataTransfer.effectAllowed = 'move';
							}}
							onDragEnd={() => setDragging( null )}
							onDragOver={( e ) => {
								if ( dragging !== null ) {
									e.preventDefault();
									e.dataTransfer.dropEffect = 'move';
								}
							}}
							onDrop={( e ) => {
								e.preventDefault();

								if ( dragging !== null ) {
									move( dragging, index, false );
								}

								setDragging( null );
							}}
						>
							{!readOnly && <span className="cursor-grab text-base-content/50" aria-hidden="true">⠿</span>}
							<span className="badge badge-neutral tabular-nums" aria-hidden="true">{index + 1}</span>
							<span className="flex-1">{label}</span>
							{!readOnly && (
								<>
									<button
										type="button"
										id={`${idPrefix}${index}-up`}
										className="btn btn-ghost btn-sm btn-square"
										aria-label={t( 'Move :choice up', { choice: label } )}
										disabled={index === 0}
										onClick={() => move( index, index - 1, true )}
									>
										<span aria-hidden="true">↑</span>
									</button>
									<button
										type="button"
										id={`${idPrefix}${index}-down`}
										className="btn btn-ghost btn-sm btn-square"
										aria-label={t( 'Move :choice down', { choice: label } )}
										disabled={index === order.length - 1}
										onClick={() => move( index, index + 1, true )}
									>
										<span aria-hidden="true">↓</span>
									</button>
								</>
							)}
						</li>
					);
				} )}
			</ol>

			{layout.description !== 'above' && hint}

			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}

			<div className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</div>
		</fieldset>
	);
}
