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

import type { JSX } from 'react';
import type { FieldComponentProps } from './types';

type HeadingTag = 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

/**
 * Renders a heading element (h1-h6).
 */
export function HeadingField( { field }: FieldComponentProps ) {
	const level = field.field_config && 'level' in field.field_config
		? Number( field.field_config.level )
		: 2;
	const Tag = ( `h${Math.min( Math.max( level, 1 ), 6 )}` as HeadingTag ) satisfies keyof JSX.IntrinsicElements;

	return (
		<Tag className={`font-bold ${field.css_classes ?? ''}`}>
			{field.label ?? ''}
		</Tag>
	);
}

/**
 * Renders a paragraph of text.
 */
export function ParagraphField( { field }: FieldComponentProps ) {
	return (
		<p className={field.css_classes ?? undefined}>
			{field.default_value ?? field.label ?? ''}
		</p>
	);
}

/**
 * Renders a horizontal divider.
 */
export function DividerField( { field }: FieldComponentProps ) {
	return <hr className={`divider ${field.css_classes ?? ''}`} />;
}

/**
 * Renders custom HTML content.
 */
export function HtmlField( { field }: FieldComponentProps ) {
	const content = field.default_value ?? '';

	return (
		<div
			className={field.css_classes ?? undefined}
			dangerouslySetInnerHTML={{ __html: content }}
		/>
	);
}
