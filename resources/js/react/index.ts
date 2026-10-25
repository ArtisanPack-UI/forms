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
	ToggleField,
	CheckboxGroupField,
	SelectMultipleField,
} from './components/fields/ChoiceField';

export {
	FileField,
	DateField,
	DateTimeField,
	RatingField,
} from './components/fields/AdvancedField';

export { ListField } from './components/fields/ListField';

export { SectionField, SectionGroup } from './components/fields/SectionField';
export type { SectionGroupProps } from './components/fields/SectionField';

export { FormValuesContext, useFormValues } from './components/fields/FormValuesContext';
export type { FormValuesContextValue } from './components/fields/FormValuesContext';

export {
	HeadingField,
	ParagraphField,
	DividerField,
	HtmlField,
} from './components/fields/LayoutField';

export { FieldLayout } from './components/fields/FieldLayout';
export type { FieldLayoutProps } from './components/fields/FieldLayout';

export { Combobox } from './components/fields/Combobox';
export type { ComboboxProps } from './components/fields/Combobox';

export { CompositeField } from './components/fields/CompositeField';

export { ConsentField } from './components/fields/ConsentField';

export { CaptchaField } from './components/fields/CaptchaField';

export { SubmitHooksContext, useSubmitHooks } from './components/fields/SubmitHooksContext';

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
	getRegisteredFieldSettingsRegistration,
	resolveFieldSettings,
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
	CustomFieldSettingsEntry,
	FieldSettingsOptions,
	FieldSettingsPosition,
	FieldSettingsRegistration,
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
	getFieldPalette,
	ConditionalLogicEditor,
	NotificationEditor,
	SubmissionsList,
	SubmissionDetail,
	OptionsEditor,
	SubInputsEditor,
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
	OptionsEditorProps,
	SubInputsEditorProps,
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

// Field layout and "Other" choice helpers (since 1.6.0)
export {
	OTHER_CHOICE_VALUE,
	allowsOtherChoice,
	getFieldLayout,
	getOtherChoiceLabel,
	resolveOtherChoices,
} from '../shared/fieldSettings';
export type { DescriptionPlacement, FieldLayout as FieldLayoutSettings, LabelPlacement } from '../shared/fieldSettings';

// Choice field helpers (since 1.6.0)
export {
	applyBulkChoices,
	getChoiceLayout,
	getChoiceLayoutClasses,
	getDefaultOptionValues,
	getRenderOptions,
	parseBulkChoices,
	shuffleWithSeed,
	toBulkChoices,
} from '../shared/choices';

// Composite field helpers (since 1.6.0)
export {
	buildSubInputUpdate,
	getConditionTargets,
	getEnabledSubInputs,
	getRequiredSubInputKeys,
	getSubInputErrors,
	isCompositeField,
} from '../shared/composite';
export type { ConditionTarget, SubInputSetting } from '../shared/composite';

export { isDateRange, validateCompositeField } from '../shared/validation';

// Section, list and live merge tag helpers (since 1.6.0)
export {
	applySectionVisibility,
	getFieldSegments,
	getSectionMembers,
} from '../shared/sections';
export type { FieldSegment } from '../shared/sections';

export {
	getInitialListRows,
	getListColumns,
	validateListField,
} from '../shared/list';
export type { ListRow } from '../shared/list';

export { applyLiveMergeTags } from '../shared/liveMergeTags';

// Email confirmation helpers (since 1.6.0)
export {
	getConfirmationKey,
	requiresEmailConfirmation,
} from '../shared/fieldSettings';

export { validateCaptchaField, validateEmailConfirmation } from '../shared/validation';

// CAPTCHA and submit hook helpers (since 1.6.0)
export {
	CAPTCHA_SCRIPT_TIMEOUT,
	createCaptchaController,
	createSubmitHooks,
	hasCaptchaWidget,
	loadCaptchaScript,
	runsOnSubmit,
	SubmitHookError,
} from '../shared/captcha';
export type { CaptchaController, SubmitHook, SubmitHooks } from '../shared/captcha';

// Dynamic population (since 1.6.0)
export {
	getPageContext,
	getPopulatedValue,
	resolvePageMergeTags,
	sanitizePopulatedValue,
	MERGE_TAGS,
} from '../shared/dynamicPopulation';
export type { PageContext, PopulationSource } from '../shared/dynamicPopulation';

// Translations for the admin components
export {
	t,
	setTranslations,
	getTranslations,
	clearTranslations,
} from '../shared/i18n';

export type { TranslationReplacements } from '../shared/i18n';
