/**
 * CAPTCHA field component.
 *
 * Renders a CAPTCHA provider's challenge (Cloudflare Turnstile, hCaptcha or
 * Google reCAPTCHA) through the shared CAPTCHA controller, which loads the
 * provider's script once per page and sends each token to the field value.
 * Invisible widgets and reCAPTCHA v3 fetch their token when the form is
 * submitted, through the form's submit hooks; a rejected submission resets
 * the widget for a fresh challenge. Nothing renders while the provider has
 * no keys. Mirrors the Blade `captcha` partial and its `formsCaptcha`
 * Alpine component.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useMemo, useRef, useState } from 'react';

import { createCaptchaController, hasCaptchaWidget, runsOnSubmit } from '../../../shared/captcha';
import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { FieldLayout } from './FieldLayout';
import { useSubmitHooks } from './SubmitHooksContext';
import type { FieldComponentProps } from './types';
import { fieldIdKey } from '../../../shared/domIds';

/**
 * Renders a CAPTCHA field, or nothing when its provider is not set up.
 *
 * @since 1.6.0
 */
export function CaptchaField( { field, error, onChange, displayConfig }: FieldComponentProps ) {
	const submitHooks = useSubmitHooks();
	const widgetRef = useRef<HTMLDivElement>( null );
	const mountedOn = useRef<HTMLElement | null>( null );
	const [failed, setFailed] = useState( false );
	const widget = hasCaptchaWidget( field ) ? field.captcha : null;

	// The renderer passes a new callback each render; the controller keeps the first.
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	const controller = useMemo(
		() => ( widget ? createCaptchaController( widget, ( token ) => onChangeRef.current( token ) ) : null ),
		[widget],
	);

	// Render the widget once per element (Strict Mode re-runs effects on the same element).
	useEffect( () => {
		const element = widgetRef.current;

		if ( !controller || !element || mountedOn.current === element ) {
			return;
		}

		mountedOn.current = element;
		setFailed( false );
		controller.mount( element ).catch( () => setFailed( true ) );
	}, [controller] );

	useEffect( () => {
		if ( !submitHooks || !controller || !widget ) {
			return undefined;
		}

		return submitHooks.register( field.name, {
			beforeSubmit: runsOnSubmit( widget )
				? async () => ( { [field.name]: await controller.execute() } )
				: undefined,
			// The server spends the token only when every other field validated,
			// so a 422 for other fields keeps it.
			afterSubmit: ( { ok, errors } ) => {
				if ( !ok && ( errors[field.name] || Object.keys( errors ).length === 0 ) ) {
					controller.reset();
				}
			},
		} );
	}, [submitHooks, controller, widget, field.name] );

	if ( !widget ) {
		return null;
	}

	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = runsOnSubmit( widget ) || layout.label === 'hidden'
		|| ( !layout.custom && displayConfig.label_position === 'hidden' );
	const labelId = field.label ? `label-${fieldIdKey( field )}` : undefined;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text ? `${fieldIdKey( field )}-hint` : undefined );
	const errorId = error ? `${fieldIdKey( field )}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<FieldLayout field={field} layout={layout} renderLabel={false}>
			<div
				id={`field-${fieldIdKey( field )}`}
				role="group"
				aria-labelledby={labelId}
				aria-label={labelId ? undefined : field.name}
				aria-describedby={describedBy}
				data-forms-captcha={widget.provider}
			>
				{field.label && (
					<p id={labelId} className={`label-text mb-2 ${hiddenLabel ? 'sr-only' : ''}`}>{field.label}</p>
				)}

				<div ref={widgetRef} />

				{failed && (
					<p className="text-sm text-error" role="alert">
						{t( 'The verification check could not be loaded. Please reload the page and try again.' )}
					</p>
				)}

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
