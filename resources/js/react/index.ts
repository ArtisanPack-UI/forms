/**
 * ArtisanPack UI Forms - React Form Renderer
 *
 * Main entry point for the React form renderer package.
 * Publish with: php artisan vendor:publish --tag=forms-react
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

// Main components
export { FormRenderer } from './components/FormRenderer';
export type { FormRendererProps } from './components/FormRenderer';

export { MultiStepForm } from './components/MultiStepForm';
export type { MultiStepFormProps } from './components/MultiStepForm';

// Field components
export { FieldRenderer } from './components/fields/FieldRenderer';
export type { FieldRendererProps } from './components/fields/FieldRenderer';

export {
	TextField,
	EmailField,
	PhoneField,
	NumberField,
	UrlField,
	TextareaField,
	HiddenField,
	TimeField,
} from './components/fields/TextField';

export {
	SelectField,
	RadioField,
	CheckboxField,
	CheckboxGroupField,
	SelectMultipleField,
} from './components/fields/ChoiceField';

export {
	FileField,
	DateField,
} from './components/fields/AdvancedField';

export {
	HeadingField,
	ParagraphField,
	DividerField,
	HtmlField,
} from './components/fields/LayoutField';

export type { FieldComponentProps } from './components/fields/types';

// Field extensibility registry
export {
	registerFieldComponent,
	unregisterFieldComponent,
	getRegisteredFieldComponent,
	getRegisteredFieldComponents,
	clearRegisteredFieldComponents,
	registerFieldPaletteGroup,
	getRegisteredFieldPaletteGroups,
	clearRegisteredFieldPaletteGroups,
	registerFieldSettings,
	unregisterFieldSettings,
	getRegisteredFieldSettings,
	clearRegisteredFieldSettings,
	registerFieldCardPreview,
	unregisterFieldCardPreview,
	getRegisteredFieldCardPreview,
	clearRegisteredFieldCardPreviews,
} from './components/fields/registry';

export type {
	FieldComponent,
	FieldComponentMap,
	CustomFieldSettingsProps,
	CustomFieldSettingsComponent,
	FieldCardPreviewProps,
	FieldCardPreviewComponent,
} from './components/fields/registry';

// Hooks
export { useForm } from './hooks/useForm';
export type { UseFormOptions, UseFormReturn } from './hooks/useForm';

export { useApi, ApiError, ApiValidationError } from './hooks/useApi';
export type { UseApiOptions, UseApiReturn } from './hooks/useApi';

export { useAutoSave } from './hooks/useAutoSave';
export type { UseAutoSaveOptions, UseAutoSaveReturn } from './hooks/useAutoSave';

// Admin components
export {
	FormsList,
	FormBuilder,
	FieldEditor,
	FieldPalette,
	FIELD_PALETTE,
	ConditionalLogicEditor,
	NotificationEditor,
	SubmissionsList,
	SubmissionDetail,
} from './components/admin';

export type {
	FormsListProps,
	FormBuilderProps,
	FieldEditorProps,
	FieldPaletteProps,
	ConditionalLogicEditorProps,
	NotificationEditorProps,
	SubmissionsListProps,
	SubmissionDetailProps,
} from './components/admin';

// Utilities
export {
	compareValues,
	evaluateFieldVisibility,
	evaluateRule,
	getHiddenFields,
} from '../shared/conditionalLogic';

export {
	validateField,
	validateFields,
} from '../shared/validation';
