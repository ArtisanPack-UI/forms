/**
 * Field renderer component.
 *
 * Maps a form field definition to the appropriate React component
 * based on its field type.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import type { BuiltInFieldType, DisplayConfig, FormField, FormLayout } from '../../../types/artisanpack-forms';
import { DateField, FileField } from './AdvancedField';
import {
	CheckboxField,
	CheckboxGroupField,
	RadioField,
	SelectField,
	SelectMultipleField,
} from './ChoiceField';
import { DividerField, HeadingField, HtmlField, ParagraphField } from './LayoutField';
import {
	EmailField,
	HiddenField,
	NumberField,
	PhoneField,
	TextareaField,
	TextField,
	TimeField,
	UrlField,
} from './TextField';
import { getRegisteredFieldComponent, ownEntry } from './registry';
import type { FieldComponent, FieldComponentMap } from './registry';

/** Props for the FieldRenderer component. */
export interface FieldRendererProps {
	/** The field definition from the API. */
	field: FormField;
	/** The current value for this field. */
	value: unknown;
	/** The first validation error message for this field, if any. */
	error?: string;
	/** Callback to update the field value. */
	onChange: ( value: unknown ) => void;
	/** Callback to set a file for file fields. */
	onFileChange?: ( file: File | File[] ) => void;
	/** Display configuration from the form. */
	displayConfig: DisplayConfig;
	/**
	 * Host-supplied field components that overlay the built-ins. Takes
	 * precedence over the module-level registry (see `registerFieldComponent`)
	 * and the built-in components for the same type.
	 */
	fieldComponents?: FieldComponentMap;
	/**
	 * The layout of the row the field sits in. In `inline` mode a full-width
	 * field shrinks to share its row with the submit button, and a hidden
	 * field's wrapper is removed from the flow. Defaults to `stacked`.
	 *
	 * @since 1.5.2
	 */
	layout?: FormLayout;
}

/** Map of built-in field types to their React components. */
const FIELD_COMPONENTS: Record<BuiltInFieldType, FieldComponent> = {
	text: TextField,
	email: EmailField,
	phone: PhoneField,
	number: NumberField,
	url: UrlField,
	textarea: TextareaField,
	hidden: HiddenField,
	select: SelectField,
	radio: RadioField,
	checkbox: CheckboxField,
	checkbox_group: CheckboxGroupField,
	select_multiple: SelectMultipleField,
	file: FileField,
	date: DateField,
	time: TimeField,
	heading: HeadingField,
	paragraph: ParagraphField,
	divider: DividerField,
	html: HtmlField,
};

/** CSS class map for field width. */
const WIDTH_CLASSES: Record<string, string> = {
	full: 'w-full',
	half: 'w-full md:w-1/2',
	third: 'w-full md:w-1/3',
	'two-thirds': 'w-full md:w-2/3',
};

/**
 * Returns the wrapper class for a field in an inline row.
 *
 * A full-width field grows to fill the row but may shrink to 12rem, or to
 * the row width when that is narrower, so the submit button can share its
 * line and wraps below it only when the row is too narrow. A hidden input's
 * wrapper is not displayed, so it can't take up a slot in the row. Other
 * widths keep their stacked classes.
 *
 * @since 1.5.2
 */
function getInlineWidthClass( field: FormField ): string {
	if ( field.type === 'hidden' ) {
		return 'hidden';
	}

	const width = WIDTH_CLASSES[field.width] ? field.width : 'full';

	return width === 'full' ? 'flex-1 min-w-[min(12rem,100%)]' : WIDTH_CLASSES[width];
}

/**
 * Renders a single form field based on its type.
 *
 * @example
 * ```tsx
 * <FieldRenderer
 *   field={field}
 *   value={values[field.name]}
 *   error={errors[field.name]?.[0]}
 *   onChange={(v) => setValue(field.name, v)}
 *   displayConfig={form.config.display}
 * />
 * ```
 */
export function FieldRenderer( {
	field,
	value,
	error,
	onChange,
	onFileChange,
	displayConfig,
	fieldComponents,
	layout = 'stacked',
}: FieldRendererProps ) {
	// Resolution order: host prop overlay, then the module-level registry,
	// then the built-in components. This keeps built-ins working while letting
	// a host add or override a type without patching the vendored source.
	// `ownEntry` guards the plain-object maps so an arbitrary type such as
	// `constructor` cannot resolve an inherited `Object.prototype` member.
	const Component =
		ownEntry( fieldComponents, field.type ) ??
		getRegisteredFieldComponent( field.type ) ??
		ownEntry( FIELD_COMPONENTS as Record<string, FieldComponent>, field.type );

	if ( !Component ) {
		return null;
	}

	const widthClass = layout === 'inline'
		? getInlineWidthClass( field )
		: ( WIDTH_CLASSES[field.width] ?? WIDTH_CLASSES.full );

	return (
		<div className={widthClass}>
			<Component
				field={field}
				value={value}
				error={error}
				onChange={onChange}
				onFileChange={onFileChange}
				displayConfig={displayConfig}
			/>
		</div>
	);
}
