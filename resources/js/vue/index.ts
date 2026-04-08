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
	ConditionalLogicEditor,
	NotificationEditor,
	SubmissionsList,
	SubmissionDetail,
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
