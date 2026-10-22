/**
 * ArtisanPack UI Forms - Vue Form Renderer
 *
 * Main entry point for the Vue form renderer package.
 * Publish with: php artisan vendor:publish --tag=forms-vue
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

// Main components
export { default as FormRenderer } from './components/FormRenderer.vue';
export { default as MultiStepForm } from './components/MultiStepForm.vue';

// Field components
export { default as FieldRenderer } from './components/fields/FieldRenderer.vue';
export { default as TextField } from './components/fields/TextField.vue';
export { default as ChoiceField } from './components/fields/ChoiceField.vue';
export { default as AdvancedField } from './components/fields/AdvancedField.vue';
export { default as LayoutField } from './components/fields/LayoutField.vue';
export { default as RatingField } from './components/fields/RatingField.vue';
export { default as FieldLayout } from './components/fields/FieldLayout.vue';
export { default as Combobox } from './components/fields/Combobox.vue';
export { default as CompositeField } from './components/fields/CompositeField.vue';

// Composables
export { useForm } from './composables/useForm';
export type { UseFormOptions } from './composables/useForm';

export { useApi, ApiError, ApiValidationError } from './composables/useApi';
export type { UseApiOptions, UseApiReturn } from './composables/useApi';

export { useAutoSave } from './composables/useAutoSave';
export type { UseAutoSaveOptions, UseAutoSaveReturn } from './composables/useAutoSave';

// Admin components
export {
	FormsList,
	FormBuilder,
	FieldEditor,
	FieldPalette,
	FieldSettingControl,
	ConditionalLogicEditor,
	NotificationEditor,
	SubmissionsList,
	SubmissionDetail,
	OptionsEditor,
	SubInputsEditor,
} from './components/admin';

// Shared utilities (re-exported for convenience)
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

export { validateCompositeField } from '../shared/validation';

// Dynamic population (since 1.6.0)
export {
	getPageContext,
	getPopulatedValue,
	resolvePageMergeTags,
	sanitizePopulatedValue,
	MERGE_TAGS,
} from '../shared/dynamicPopulation';
export type { PageContext, PopulationSource } from '../shared/dynamicPopulation';

// Translations for the admin components (since 1.6.0)
export {
	t,
	setTranslations,
	getTranslations,
	clearTranslations,
} from '../shared/i18n';
export type { TranslationReplacements } from '../shared/i18n';
