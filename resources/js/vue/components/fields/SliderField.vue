<!--
  Slider field component.

  Renders a native range input with the chosen value shown beside it and
  given to screen readers as `aria-valuetext` ("$40"), mirroring the Blade
  `slider` partial (since 1.6.0; see `shared/slider.ts`).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { formatSliderValue, getInitialSliderValue, getSliderSettings } from '../../../shared/slider';
import FieldLayout from './FieldLayout.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: string];
}>();

const settings = computed( () => getSliderSettings( props.field ) );
const current = computed( () => getInitialSliderValue( props.field, props.modelValue ) );
const valueText = computed( () => formatSliderValue( props.field, current.value ) );
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

const inputId = computed( () => ( layout.value.custom ? layout.value.inputId : `field-${props.field.name}` ) );
const labelId = computed( () => {
	if ( !props.field.label ) {
		return undefined;
	}

	return layout.value.custom ? layout.value.labelId : `${props.field.name}-label`;
} );
const hintId = computed( () => {
	if ( layout.value.custom ) {
		return props.field.help_text ? layout.value.descriptionId : undefined;
	}

	return props.field.help_text && !props.error ? `${props.field.name}-hint` : undefined;
} );
const errorId = computed( () => ( props.error ? `${props.field.name}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );
</script>

<template>
	<FieldLayout :field="field" :layout="layout" group>
		<div class="fieldset">
			<span
				v-if="!layout.custom && field.label"
				:id="labelId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</span>

			<div class="flex items-center gap-4">
				<input
					:id="inputId"
					type="range"
					:name="field.name"
					:min="settings.min"
					:max="settings.max"
					:step="settings.step"
					:value="current"
					:class="['range range-primary flex-1', field.css_classes ?? '']"
					:aria-labelledby="labelId"
					:aria-label="labelId ? undefined : field.name"
					:aria-valuetext="valueText"
					:aria-describedby="describedBy"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-readonly="readOnly ? 'true' : undefined"
					:aria-invalid="error ? 'true' : undefined"
					:disabled="readOnly"
					@input="emit( 'update:modelValue', ( $event.target as HTMLInputElement ).value )"
				/>
				<output
					v-if="settings.show_value"
					:for="inputId"
					class="min-w-16 text-right font-medium tabular-nums"
					aria-hidden="true"
				>{{ valueText }}</output>
			</div>
			<div class="mt-1 flex justify-between text-xs text-base-content/60" aria-hidden="true">
				<span>{{ formatSliderValue( field, settings.min ) }}</span>
				<span>{{ formatSliderValue( field, settings.max ) }}</span>
			</div>

			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>
</template>
