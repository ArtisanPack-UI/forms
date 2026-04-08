<!--
  FormRenderer component.

  Main component that fetches a form definition from the API and renders
  all fields with conditional logic, multi-step navigation, validation,
  honeypot protection, and submission handling.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { Button } from '@artisanpack-ui/vue/form';
import type { SubmitFormResponse } from '../../types/artisanpack-forms';
import { useForm } from '../composables/useForm';
import FieldRenderer from './fields/FieldRenderer.vue';
import HoneypotField from './HoneypotField.vue';
import MultiStepForm from './MultiStepForm.vue';

const props = withDefaults( defineProps<{
	/** Base URL for the forms API. */
	baseUrl: string;
	/** The form slug or ID used in the API URL. */
	formSlug: string;
	/** Whether to validate on the client before submitting. */
	clientValidation?: boolean;
	/** Optional CSS class for the form wrapper. */
	formClass?: string;
}>(), {
	clientValidation: true,
	formClass: undefined,
} );

const emit = defineEmits<{
	success: [response: SubmitFormResponse];
	error: [error: Error];
}>();

const {
	form,
	values,
	errors,
	hiddenFields,
	isLoading,
	isSubmitting,
	isSubmitted,
	currentStep,
	totalSteps,
	progressPercentage,
	currentFields,
	currentStepData,
	loadError,
	setValue,
	setFile,
	nextStep,
	prevStep,
	goToStep,
	submit,
	reset,
} = useForm( {
	baseUrl: props.baseUrl,
	formSlug: props.formSlug,
	clientValidation: props.clientValidation,
	onSuccess: ( response ) => emit( 'success', response ),
	onError: ( error ) => emit( 'error', error ),
} );

function handleFormSubmit( e: Event ): void {
	e.preventDefault();
	submit();
}
</script>

<template>
	<!-- Loading state -->
	<div v-if="isLoading" class="animate-pulse space-y-4">
		<slot name="loading">
			<div class="h-8 bg-base-300 rounded w-1/3" />
			<div class="space-y-3">
				<div class="h-10 bg-base-300 rounded" />
				<div class="h-10 bg-base-300 rounded" />
				<div class="h-10 bg-base-300 rounded" />
			</div>
			<div class="h-10 bg-base-300 rounded w-1/4" />
		</slot>
	</div>

	<!-- Load error -->
	<div v-else-if="loadError" class="alert alert-error" role="alert">
		<slot name="error" :message="loadError">
			<span>{{ loadError }}</span>
		</slot>
	</div>

	<!-- No form -->
	<div v-else-if="!form" class="alert alert-error" role="alert">
		<span>Form not found.</span>
	</div>

	<!-- Success state -->
	<div v-else-if="isSubmitted" class="space-y-4">
		<slot name="success" :message="form.success_message ?? 'Form submitted successfully.'">
			<div class="alert alert-success" role="status">
				<span>{{ form.success_message ?? 'Form submitted successfully.' }}</span>
			</div>
		</slot>
		<Button type="button" @click="reset">
			Submit another response
		</Button>
	</div>

	<!-- Form -->
	<form v-else :class="formClass" novalidate @submit="handleFormSubmit">
		<!-- Form description -->
		<p v-if="form.description" class="mb-4 opacity-70">
			{{ form.description }}
		</p>

		<!-- Form-level error messages -->
		<div v-if="errors._form?.length" class="alert alert-error mb-4" role="alert">
			<span v-for="( msg, i ) in errors._form" :key="i">{{ msg }}</span>
		</div>

		<!-- Multi-step form -->
		<MultiStepForm
			v-if="form.is_multi_step && currentStepData"
			:current-step-data="currentStepData"
			:current-step="currentStep"
			:total-steps="totalSteps"
			:progress-percentage="progressPercentage"
			:show-progress-bar="form.show_progress_bar"
			:allow-step-navigation="form.allow_step_navigation"
			:is-submitting="isSubmitting"
			:is-last-step="currentStep === totalSteps - 1"
			:submit-button-text="form.submit_button_text"
			@next-step="nextStep"
			@prev-step="prevStep"
			@go-to-step="goToStep"
			@submit="submit"
		>
			<div class="flex flex-wrap gap-4">
				<template v-for="field in currentFields" :key="field.id">
					<FieldRenderer
						v-if="!hiddenFields[field.name]"
						:field="field"
						:model-value="values[field.name]"
						:error="errors[field.name]?.[0]"
						:display-config="form.config.display"
						@update:model-value="setValue( field.name, $event )"
						@file-change="setFile( field.name, $event )"
					/>
				</template>

				<HoneypotField
					v-if="form.config.honeypot.enabled"
					:slug="form.slug"
					:field-name="form.config.honeypot.field_name"
				/>
			</div>
		</MultiStepForm>

		<!-- Single-step form -->
		<div v-else class="space-y-6">
			<div class="flex flex-wrap gap-4">
				<template v-for="field in currentFields" :key="field.id">
					<FieldRenderer
						v-if="!hiddenFields[field.name]"
						:field="field"
						:model-value="values[field.name]"
						:error="errors[field.name]?.[0]"
						:display-config="form.config.display"
						@update:model-value="setValue( field.name, $event )"
						@file-change="setFile( field.name, $event )"
					/>
				</template>

				<HoneypotField
					v-if="form.config.honeypot.enabled"
					:slug="form.slug"
					:field-name="form.config.honeypot.field_name"
				/>
			</div>
			<div>
				<Button type="submit" color="primary" :disabled="isSubmitting">
					{{ isSubmitting ? 'Submitting...' : form.submit_button_text }}
				</Button>
			</div>
		</div>
	</form>
</template>
