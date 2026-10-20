/**
 * Layout field components.
 *
 * Renders non-data layout elements: heading, paragraph, divider, and HTML.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { getDividerClasses, getHeadingLevel, getLayoutContent } from '../../../shared/fieldSettings';

import type { FieldComponentProps } from './types';

/**
 * Renders a heading element (h2-h6).
 */
export function HeadingField( { field }: FieldComponentProps ) {
	const Tag = getHeadingLevel( field );

	return (
		<Tag className={`font-bold ${field.css_classes ?? ''}`}>
			{getLayoutContent( field )}
		</Tag>
	);
}

/**
 * Renders a paragraph of text.
 */
export function ParagraphField( { field }: FieldComponentProps ) {
	return (
		<p className={field.css_classes ?? undefined}>
			{getLayoutContent( field )}
		</p>
	);
}

/**
 * Renders a horizontal divider.
 */
export function DividerField( { field }: FieldComponentProps ) {
	return <hr className={`divider ${getDividerClasses( field )} ${field.css_classes ?? ''}`} />;
}

/**
 * Renders custom HTML content.
 */
export function HtmlField( { field }: FieldComponentProps ) {
	const content = getLayoutContent( field );

	return (
		<div
			className={field.css_classes ?? undefined}
			dangerouslySetInnerHTML={{ __html: content }}
		/>
	);
}
