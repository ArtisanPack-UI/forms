/**
 * Layout field components.
 *
 * Renders non-data layout elements: heading, paragraph, divider, and HTML.
 * Since 1.6.0 a heading has an alignment, a paragraph keeps its basic
 * formatting (sanitized by the server as `content_html`) and an HTML block's
 * `{field_name}` merge tags show what the visitor entered, as they type.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { getDividerClasses, getHeadingAlignClass, getHeadingLevel, getLayoutContent } from '../../../shared/fieldSettings';
import { applyLiveMergeTags } from '../../../shared/liveMergeTags';

import { useFormValues } from './FormValuesContext';
import type { FieldComponentProps } from './types';

/**
 * Renders a heading element (h2-h6).
 */
export function HeadingField( { field }: FieldComponentProps ) {
	const Tag = getHeadingLevel( field );

	return (
		<Tag className={`font-bold ${getHeadingAlignClass( field )} ${field.css_classes ?? ''}`}>
			{getLayoutContent( field )}
		</Tag>
	);
}

/**
 * Renders a paragraph of text. The server's sanitized `content_html` keeps
 * bold, italic, underline and links; without it the text is shown as is.
 */
export function ParagraphField( { field }: FieldComponentProps ) {
	if ( field.content_html !== undefined ) {
		return (
			<p
				className={field.css_classes ?? undefined}
				dangerouslySetInnerHTML={{ __html: field.content_html }}
			/>
		);
	}

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
 * Renders custom HTML content, sanitized by the server, with its merge tags
 * filled in from the form's values (escaped).
 */
export function HtmlField( { field }: FieldComponentProps ) {
	const form = useFormValues();
	const content = getLayoutContent( field );

	return (
		<div
			className={field.css_classes ?? undefined}
			dangerouslySetInnerHTML={{ __html: form ? applyLiveMergeTags( content, form.fields, form ) : content }}
		/>
	);
}
