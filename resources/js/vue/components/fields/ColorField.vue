<!--
  Color field component.

  The browser's color picker joined to a text input holding the hex code,
  mirroring the Blade `color` partial (since 1.6.0; see `shared/color.ts`).
  The picker shows the chosen color as its swatch, and the text input is
  labelled by the field label, so the color can be typed as well as picked.
  A typed color is normalized when the input loses focus (`F80` becomes
  `#ff8800`).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getColorPickerValue, normalizeColor } from '../../../shared/color';
import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
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

const text = computed( () => ( typeof props.modelValue === 'string' ? props.modelValue : '' ) );
const readOnly = computed( () => isReadOnlyField( props.field ) );

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
const pickerLabel = computed( () => t( ':field color picker', { field: props.field.label || props.field.name } ) );
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
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</label>

			<div class="join w-full">
				<input
					:id="`${inputId}-picker`"
					type="color"
					:class="['input join-item h-auto w-14 shrink-0 cursor-pointer p-1', error ? 'input-error' : '']"
					:value="getColorPickerValue( text )"
					:aria-label="pickerLabel"
					:disabled="readOnly"
					@input="emit( 'update:modelValue', ( $event.target as HTMLInputElement ).value )"
				/>
				<input
					:id="inputId"
					type="text"
					:name="field.name"
					:value="text"
					:placeholder="field.placeholder || '#rrggbb'"
					maxlength="9"
					spellcheck="false"
					autocomplete="off"
					:class="['input input-bordered join-item w-full font-mono', error ? 'input-error' : '', field.css_classes ?? '']"
					:aria-label="field.label ? undefined : field.name"
					:aria-describedby="describedBy"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-readonly="readOnly ? 'true' : undefined"
					:aria-invalid="error ? 'true' : undefined"
					:required="field.is_required"
					:readonly="readOnly"
					@input="emit( 'update:modelValue', ( $event.target as HTMLInputElement ).value )"
					@blur="emit( 'update:modelValue', normalizeColor( ( $event.target as HTMLInputElement ).value ) )"
				/>
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
