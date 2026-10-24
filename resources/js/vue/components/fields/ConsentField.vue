<!--
  Consent field component.

  The field label as a heading, a checkbox with the consent text beside it,
  and the consent description (links allowed) below. The description is
  sanitized with kses by the server, and is part of the checkbox's
  description, so screen readers announce what is being agreed to. Mirrors
  the React `ConsentField` and the Blade `consent` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout, getLayoutDescribedBy } from '../../../shared/fieldSettings';
import FieldLayout from './FieldLayout.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: boolean];
}>();

/** Where the description goes (see `FieldLayout.vue`); the label is rendered here. */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** Whether the heading-style label is visually hidden. */
const hiddenLabel = computed( () =>
	layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ),
);

const isChecked = computed( () => {
	const v = props.modelValue;

	if ( typeof v === 'boolean' ) {
		return v;
	}

	if ( typeof v === 'number' ) {
		return v === 1;
	}

	if ( typeof v === 'string' ) {
		return ['true', '1', 'yes', 'on'].includes( v.toLowerCase() );
	}

	return false;
} );

/** The consent text beside the checkbox. */
const consentText = computed( () => props.field.consent?.text ?? '' );

/** The server-sanitized consent description HTML. */
const consentDescription = computed( () => props.field.consent?.description ?? '' );

const labelId = computed( () => `label-${props.field.name}` );
const descriptionId = computed( () => ( consentDescription.value !== '' ? `consent-description-${props.field.name}` : undefined ) );
const hintId = computed( () => ( !layout.value.custom && props.field.help_text ? `${props.field.name}-hint` : undefined ) );
const errorId = computed( () => ( props.error ? `${props.field.name}-error` : undefined ) );

/** The consent description, the help text and the error, for the checkbox. */
const describedBy = computed( () => [
	descriptionId.value,
	layout.value.custom ? getLayoutDescribedBy( props.field, layout.value ) : hintId.value,
	errorId.value,
].filter( Boolean ).join( ' ' ) || undefined );
</script>

<template>
	<FieldLayout :field="field" :layout="layout" :render-label="false">
		<div class="flex flex-col gap-2">
			<p v-if="field.label" :id="labelId" :class="['label-text font-medium', hiddenLabel ? 'sr-only' : '']">
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
			</p>

			<label :for="`field-${field.name}`" class="label cursor-pointer justify-start gap-3">
				<input
					:id="`field-${field.name}`"
					type="checkbox"
					:name="field.name"
					:class="['checkbox checkbox-primary', field.css_classes ?? '']"
					:checked="isChecked"
					:required="field.is_required"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-invalid="error ? true : undefined"
					:aria-describedby="describedBy"
					@change="emit( 'update:modelValue', ( $event.target as HTMLInputElement ).checked )"
				/>
				<span class="label-text whitespace-normal">{{ consentText }}</span>
			</label>

			<!-- Sanitized with kses by the server (`ConsentFieldType::description()`). -->
			<div
				v-if="descriptionId"
				:id="descriptionId"
				class="prose prose-sm max-w-none text-base-content/70"
				v-html="consentDescription"
			/>

			<p v-if="hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>
</template>
