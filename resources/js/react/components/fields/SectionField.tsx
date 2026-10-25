/**
 * Section component.
 *
 * Wraps the fields a section groups in a `<fieldset>` whose `<legend>` is
 * the section's label, with its description (since 1.6.0; see
 * `getFieldSegments()`). A collapsible section's legend is a disclosure
 * button (`aria-expanded`, `aria-controls`); it opens when one of its
 * fields has an error. A hidden section's fields are hidden by conditional
 * logic; its fieldset stays shown, without the legend, only when it holds a
 * CAPTCHA, which is never hidden. Mirrors the Blade `section` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { getSectionDescription, isCollapsibleSection, startsCollapsed } from '../../../shared/sections';
import type { FormField } from '../../../types/artisanpack-forms';

import type { FieldComponentProps } from './types';

/** Props for the SectionGroup component. */
export interface SectionGroupProps {
	/** The section field. */
	section: FormField;
	/** Whether conditional logic hides the section. */
	hidden?: boolean;
	/** Whether one of the section's fields is a CAPTCHA, which stays shown. */
	holdsCaptcha?: boolean;
	/** Whether one of the section's fields has an error. */
	hasErrors?: boolean;
	/** The section's fields. */
	children?: ReactNode;
}

/**
 * Renders a section around the fields it groups.
 */
export function SectionGroup( { section, hidden = false, holdsCaptcha = false, hasErrors = false, children }: SectionGroupProps ) {
	const collapsible = isCollapsibleSection( section );
	const description = getSectionDescription( section );
	const contentId = `section-${section.name}-content`;
	const descriptionId = `section-${section.name}-description`;
	const [open, setOpen] = useState( !startsCollapsed( section ) );

	// A collapsed section opens to show its errors.
	useEffect( () => {
		if ( hasErrors ) {
			setOpen( true );
		}
	}, [hasErrors] );

	if ( hidden && !holdsCaptcha ) {
		return null;
	}

	return (
		<fieldset
			id={`section-${section.name}`}
			className={`fieldset w-full rounded-box border border-base-300 p-4 ${section.css_classes ?? ''}`}
			data-forms-section={section.name}
			aria-describedby={description && !hidden ? descriptionId : undefined}
		>
			{!hidden && (
				<legend className="fieldset-legend text-lg font-semibold">
					{collapsible ? (
						<button
							type="button"
							className="flex items-center gap-2 rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
							aria-controls={contentId}
							aria-expanded={open}
							onClick={() => setOpen( ( current ) => !current )}
						>
							<span aria-hidden="true" className={`inline-block transition-transform ${open ? 'rotate-90' : ''}`}>›</span>
							<span>{section.label}</span>
						</button>
					) : section.label}
				</legend>
			)}

			{description && !hidden && (
				<p id={descriptionId} className="text-sm text-base-content/70 whitespace-pre-line">{description}</p>
			)}

			<div id={contentId} className={`mt-2 flex-wrap gap-4 ${collapsible && !open && !hidden ? 'hidden' : 'flex'}`}>
				{children}
			</div>
		</fieldset>
	);
}

/**
 * Renders a section on its own, without the fields it groups (as
 * `FieldRenderer` does when given a section).
 */
export function SectionField( { field }: FieldComponentProps ) {
	return <SectionGroup section={field} />;
}
