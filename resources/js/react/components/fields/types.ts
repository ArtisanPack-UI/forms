/**
 * Shared types for field components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';

/**
 * Common props passed to every field component.
 */
export interface FieldComponentProps {
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
	/** A composite field's first error per sub-input, keyed by sub-input (since 1.6.0). */
	subErrors?: Record<string, string>;
}
