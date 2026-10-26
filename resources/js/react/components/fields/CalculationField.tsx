/**
 * Calculation field component.
 *
 * A read-only input showing the result of the field's formula, worked out
 * from the other values as the visitor types (since 1.6.0; see
 * `shared/calculation.ts` and `useForm`). It stays focusable and labelled,
 * so screen readers can read the result. The server works it out again on
 * submit. Mirrors the Blade `calculation` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { getFieldLayout } from '../../../shared/fieldSettings';

import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/**
 * Renders a calculation field.
 */
export function CalculationField( { field, value, error, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const inputId = layout.custom ? layout.inputId : `field-${field.name}`;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text && !error ? `${field.name}-hint` : undefined );
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;

	return (
		<FieldLayout field={field} layout={layout}>
			<div className="fieldset">
				{!layout.custom && field.label && (
					<label htmlFor={inputId} className={`fieldset-legend ${displayConfig.label_position === 'hidden' ? 'sr-only' : ''}`}>
						{field.label}
					</label>
				)}
				<input
					type="text"
					id={inputId}
					name={field.name}
					value={typeof value === 'string' || typeof value === 'number' ? String( value ) : ''}
					readOnly
					aria-readonly="true"
					inputMode="decimal"
					data-forms-calculation={field.name}
					className={`input input-bordered w-full bg-base-200 font-medium tabular-nums ${field.css_classes ?? ''}`}
					aria-label={field.label ? undefined : field.name}
					aria-describedby={describedBy}
				/>
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
