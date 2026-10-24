/**
 * Consent field component.
 *
 * Renders the field label as a heading, a checkbox with the consent text
 * beside it, and the consent description below. The description is HTML the
 * server sanitized with kses (links allowed), and it is part of the
 * checkbox's description, so screen readers announce what is being agreed
 * to. Mirrors the Blade `consent` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { isChecked } from './ChoiceField';
import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/**
 * Renders a consent field.
 *
 * @since 1.6.0
 */
export function ConsentField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const checked = isChecked( value );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const text = field.consent?.text ?? '';
	const description = field.consent?.description ?? '';
	const descriptionId = description !== '' ? `consent-description-${field.name}` : undefined;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text ? `${field.name}-hint` : undefined );
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [descriptionId, hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<FieldLayout field={field} layout={layout} renderLabel={false}>
			<div className="flex flex-col gap-2">
				{field.label && (
					<p id={`label-${field.name}`} className={`label-text font-medium ${hiddenLabel ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
					</p>
				)}

				<label className="label cursor-pointer justify-start gap-3">
					<input
						type="checkbox"
						id={`field-${field.name}`}
						name={field.name}
						className={`checkbox checkbox-primary ${field.css_classes ?? ''}`}
						checked={checked}
						required={field.is_required}
						disabled={readOnly}
						aria-readonly={readOnly ? 'true' : undefined}
						aria-required={field.is_required ? 'true' : undefined}
						aria-invalid={error ? true : undefined}
						aria-describedby={describedBy}
						onChange={( e ) => onChange( e.target.checked )}
					/>
					<span className="label-text whitespace-normal">{text}</span>
				</label>

				{descriptionId && (
					<div
						id={descriptionId}
						className="prose prose-sm max-w-none text-base-content/70"
						// Sanitized with kses by the server (FormFieldResource).
						dangerouslySetInnerHTML={{ __html: description }}
					/>
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
