<!--
  Calculation field component.

  A read-only input showing the result of the field's formula, worked out
  from the other values as the visitor types, mirroring the Blade
  `calculation` partial (since 1.6.0; see `shared/calculation.ts` and
  `useForm`). It stays focusable and labelled, so screen readers can read the
  result. The server works it out again on submit.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout } from '../../../shared/fieldSettings';
import FieldLayout from './FieldLayout.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const result = computed( () => ( typeof props.modelValue === 'string' || typeof props.modelValue === 'number' ? String( props.modelValue ) : '' ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

const inputId = computed( () => ( layout.value.custom ? layout.value.inputId : `field-${props.field.name}` ) );
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
	<FieldLayout :field="field" :layout="layout">
		<div class="fieldset">
			<label
				v-if="!layout.custom && field.label"
				:for="inputId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
			</label>

			<input
				:id="inputId"
				type="text"
				:name="field.name"
				:value="result"
				readonly
				aria-readonly="true"
				inputmode="decimal"
				:data-forms-calculation="field.name"
				:class="['input input-bordered w-full bg-base-200 font-medium tabular-nums', field.css_classes ?? '']"
				:aria-label="field.label ? undefined : field.name"
				:aria-describedby="describedBy"
			/>

			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>
</template>
