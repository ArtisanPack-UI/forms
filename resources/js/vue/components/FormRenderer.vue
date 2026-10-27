<!--
  FormRenderer component.

  Main component that fetches a form definition from the API and renders
  all fields with conditional logic, multi-step navigation, validation,
  honeypot protection, and submission handling. Since 1.6.0 a section's
  fields are grouped in its fieldset, and HTML blocks get the form's values
  for their merge tags.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, provide } from 'vue';
import type { FunctionalComponent } from 'vue';
import { Button } from '@artisanpack-ui/vue/form';
import { getOffStepSubmitCaptchas } from '../../shared/captcha';
import { getSubInputErrors } from '../../shared/composite';
import { getConfirmationKey, requiresEmailConfirmation } from '../../shared/fieldSettings';
import { getFieldSegments } from '../../shared/sections';
import type { FieldSegment } from '../../shared/sections';
import type { FormField, FormLayout, SubmitFormResponse } from '../../types/artisanpack-forms';
import { useForm } from '../composables/useForm';
import { FORM_VALUES_KEY } from '../composables/formValues';
import { SUBMIT_HOOKS_KEY } from '../composables/submitHooks';
import FieldRenderer from './fields/FieldRenderer.vue';
import SectionField from './fields/SectionField.vue';
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
	/**
	 * How the submit button sits relative to the fields. `stacked` (the
	 * default) renders it below the field row. `inline` renders it as the last
	 * item in the field row, aligned to the bottom of the fields, so a short
	 * form such as a newsletter signup sits on one line and wraps when the row
	 * is too narrow. Multi-step forms ignore this.
	 *
	 * @since 1.5.2
	 */
	layout?: FormLayout;
	/**
	 * Extra CSS classes appended to the submit button's classes. Applies to
	 * single-step forms in both layouts.
	 *
	 * @since 1.5.2
	 */
	submitClassName?: string;
	/**
	 * Values for fields with dynamic population on, keyed by parameter name.
	 * Takes precedence over the page's query string and the field's default.
	 *
	 * @since 1.6.0
	 */
	prefill?: Record<string, unknown>;
}>(), {
	clientValidation: true,
	formClass: undefined,
	layout: 'stacked',
	submitClassName: undefined,
	prefill: undefined,
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
	otherValues,
	setOtherValue,
	nextStep,
	prevStep,
	goToStep,
	submit,
	reset,
	submitHooks,
} = useForm( {
	baseUrl: props.baseUrl,
	formSlug: props.formSlug,
	clientValidation: props.clientValidation,
	prefill: props.prefill,
	onSuccess: ( response ) => emit( 'success', response ),
	onError: ( error ) => emit( 'error', error ),
} );

// Fields such as the CAPTCHA register their submit hooks with the form (since 1.6.0).
provide( SUBMIT_HOOKS_KEY, submitHooks );

// HTML blocks read the form's fields and values for their merge tags (since 1.6.0).
provide( FORM_VALUES_KEY, computed( () => ( {
	fields: form.value?.fields ?? [],
	values: values.value,
	hiddenFields: hiddenFields.value,
	otherValues: otherValues.value,
} ) ) );

/** The current fields, with each section's fields grouped (since 1.6.0). */
const segments = computed( () => getFieldSegments( currentFields.value ) );

/**
 * A multi-step form's invisible CAPTCHAs on other steps, kept mounted so
 * their submit hooks fetch a token (since 1.6.0).
 */
const offStepCaptchas = computed( () => ( form.value?.is_multi_step && currentStepData.value
	? getOffStepSubmitCaptchas( form.value.fields, currentStepData.value.id, hiddenFields.value )
	: [] ) );

/** Renders the fields before the first section as they are, without a wrapper. */
const FieldGroup: FunctionalComponent = ( _props, { slots } ) => slots.default?.();

/** Whether a field, or an input nested under it, has an error. */
function hasFieldErrors( field: FormField ): boolean {
	return Object.keys( errors.value ).some( ( key ) => key === field.name || key.startsWith( `${field.name}.` ) );
}

