/**
 * CAPTCHA widget helpers.
 *
 * Framework-agnostic loading, rendering and token handling for the CAPTCHA
 * field's providers (Cloudflare Turnstile, hCaptcha and Google reCAPTCHA v2
 * and v3), shared by the React and Vue components. Mirrors the
 * `formsCaptcha` Alpine component of the Livewire renderer. The token is
 * checked by the server (`CaptchaVerifier`) when the form is submitted.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { CaptchaWidget, FormField } from '../types/artisanpack-forms';

/** How long to wait for a provider's script before giving up, in milliseconds. */
export const CAPTCHA_SCRIPT_TIMEOUT = 15000;

/**
 * How long an invisible challenge may stay open before the submission gives
 * up, in milliseconds, so a closed challenge cannot leave the form waiting.
 */
export const CAPTCHA_EXECUTE_TIMEOUT = 120000;

/** The provider scripts already requested, keyed by URL. */
const scripts = new Map<string, Promise<void>>();

/** The provider globals the widgets are driven through. */
interface CaptchaGlobals {
	turnstile?: { render: ( el: HTMLElement, options: Record<string, unknown> ) => string; reset: ( id?: string ) => void };
	hcaptcha?: { render: ( el: HTMLElement, options: Record<string, unknown> ) => string; execute: ( id?: string ) => void; reset: ( id?: string ) => void };
	grecaptcha?: {
		render?: ( el: HTMLElement, options: Record<string, unknown> ) => number;
		execute: ( idOrKey?: number | string, options?: { action: string } ) => Promise<string> | void;
		reset?: ( id?: number ) => void;
		ready?: ( callback: () => void ) => void;
	};
}

/** The window, with the provider globals. */
function globals(): CaptchaGlobals {
	return window as unknown as CaptchaGlobals;
}

/** Whether a CAPTCHA field has a provider set up and should render. */
export function hasCaptchaWidget( field: FormField ): field is FormField & { captcha: CaptchaWidget } {
	return field.type === 'captcha' && field.captcha !== null && field.captcha !== undefined;
}

/** Whether a widget fetches its token when the form is submitted rather than when the visitor solves it. */
export function runsOnSubmit( widget: CaptchaWidget ): boolean {
	return widget.size === 'invisible';
}

/** Whether a provider's global API has loaded. */
function isReady( provider: string ): boolean {
	const g = globals();

	if ( provider === 'turnstile' ) {
		return typeof g.turnstile?.render === 'function';
	}

	if ( provider === 'hcaptcha' ) {
		return typeof g.hcaptcha?.render === 'function';
	}

	if ( provider === 'recaptcha_v3' ) {
		return typeof g.grecaptcha?.ready === 'function';
	}

	return typeof g.grecaptcha?.render === 'function';
}

/**
 * Load a provider's script once per page. Resolves when its global API is
 * ready; rejects when the script fails or times out.
 */
export function loadCaptchaScript( widget: CaptchaWidget ): Promise<void> {
	const existing = scripts.get( widget.script );

	if ( existing ) {
		return existing;
	}

	const promise = new Promise<void>( ( resolve, reject ) => {
		if ( !document.querySelector( `script[src="${widget.script}"]` ) ) {
			const script = document.createElement( 'script' );
			script.src = widget.script;
			script.async = true;
			script.defer = true;
			// A failed tag is removed, so a later call adds a fresh one and retries.
			script.addEventListener( 'error', () => {
				script.remove();
				reject( new Error( 'CAPTCHA script failed to load' ) );
			}, { once: true } );
			document.head.appendChild( script );
		}

		const started = Date.now();
		const poll = (): void => {
			if ( isReady( widget.provider ) ) {
				resolve();
			} else if ( Date.now() - started > CAPTCHA_SCRIPT_TIMEOUT ) {
				reject( new Error( 'CAPTCHA script timed out' ) );
			} else {
				window.setTimeout( poll, 50 );
			}
		};

		poll();
	} );

	scripts.set( widget.script, promise );
	promise.catch( () => scripts.delete( widget.script ) );

	return promise;
}

/** Drives one rendered CAPTCHA widget. */
export interface CaptchaController {
	/** Load the provider and render the widget into the element (nothing renders for reCAPTCHA v3). */
	mount: ( element: HTMLElement ) => Promise<void>;
	/** Fetch a token now, for invisible widgets and reCAPTCHA v3. */
	execute: () => Promise<string>;
	/** Clear the token and ask the provider for a fresh challenge. */
	reset: () => void;
}

/**
 * Create the controller for a field's widget. `onToken` receives each new
 * token, and `''` when one expires or the widget is reset.
 */
