/**
 * Signature field helpers.
 *
 * Client-side mirror of `SignatureFieldType`. A drawn signature is submitted
 * as a PNG data URL and a typed one as the visitor's full name. The server
 * sends the pad height and the largest PNG it accepts as `field.signature`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';
import { t } from './i18n';

/** The prefix of a drawn signature's data URL, mirroring `SignatureFieldType::DATA_URL_PREFIX`. */
export const SIGNATURE_DATA_URL_PREFIX = 'data:image/png;base64,';

/** The longest typed signature, mirroring `SignatureFieldType::TYPED_MAX_LENGTH`. */
export const SIGNATURE_TYPED_MAX_LENGTH = 255;

/** The largest drawn PNG in bytes, mirroring `SignatureFieldType::MAX_BYTES`. */
export const SIGNATURE_MAX_BYTES = 512 * 1024;

/** The pad height in pixels when the field sets none. */
export const SIGNATURE_DEFAULT_PAD_HEIGHT = 160;

/** How a signature was given. */
export type SignatureMode = 'draw' | 'type';

/** Whether a field is a signature. */
export function isSignatureField( field: FormField ): boolean {
	return field.type === 'signature';
}

/** Whether a value is a drawn signature's PNG data URL. */
export function isDrawnSignature( value: unknown ): value is string {
	return typeof value === 'string' && value.startsWith( SIGNATURE_DATA_URL_PREFIX );
}

/** Get the drawing pad's height in pixels. */
export function getSignaturePadHeight( field: FormField ): number {
	return field.signature?.pad_height ?? SIGNATURE_DEFAULT_PAD_HEIGHT;
}

/** Get the method a value was given with: `type` for a typed name, else `draw`. */
export function getSignatureMode( value: unknown ): SignatureMode {
	return typeof value === 'string' && value !== '' && !isDrawnSignature( value ) ? 'type' : 'draw';
}

/**
 * Validate a signature, mirroring `SignatureFieldType::isValidSignature()`:
 * a PNG data URL no larger than the server accepts, or a typed name of at
 * most 255 characters.
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateSignature( field: FormField, value: unknown ): string[] {
	const invalid = [t( 'Please draw your signature for :field, or type your full name (up to :max characters).', { field: field.label ?? field.name, max: SIGNATURE_TYPED_MAX_LENGTH } )];

	if ( typeof value !== 'string' ) {
		return invalid;
	}

	if ( value.startsWith( 'data:' ) ) {
		const maxBytes = field.signature?.max_bytes ?? SIGNATURE_MAX_BYTES;
		const encoded = value.slice( SIGNATURE_DATA_URL_PREFIX.length );

		return isDrawnSignature( value ) && encoded.length <= Math.ceil( maxBytes / 3 ) * 4 ? [] : invalid;
	}

	return value.trim() !== '' && Array.from( value ).length <= SIGNATURE_TYPED_MAX_LENGTH && !/[\x00-\x1F\x7F]/.test( value ) ? [] : invalid;
}

/**
 * Draws on a signature pad canvas with pointer events and reads it back as a
 * PNG data URL. Shared by the React and Vue signature fields; the Livewire
 * partial has the same behaviour in Alpine.
 *
 * The canvas keeps its CSS size; its backing store is scaled to the device
 * pixel ratio (at most 2) for crisp lines, and resized when its width changes,
 * redrawing the current drawing.
 */
export class SignaturePad {
	private readonly canvas: HTMLCanvasElement;

	private readonly height: number;

	private width = 0;

	private active = false;

	private hasInk = false;

	private observer: ResizeObserver | null = null;

	/**
	 * @param canvas  The pad's canvas.
	 * @param height  The pad's height in CSS pixels.
	 * @param onEnd   Called when a stroke ends.
	 */
	constructor( canvas: HTMLCanvasElement, height: number, private readonly onEnd: () => void ) {
		this.canvas = canvas;
		this.height = height;
		this.start = this.start.bind( this );
		this.move = this.move.bind( this );
		this.end = this.end.bind( this );

		canvas.addEventListener( 'pointerdown', this.start );
		canvas.addEventListener( 'pointermove', this.move );
		canvas.addEventListener( 'pointerup', this.end );
		canvas.addEventListener( 'pointercancel', this.end );
		canvas.addEventListener( 'pointerleave', this.end );

		if ( typeof ResizeObserver !== 'undefined' ) {
			this.observer = new ResizeObserver( () => this.resize() );
			this.observer.observe( canvas );
		}

		this.resize();
	}

	/** Whether anything has been drawn since the pad was cleared. */
	isEmpty(): boolean {
		return !this.hasInk;
	}

	/** Get the drawing as a PNG data URL. */
	toDataUrl(): string {
		return this.canvas.toDataURL( 'image/png' );
	}

	/** Draw a saved PNG data URL onto the pad. */
	fromDataUrl( src: string ): void {
		if ( !isDrawnSignature( src ) ) {
			return;
		}

		const image = new Image();

		image.onload = () => {
			this.context().drawImage( image, 0, 0, this.canvas.offsetWidth, this.height );
			this.hasInk = true;
		};
		image.src = src;
	}

	/** Clear the pad. */
	clear(): void {
		this.context().clearRect( 0, 0, this.canvas.width, this.canvas.height );
		this.hasInk = false;
	}

	/** Remove the pad's listeners. */
	destroy(): void {
		this.canvas.removeEventListener( 'pointerdown', this.start );
		this.canvas.removeEventListener( 'pointermove', this.move );
		this.canvas.removeEventListener( 'pointerup', this.end );
		this.canvas.removeEventListener( 'pointercancel', this.end );
		this.canvas.removeEventListener( 'pointerleave', this.end );
		this.observer?.disconnect();
	}

	private context(): CanvasRenderingContext2D {
		return this.canvas.getContext( '2d' ) as CanvasRenderingContext2D;
	}

	private resize(): void {
		const width = this.canvas.offsetWidth;

		if ( !width || width === this.width ) {
			return;
		}

		const previous = this.hasInk ? this.toDataUrl() : null;
		const ratio = Math.min( window.devicePixelRatio || 1, 2 );
		const context = this.context();

		this.width = width;
		this.canvas.width = width * ratio;
		this.canvas.height = this.height * ratio;
		context.setTransform( ratio, 0, 0, ratio, 0, 0 );
		context.lineWidth = 2.5;
		context.lineCap = 'round';
		context.lineJoin = 'round';
		context.strokeStyle = '#111827';

		if ( previous ) {
			this.fromDataUrl( previous );
		}
	}

	private point( event: PointerEvent ): { x: number; y: number } {
		const rect = this.canvas.getBoundingClientRect();

		return { x: event.clientX - rect.left, y: event.clientY - rect.top };
	}

	private start( event: PointerEvent ): void {
		event.preventDefault();
		this.resize();
		this.active = true;
		this.canvas.setPointerCapture?.( event.pointerId );

		const { x, y } = this.point( event );
		const context = this.context();

		context.beginPath();
		context.moveTo( x, y );
		context.lineTo( x + 0.1, y + 0.1 );
		context.stroke();
	}

	private move( event: PointerEvent ): void {
		if ( !this.active ) {
			return;
		}

		const { x, y } = this.point( event );
		const context = this.context();

		context.lineTo( x, y );
		context.stroke();
	}

	private end(): void {
		if ( !this.active ) {
			return;
		}

		this.active = false;
		this.hasInk = true;
		this.onEnd();
	}
}
