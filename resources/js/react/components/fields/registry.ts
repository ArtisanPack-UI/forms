/**
 * Field extensibility registry.
 *
 * Module-level seams that let a host application (or an ecosystem package)
 * contribute a custom field type to the React renderer and builder without
 * patching the vendored source. This mirrors the server-side `ap.forms.*`
 * filter extensibility (`fieldTypes`, `fieldCategories`, `validationRules`)
 * so a type registered on the server — e.g. `artisanpack-ui/bookings`'
 * `booking_slot` — can be rendered and edited in a React host too.
 *
 * Registrations overlay the built-ins: a registered component for an existing
 * type overrides that built-in, while a new type is added alongside them.
 * Register once at application bootstrap; every `FormRenderer`, `FieldPalette`,
 * and `FieldEditor` instance then resolves the registration automatically.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.5.0
 */

import type {
	FieldPaletteGroup,
	FieldType,
	FormField,
	UpdateFieldRequest,
} from '../../../types/artisanpack-forms';
import type { FieldComponentProps } from './types';

/** A React component that renders a single form field on the public renderer. */
export type FieldComponent = React.ComponentType<FieldComponentProps>;

/** A map of field types to the component that renders them. */
export type FieldComponentMap = Record<string, FieldComponent>;

/** Props passed to a custom per-type settings panel in the admin editor. */
export interface CustomFieldSettingsProps {
	/** The field being edited. */
	field: FormField;
	/**
	 * Every field in the form, including the one being edited. Mirrors the
	 * `Form` passed to the server-side `ap.forms.fieldSettings` filter, so a
	 * custom panel can build controls that reference other fields — e.g.
	 * mapping a booking's name/email/phone to existing form fields.
	 */
	allFields: FormField[];
	/** Apply a partial update to the field (debounced-saved by the editor). */
	updateField: ( data: UpdateFieldRequest ) => void;
}

/** A React component that renders the settings panel for a custom field type. */
export type CustomFieldSettingsComponent = React.ComponentType<CustomFieldSettingsProps>;

/** Props passed to a custom builder-canvas preview for a field type. */
export interface FieldCardPreviewProps {
	/** The field being previewed on the builder canvas. */
	field: FormField;
}

/** A React component that renders a live preview on the builder field card. */
export type FieldCardPreviewComponent = React.ComponentType<FieldCardPreviewProps>;

// ---------------------------------------------------------------------------
// Field renderer components
// ---------------------------------------------------------------------------

const fieldComponentRegistry = new Map<string, FieldComponent>();

/**
 * Register the renderer component for a field type.
 *
 * @example
 * ```tsx
 * registerFieldComponent( 'booking_slot', BookingSlotField );
 * ```
 */
export function registerFieldComponent( type: FieldType, component: FieldComponent ): void {
	fieldComponentRegistry.set( type, component );
}

/** Remove a previously registered field renderer component. */
export function unregisterFieldComponent( type: FieldType ): boolean {
	return fieldComponentRegistry.delete( type );
}

/** Get the registered renderer component for a field type, if any. */
export function getRegisteredFieldComponent( type: FieldType ): FieldComponent | undefined {
	return fieldComponentRegistry.get( type );
}

/** Get a snapshot map of all registered field renderer components. */
export function getRegisteredFieldComponents(): FieldComponentMap {
	return Object.fromEntries( fieldComponentRegistry );
}

/** Clear all registered field renderer components (primarily for tests). */
export function clearRegisteredFieldComponents(): void {
	fieldComponentRegistry.clear();
}

// ---------------------------------------------------------------------------
// Field palette groups (admin builder sidebar)
// ---------------------------------------------------------------------------

const fieldPaletteGroupRegistry: FieldPaletteGroup[] = [];

/**
 * Append a palette group to the form builder sidebar.
 *
 * Registered groups render after the built-in groups, in registration order.
 *
 * @example
 * ```tsx
 * registerFieldPaletteGroup( {
 *   label: 'Bookings',
 *   fields: [
 *     { type: 'booking_slot', label: 'Booking Slot', icon: 'booking_slot', category: 'advanced', iconPath: 'M4 1h8...' },
 *   ],
 * } );
 * ```
 */
export function registerFieldPaletteGroup( group: FieldPaletteGroup ): void {
	fieldPaletteGroupRegistry.push( group );
}

/** Get a snapshot array of all registered palette groups. */
export function getRegisteredFieldPaletteGroups(): FieldPaletteGroup[] {
	return [...fieldPaletteGroupRegistry];
}

/** Clear all registered palette groups (primarily for tests). */
export function clearRegisteredFieldPaletteGroups(): void {
	fieldPaletteGroupRegistry.length = 0;
}

// ---------------------------------------------------------------------------
// Field editor settings panels
// ---------------------------------------------------------------------------

const fieldSettingsRegistry = new Map<string, CustomFieldSettingsComponent>();

/**
 * Register a custom settings panel for a field type in the admin editor.
 *
 * The panel renders in the editor's General tab, below the shared settings,
 * and receives the field plus an `updateField` callback.
 */
export function registerFieldSettings( type: FieldType, component: CustomFieldSettingsComponent ): void {
	fieldSettingsRegistry.set( type, component );
}

/** Remove a previously registered field settings panel. */
export function unregisterFieldSettings( type: FieldType ): boolean {
	return fieldSettingsRegistry.delete( type );
}

/** Get the registered settings panel for a field type, if any. */
export function getRegisteredFieldSettings( type: FieldType ): CustomFieldSettingsComponent | undefined {
	return fieldSettingsRegistry.get( type );
}

/** Clear all registered field settings panels (primarily for tests). */
export function clearRegisteredFieldSettings(): void {
	fieldSettingsRegistry.clear();
}

// ---------------------------------------------------------------------------
// Field card previews (admin builder canvas)
// ---------------------------------------------------------------------------

const fieldCardPreviewRegistry = new Map<string, FieldCardPreviewComponent>();

/**
 * Register a live canvas preview for a field type in the form builder.
 *
 * The preview renders inside the field's card on the builder canvas — the
 * React equivalent of the server-side `ap.forms.fieldCardPreview` filter.
 */
export function registerFieldCardPreview( type: FieldType, component: FieldCardPreviewComponent ): void {
	fieldCardPreviewRegistry.set( type, component );
}

/** Remove a previously registered field card preview. */
export function unregisterFieldCardPreview( type: FieldType ): boolean {
	return fieldCardPreviewRegistry.delete( type );
}

/** Get the registered card preview for a field type, if any. */
export function getRegisteredFieldCardPreview( type: FieldType ): FieldCardPreviewComponent | undefined {
	return fieldCardPreviewRegistry.get( type );
}

/** Clear all registered field card previews (primarily for tests). */
export function clearRegisteredFieldCardPreviews(): void {
	fieldCardPreviewRegistry.clear();
}
