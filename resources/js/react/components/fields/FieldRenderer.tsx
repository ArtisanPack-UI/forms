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
import { isCompositeField } from '../../../shared/composite';
import { getWidthClass, WIDTH_CLASSES } from '../../../shared/fieldSettings';
import { DateField, FileField, RatingField } from './AdvancedField';
import {
	CheckboxField,
	CheckboxGroupField,
	RadioField,
	SelectField,
	SelectMultipleField,
	ToggleField,
} from './ChoiceField';
import { CompositeField } from './CompositeField';
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
	/** Text typed into the field's "Other" choice (since 1.6.0). */
	otherValue?: string;
	/** Callback to update the "Other" choice's text (since 1.6.0). */
	onOtherChange?: ( text: string ) => void;
	/**
	 * A composite field's first error per sub-input, keyed by sub-input
	 * (since 1.6.0; see `getSubInputErrors()`).
	 */
	subErrors?: Record<string, string>;
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
	toggle: ToggleField,
	checkbox_group: CheckboxGroupField,
	select_multiple: SelectMultipleField,
	file: FileField,
	date: DateField,
	time: TimeField,
	rating: RatingField,
	heading: HeadingField,
	paragraph: ParagraphField,
	divider: DividerField,
	html: HtmlField,
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

	const widthClass = getWidthClass( field.width );

	return widthClass === WIDTH_CLASSES.full ? 'flex-1 min-w-[min(12rem,100%)]' : widthClass;
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
	otherValue,
	onOtherChange,
	subErrors,
	fieldComponents,
	layout = 'stacked',
}: FieldRendererProps ) {
	// Resolution order: host prop overlay, then the module-level registry,
	// then the built-in components, then the generic composite component for
	// a type with sub-inputs. This keeps built-ins working while letting a
	// host add or override a type without patching the vendored source.
	// `ownEntry` guards the plain-object maps so an arbitrary type such as
	// `constructor` cannot resolve an inherited `Object.prototype` member.
	const Component =
		ownEntry( fieldComponents, field.type ) ??
		getRegisteredFieldComponent( field.type ) ??
		ownEntry( FIELD_COMPONENTS as Record<string, FieldComponent>, field.type ) ??
		( isCompositeField( field ) ? CompositeField : undefined );

	if ( !Component ) {
		return null;
	}

	const widthClass = layout === 'inline'
		? getInlineWidthClass( field )
		: getWidthClass( field.width );

	return (
		<div className={widthClass}>
			<Component
				field={field}
				value={value}
				error={error}
				onChange={onChange}
				onFileChange={onFileChange}
				displayConfig={displayConfig}
				otherValue={otherValue}
				onOtherChange={onOtherChange}
				subErrors={subErrors}
			/>
		</div>
	);
}
