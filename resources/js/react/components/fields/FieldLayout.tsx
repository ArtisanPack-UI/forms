/**
 * Field label and description placement.
 *
 * Wraps a field's input when the field or its form sets a label or
 * description placement (see `getFieldLayout()`): the label on top, to the
 * left (from the `md` breakpoint up) or visually hidden, and the description
 * above or below the input. Without a placement the input component keeps
 * rendering its own label and hint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { ReactNode } from 'react';

import type { FieldLayout as FieldLayoutSettings } from '../../../shared/fieldSettings';
import type { FormField } from '../../../types/artisanpack-forms';

/** Props for the FieldLayout component. */
export interface FieldLayoutProps {
	field: FormField;
	layout: FieldLayoutSettings;
	/**
	 * Whether to render the label. False for a single checkbox or toggle,
	 * whose label sits beside the box.
	 */
	renderLabel?: boolean;
	/**
	 * Whether the label names a group (referenced by the group's
	 * `aria-labelledby`) rather than one input (`htmlFor`).
	 */
	group?: boolean;
	children: ReactNode;
}

/**
 * Places a field's label and description around its input.
 */
export function FieldLayout( { field, layout, renderLabel = true, group = false, children }: FieldLayoutProps ) {
	if ( !layout.custom ) {
		return <>{children}</>;
	}

	const showLabel = renderLabel && Boolean( field.label );
	const isLeft = showLabel && layout.label === 'left';
	const labelClass = ['fieldset-legend', layout.label === 'hidden' ? 'sr-only' : '', isLeft ? 'md:pt-2' : '']
		.filter( Boolean )
		.join( ' ' );
	const labelContent = (
		<>
			{field.label}
			{field.is_required && <span className="text-error ml-1">*</span>}
		</>
	);
	const description = field.help_text
		? <p id={layout.descriptionId} className="fieldset-label">{field.help_text}</p>
		: null;

	return (
		<div className={isLeft ? 'md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:items-start md:gap-x-4' : undefined}>
			{showLabel && ( group
				? <span id={layout.labelId} className={labelClass}>{labelContent}</span>
				: <label id={layout.labelId} htmlFor={layout.inputId} className={labelClass}>{labelContent}</label>
			)}
			<div className={isLeft ? 'md:col-start-2' : undefined}>
				{layout.description === 'above' && description}
				{children}
				{layout.description === 'below' && description}
			</div>
		</div>
	);
}
