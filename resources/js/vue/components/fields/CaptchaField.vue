<!--
  CAPTCHA field component.

  Loads the provider's script and renders its widget (see
  `createCaptchaController()`), storing each token as the field's value. An
  invisible widget, and reCAPTCHA v3, fetch the token when the form is
  submitted, through the form's submit hooks (see `useSubmitHooks()`); a
  spent token is reset when the submission fails. Nothing renders while the
  provider has no keys. Mirrors the React `CaptchaField` and the Blade
  `captcha` partial with its `formsCaptcha` Alpine component.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { createCaptchaController, hasCaptchaWidget, runsOnSubmit } from '../../../shared/captcha';
import { getFieldLayout, getLayoutDescribedBy } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { useSubmitHooks } from '../../composables/submitHooks';
import FieldLayout from './FieldLayout.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [token: string];
}>();

const submitHooks = useSubmitHooks();

/** The element the provider renders its widget into. */
const widgetElement = ref<HTMLElement | null>( null );

/** Whether the provider's script or widget could not be loaded. */
const failed = ref( false );

/** Whether the field has a provider set up; nothing renders otherwise. */
const hasWidget = computed( () => hasCaptchaWidget( props.field ) );

/** Where the description goes (see `FieldLayout.vue`); the label is rendered here. */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** The label is visually hidden for an invisible widget, as nothing is shown. */
const hiddenLabel = computed( () =>
	layout.value.label === 'hidden'
	|| ( !layout.value.custom && props.displayConfig.label_position === 'hidden' )
	|| ( hasCaptchaWidget( props.field ) && props.field.captcha.size === 'invisible' ),
);

const labelId = computed( () => ( props.field.label ? `label-${props.field.name}` : undefined ) );
const hintId = computed( () => ( !layout.value.custom && props.field.help_text ? `${props.field.name}-hint` : undefined ) );
const errorId = computed( () => ( props.error ? `${props.field.name}-error` : undefined ) );
const describedBy = computed( () => [
	layout.value.custom ? getLayoutDescribedBy( props.field, layout.value ) : hintId.value,
	errorId.value,
].filter( Boolean ).join( ' ' ) || undefined );

let unregister: ( () => void ) | null = null;

onMounted( () => {
	const field = props.field;

	if ( !hasCaptchaWidget( field ) || !widgetElement.value ) {
		return;
	}

	const widget = field.captcha;
	const captcha = createCaptchaController( widget, ( token ) => emit( 'update:modelValue', token ) );

	captcha.mount( widgetElement.value ).catch( () => {
		failed.value = true;
	} );

	unregister = submitHooks?.register( field.name, {
		beforeSubmit: runsOnSubmit( widget )
			? async () => ( { [field.name]: await captcha.execute() } )
			: undefined,
		// The server spends the token only when every other field validated,
		// so a 422 for other fields keeps it.
		afterSubmit: ( { ok, errors } ) => {
			if ( !ok && ( errors[field.name] || Object.keys( errors ).length === 0 ) ) {
				captcha.reset();
			}
		},
	} ) ?? null;
} );

onBeforeUnmount( () => {
	unregister?.();
	unregister = null;
} );
</script>

<template>
	<FieldLayout v-if="hasWidget" :field="field" :layout="layout" :render-label="false">
		<div
			:id="`field-${field.name}`"
			role="group"
			:aria-labelledby="labelId"
			:aria-describedby="describedBy"
			:data-forms-captcha="field.captcha?.provider"
		>
			<p v-if="field.label" :id="labelId" :class="['label-text mb-2', hiddenLabel ? 'sr-only' : '']">
				{{ field.label }}
			</p>

			<div ref="widgetElement" />

			<p v-if="failed" class="text-sm text-error" role="alert">
				{{ t( 'The verification check could not be loaded. Please reload the page and try again.' ) }}
			</p>

			<p v-if="hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>
</template>
