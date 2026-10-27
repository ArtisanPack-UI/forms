/**
 * Color field component.
 *
 * The browser's color picker joined to a text input holding the hex code
 * (since 1.6.0; see `shared/color.ts`). The picker shows the chosen color as
 * its swatch, and the text input is labelled by the field label, so the
 * color can be typed as well as picked. A typed color is normalized when the
 * input loses focus (`F80` becomes `#ff8800`). Mirrors the Blade `color`
 * partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { getColorPickerValue, normalizeColor } from '../../../shared/color';
import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';

import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';
import { fieldIdKey } from '../../../shared/domIds';

/**
 * Renders a color field.
 */
export function ColorField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const readOnly = isReadOnlyField( field );
	const text = typeof value === 'string' ? value : '';
	const inputId = layout.custom ? layout.inputId : `field-${fieldIdKey( field )}`;
	const labelHidden = displayConfig.label_position === 'hidden';
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text && !error ? `${fieldIdKey( field )}-hint` : undefined );
	const errorId = error ? `${fieldIdKey( field )}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<FieldLayout field={field} layout={layout}>
			<div className="fieldset">
				{!layout.custom && field.label && (
					<label htmlFor={inputId} className={`fieldset-legend ${labelHidden ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</label>
				)}
				<div className="join w-full">
					<input
						type="color"
						id={`${inputId}-picker`}
						className={`input join-item h-auto w-14 shrink-0 cursor-pointer p-1 ${error ? 'input-error' : ''}`}
						value={getColorPickerValue( text )}
						aria-label={t( ':field color picker', { field: field.label || field.name } )}
						disabled={readOnly}
						onChange={( e ) => onChange( e.target.value )}
					/>
					<input
						type="text"
						id={inputId}
						name={field.name}
						value={text}
						placeholder={field.placeholder || '#rrggbb'}
						maxLength={9}
						spellCheck={false}
						autoComplete="off"
						className={`input input-bordered join-item w-full font-mono ${error ? 'input-error' : ''} ${field.css_classes ?? ''}`}
						aria-label={field.label ? undefined : field.name}
						aria-describedby={describedBy}
						aria-required={field.is_required ? 'true' : undefined}
						aria-readonly={readOnly ? 'true' : undefined}
						aria-invalid={error ? true : undefined}
						required={field.is_required}
						readOnly={readOnly}
						onChange={( e ) => onChange( e.target.value )}
						onBlur={( e ) => onChange( normalizeColor( e.target.value ) )}
					/>
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