/** The props of a segment's section, or none for the fields before the first section. */
function sectionProps( segment: FieldSegment ): Record<string, unknown> {
	if ( !segment.section ) {
		return {};
	}

	return {
		field: segment.section,
		hidden: hiddenFields.value[segment.section.name] ?? false,
		holdsCaptcha: segment.fields.some( ( field ) => field.type === 'captcha' ),
		hasErrors: segment.fields.some( hasFieldErrors ),
	};
}

/**
 * The confirmation an email field that asks for the address twice holds,
 * or undefined for other fields (since 1.6.0).
 */
function getConfirmValue( field: FormField ): string | undefined {
	return requiresEmailConfirmation( field ) ? String( values.value[getConfirmationKey( field )] ?? '' ) : undefined;
}

/** Store an email field's confirmation (since 1.6.0). */
function setConfirmValue( field: FormField, text: string ): void {
	setValue( getConfirmationKey( field ), text );
}

/** Whether the single-step form renders its submit button in the field row. */
const isInline = computed( () => props.layout === 'inline' );

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
				<template v-for="( segment, index ) in segments" :key="segment.section?.id ?? `fields-${index}`">
					<component :is="segment.section ? SectionField : FieldGroup" v-bind="sectionProps( segment )">
						<template v-for="field in segment.fields" :key="field.id">
							<FieldRenderer
								v-if="!hiddenFields[field.name]"
								:field="field"
								:model-value="values[field.name]"
								:error="errors[field.name]?.[0]"
								:display-config="form.config.display"
								:other-value="otherValues[field.name] ?? ''"
								:confirm-value="getConfirmValue( field )"
								:sub-errors="getSubInputErrors( errors, field.name )"
								@update:model-value="setValue( field.name, $event )"
								@update:other-value="setOtherValue( field.name, $event )"
								@update:confirm-value="setConfirmValue( field, $event )"
								@file-change="setFile( field.name, $event )"
							/>
						</template>
					</component>
				</template>

				<HoneypotField
					v-if="form.config.honeypot.enabled"
					:slug="form.slug"
					:field-name="form.config.honeypot.field_name"
				/>
			</div>

			<!-- Invisible CAPTCHAs on other steps stay mounted, so their submit hooks fetch a token -->
			<div v-if="offStepCaptchas.length > 0" class="sr-only">
				<FieldRenderer
					v-for="field in offStepCaptchas"
					:key="field.id"
					:field="field"
					:model-value="values[field.name]"
					:error="errors[field.name]?.[0]"
					:display-config="form.config.display"
					@update:model-value="setValue( field.name, $event )"
				/>
			</div>
		</MultiStepForm>

		<!-- Single-step form -->
		<div v-else class="space-y-6">
			<div class="flex flex-wrap gap-4">
				<template v-for="( segment, index ) in segments" :key="segment.section?.id ?? `fields-${index}`">
					<component :is="segment.section ? SectionField : FieldGroup" v-bind="sectionProps( segment )">
						<template v-for="field in segment.fields" :key="field.id">
							<FieldRenderer
								v-if="!hiddenFields[field.name]"
								:field="field"
								:model-value="values[field.name]"
								:error="errors[field.name]?.[0]"
								:display-config="form.config.display"
								:layout="layout"
								:other-value="otherValues[field.name] ?? ''"
								:confirm-value="getConfirmValue( field )"
								:sub-errors="getSubInputErrors( errors, field.name )"
								@update:model-value="setValue( field.name, $event )"
								@update:other-value="setOtherValue( field.name, $event )"
								@update:confirm-value="setConfirmValue( field, $event )"
								@file-change="setFile( field.name, $event )"
							/>
						</template>
					</component>
				</template>

				<HoneypotField
					v-if="form.config.honeypot.enabled"
					:slug="form.slug"
					:field-name="form.config.honeypot.field_name"
				/>

				<!-- Inline layout: the submit button is the last item in the field row -->
				<Button
					v-if="isInline"
					type="submit"
					color="primary"
					:disabled="isSubmitting"
					:class="['shrink-0 self-end', submitClassName]"
				>
					{{ isSubmitting ? 'Submitting...' : form.submit_button_text }}
				</Button>
			</div>
			<div v-if="!isInline">
				<Button type="submit" color="primary" :disabled="isSubmitting" :class="submitClassName">
					{{ isSubmitting ? 'Submitting...' : form.submit_button_text }}
				</Button>
			</div>
		</div>
	</form>
</template>