export function createCaptchaController( widget: CaptchaWidget, onToken: ( token: string ) => void ): CaptchaController {
	let widgetId: string | number | null = null;
	let pending: { resolve: ( token: string ) => void; reject: ( error: Error ) => void } | null = null;
	let timer: number | undefined;

	const settlePending = (): typeof pending => {
		const current = pending;

		pending = null;
		window.clearTimeout( timer );

		return current;
	};

	const receive = ( token: string ): void => {
		onToken( token );

		if ( token ) {
			settlePending()?.resolve( token );
		}
	};

	const expire = (): void => {
		onToken( '' );
		settlePending()?.reject( new Error( 'CAPTCHA check failed' ) );
	};

	const close = (): void => {
		settlePending()?.reject( new Error( 'CAPTCHA challenge closed' ) );
	};

	return {
		async mount( element: HTMLElement ): Promise<void> {
			await loadCaptchaScript( widget );

			const g = globals();
			const options = {
				sitekey: widget.site_key,
				size: widget.size,
				callback: receive,
				'expired-callback': expire,
				'error-callback': expire,
			};

			if ( widget.provider === 'turnstile' && g.turnstile ) {
				widgetId = g.turnstile.render( element, { ...options, theme: widget.theme } );
			} else if ( widget.provider === 'hcaptcha' && g.hcaptcha ) {
				widgetId = g.hcaptcha.render( element, { ...options, 'close-callback': close, theme: widget.theme === 'dark' ? 'dark' : 'light' } );
			} else if ( widget.provider === 'recaptcha_v2' && g.grecaptcha?.render ) {
				widgetId = g.grecaptcha.render( element, { ...options, theme: widget.theme === 'dark' ? 'dark' : 'light' } );
			}
		},

		execute(): Promise<string> {
			const g = globals();

			if ( widget.provider === 'recaptcha_v3' ) {
				return new Promise<string>( ( resolve, reject ) => {
					if ( !g.grecaptcha?.ready ) {
						reject( new Error( 'CAPTCHA not loaded' ) );

						return;
					}

					g.grecaptcha.ready( () => {
						Promise.resolve( g.grecaptcha?.execute( widget.site_key, { action: widget.action ?? 'forms_submit' } ) )
							.then( ( token ) => {
								receive( token ?? '' );
								resolve( token ?? '' );
							} )
							.catch( reject );
					} );
				} );
			}

			return new Promise<string>( ( resolve, reject ) => {
				settlePending()?.reject( new Error( 'CAPTCHA check restarted' ) );
				pending = { resolve, reject };
				timer = window.setTimeout( close, CAPTCHA_EXECUTE_TIMEOUT );

				if ( widget.provider === 'hcaptcha' && g.hcaptcha ) {
					g.hcaptcha.execute( widgetId as string );
				} else if ( widget.provider === 'recaptcha_v2' && g.grecaptcha ) {
					g.grecaptcha.execute( widgetId as number );
				} else {
					settlePending();
					reject( new Error( 'This CAPTCHA cannot be run on submit' ) );
				}
			} );
		},

		reset(): void {
			onToken( '' );
			settlePending()?.reject( new Error( 'CAPTCHA reset' ) );

			if ( widgetId === null ) {
				return;
			}

			const g = globals();

			if ( widget.provider === 'turnstile' ) {
				g.turnstile?.reset( widgetId as string );
			} else if ( widget.provider === 'hcaptcha' ) {
				g.hcaptcha?.reset( widgetId as string );
			} else if ( widget.provider === 'recaptcha_v2' ) {
				g.grecaptcha?.reset?.( widgetId as number );
			}
		},
	};
}

/** What a field contributes to a submission and learns about its outcome. */
export interface SubmitHook {
	/** Called before the data is sent; the values it returns are submitted under `data[...]`. */
	beforeSubmit?: () => Promise<Record<string, unknown>>;
	/** Called after the server answers: `ok` on success, otherwise the errors by field name. */
	afterSubmit?: ( result: { ok: boolean; errors: Record<string, string[]> } ) => void;
}

/** Hooks fields register with their form, keyed by field name. */
export interface SubmitHooks {
	/** Register a field's hook; returns the function that removes it. */
	register: ( name: string, hook: SubmitHook ) => () => void;
	/** Run every `beforeSubmit` and merge what they return. Rejects with the name of the field that failed. */
	collect: () => Promise<Record<string, unknown>>;
	/** Tell every hook how the submission went. */
	settle: ( result: { ok: boolean; errors: Record<string, string[]> } ) => void;
}

/** An error a `beforeSubmit` hook failed with, naming its field. */
export class SubmitHookError extends Error {
	constructor( public readonly field: string ) {
		super( `Submit hook for "${field}" failed` );
	}
}

/** Create a form's submit hook registry. */
export function createSubmitHooks(): SubmitHooks {
	const hooks = new Map<string, SubmitHook>();

	return {
		register( name: string, hook: SubmitHook ): () => void {
			hooks.set( name, hook );

			return () => {
				if ( hooks.get( name ) === hook ) {
					hooks.delete( name );
				}
			};
		},

		async collect(): Promise<Record<string, unknown>> {
			const data: Record<string, unknown> = {};

			for ( const [name, hook] of hooks ) {
				if ( !hook.beforeSubmit ) {
					continue;
				}

				try {
					Object.assign( data, await hook.beforeSubmit() );
				} catch {
					throw new SubmitHookError( name );
				}
			}

			return data;
		},

		settle( result: { ok: boolean; errors: Record<string, string[]> } ): void {
			for ( const hook of hooks.values() ) {
				hook.afterSubmit?.( result );
			}
		},
	};
}
