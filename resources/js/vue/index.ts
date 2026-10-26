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
export { default as EmailConfirmationField } from './components/fields/EmailConfirmationField.vue';
export { default as ConsentField } from './components/fields/ConsentField.vue';
export { default as CaptchaField } from './components/fields/CaptchaField.vue';
export { default as ListField } from './components/fields/ListField.vue';
export { default as SectionField } from './components/fields/SectionField.vue';
export { default as SliderField } from './components/fields/SliderField.vue';
export { default as NpsField } from './components/fields/NpsField.vue';
export { default as MatrixField } from './components/fields/MatrixField.vue';
export { default as RankingField } from './components/fields/RankingField.vue';
export { default as SignatureField } from './components/fields/SignatureField.vue';
export { default as ColorField } from './components/fields/ColorField.vue';
export { default as CalculationField } from './components/fields/CalculationField.vue';

// Composables
export { useForm } from './composables/useForm';
export type { UseFormOptions } from './composables/useForm';

// Submit hooks fields register with their form (since 1.6.0)
export { SUBMIT_HOOKS_KEY, useSubmitHooks } from './composables/submitHooks';

// The form's fields and values, for HTML block merge tags (since 1.6.0)
export { FORM_VALUES_KEY, useFormValues } from './composables/formValues';
export type { FormValues } from './composables/formValues';

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

// Slider, NPS, matrix, ranking and signature helpers (since 1.6.0)
export { formatSliderValue, getSliderSettings, validateSlider } from '../shared/slider';
export { getNpsLabels, NPS_SCORES, validateNps } from '../shared/nps';
export { getMatrixColumns, getMatrixRows, isSingleScale, validateMatrixField } from '../shared/matrix';
export { getRankingChoices, getRankingOrder, moveRankingItem, validateRanking } from '../shared/ranking';
export { isDrawnSignature, SignaturePad, validateSignature } from '../shared/signature';
export type { SignatureMode } from '../shared/signature';

// Calculation helpers (since 1.6.0)
export { applyCalculations, checkFormula, evaluateFormula, formatCalculation, getFormulaReferences, resolveCalculatedValues } from '../shared/calculation';

// Color helpers (since 1.6.0)
export { getColorPickerValue, isHexColor, normalizeColor, validateColor } from '../shared/color';

// Email confirmation helpers (since 1.6.0)
export { getConfirmationKey, requiresEmailConfirmation } from '../shared/fieldSettings';
export { validateCaptchaField, validateEmailConfirmation } from '../shared/validation';

// CAPTCHA helpers (since 1.6.0)
export {
	CAPTCHA_SCRIPT_TIMEOUT,
	SubmitHookError,
	createCaptchaController,
	createSubmitHooks,
	hasCaptchaWidget,
	loadCaptchaScript,
	runsOnSubmit,
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

// Translations for the admin components (since 1.6.0)
export {
	t,
	setTranslations,
	getTranslations,
	clearTranslations,
} from '../shared/i18n';
export type { TranslationReplacements } from '../shared/i18n';
