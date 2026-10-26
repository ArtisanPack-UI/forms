/**
 * Signature field component.
 *
 * A canvas pad to draw on with a mouse, finger or stylus, with a Clear
 * button, or a text input to type a full name instead for visitors who
 * can't draw (since 1.6.0; see `shared/signature.ts`). "Draw" and "Type your
 * name" are toggle buttons, and switching clears the other method. A drawing
 * is sent as a PNG data URL once a stroke ends. The pad is an image to
 * screen readers. Mirrors the Blade `signature` partial.
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
	getSignatureMode,
	getSignaturePadHeight,
	isDrawnSignature,
	SIGNATURE_TYPED_MAX_LENGTH,
	SignaturePad,
} from '../../../shared/signature';
import type { SignatureMode } from '../../../shared/signature';

import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/**
 * Renders a signature field.
 */
export function SignatureField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const padHeight = getSignaturePadHeight( field );
	const layout = getFieldLayout( field, displayConfig );
	const labelId = field.label ? ( layout.custom ? layout.labelId : `${field.name}-label` ) : undefined;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text && !error ? `${field.name}-hint` : undefined );
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const idPrefix = `field-${field.name}-`;

	const [mode, setMode] = useState<SignatureMode>( () => getSignatureMode( value ) );
	const [typed, setTyped] = useState( () => ( getSignatureMode( value ) === 'type' ? String( value ) : '' ) );
	const [hasInk, setHasInk] = useState( () => isDrawnSignature( value ) );
	const [announcement, setAnnouncement] = useState( '' );
	const canvas = useRef<HTMLCanvasElement>( null );
	const typedInput = useRef<HTMLInputElement>( null );
	const pad = useRef<SignaturePad | null>( null );
	const onChangeRef = useRef( onChange );

	onChangeRef.current = onChange;

	// Set the pad up once, redrawing a drawing already in the form data.
	useEffect( () => {
		if ( !canvas.current ) {
			return undefined;
		}

		const signaturePad = new SignaturePad( canvas.current, padHeight, () => {
			setHasInk( true );
			onChangeRef.current( signaturePad.toDataUrl() );
		} );

		if ( isDrawnSignature( value ) ) {
			signaturePad.fromDataUrl( value );
		}

		pad.current = signaturePad;

		return () => signaturePad.destroy();
		// The pad keeps its own drawing after setup, so it is not rebuilt when the value changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [padHeight] );

	const clear = () => {
		pad.current?.clear();
		setHasInk( false );
		setAnnouncement( t( 'Signature cleared.' ) );
		onChange( '' );
	};

	const choose = ( next: SignatureMode ) => {
		if ( next === mode ) {
			return;
		}

		setMode( next );
		setAnnouncement( '' );
		pad.current?.clear();
		setHasInk( false );

		if ( next === 'type' ) {
			onChange( typed.trim() );
			requestAnimationFrame( () => typedInput.current?.focus() );
		} else {
			setTyped( '' );
			onChange( '' );
		}
	};

	return (
		<FieldLayout field={field} layout={layout} group>
			<div className="fieldset">
				{!layout.custom && field.label && (
					<span id={labelId} className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</span>
				)}

				<div
					role="group"
					className={`flex flex-col gap-2 ${field.css_classes ?? ''}`}
					aria-labelledby={labelId}
					aria-label={labelId ? undefined : field.name}
					aria-describedby={describedBy}
				>
					<div className="join" role="group" aria-label={t( 'Signature method' )}>
						<button
							type="button"
							className={`btn btn-sm join-item ${mode === 'draw' ? 'btn-primary' : ''}`}
							aria-pressed={mode === 'draw'}
							onClick={() => choose( 'draw' )}
						>
							{t( 'Draw' )}
						</button>
						<button
							type="button"
							className={`btn btn-sm join-item ${mode === 'type' ? 'btn-primary' : ''}`}
							aria-pressed={mode === 'type'}
							onClick={() => choose( 'type' )}
						>
							{t( 'Type your name' )}
						</button>
					</div>

					<div hidden={mode !== 'draw'}>
						<div className="relative rounded-box border border-base-300 bg-white">
							<canvas
								ref={canvas}
								id={`${idPrefix}pad`}
								className="block w-full cursor-crosshair touch-none select-none rounded-box"
								style={{ height: `${padHeight}px` }}
								role="img"
								aria-label={t( 'Signature pad. Draw your signature with a mouse, finger or stylus, or choose Type your name instead.' )}
							/>
							<span className="pointer-events-none absolute inset-x-6 bottom-6 border-b border-dashed border-gray-300" aria-hidden="true" />
						</div>
						<div className="mt-1 flex items-center justify-between gap-2">
							<span className="text-xs text-base-content/60">{t( 'Sign above the line.' )}</span>
							<button type="button" className="btn btn-ghost btn-xs" disabled={!hasInk} onClick={clear}>
								{t( 'Clear signature' )}
							</button>
						</div>
					</div>

					<div hidden={mode !== 'type'} className="flex flex-col gap-2">
						<label htmlFor={`${idPrefix}typed`} className="label text-sm">{t( 'Type your full name' )}</label>
						<input
							ref={typedInput}
							type="text"
							id={`${idPrefix}typed`}
							className="input input-bordered w-full"
							value={typed}
							maxLength={SIGNATURE_TYPED_MAX_LENGTH}
							autoComplete="name"
							aria-invalid={error ? true : undefined}
							aria-describedby={describedBy}
							onChange={( e ) => {
								setTyped( e.target.value );
								onChange( e.target.value.trim() );
							}}
						/>
						<p className="min-h-10 border-b border-base-300 px-2 font-serif text-2xl italic" aria-hidden="true">{typed}</p>
					</div>

					<div className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</div>
				</div>

				{!layout.custom && hintId && (
					<p id={hintId} className="fieldset-label">{field.help_text}</p>
				)}
				{error && (
					<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
				)}
			</div>
		</FieldLayout>
	);
}
