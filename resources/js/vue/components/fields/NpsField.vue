<!--
  Net Promoter Score field component.

  Renders the scores 0 to 10 as a radio group shown as buttons, with the low
  and high labels under the ends, mirroring the Blade `nps` partial (since
  1.6.0; see `shared/nps.ts`). Each radio is visually hidden inside its
  button, so arrow keys still move between scores; 0 and 10 are named with
  their end labels ("0, Not likely").

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { getNpsLabels, getNpsScoreLabel, NPS_MAX_SCORE, NPS_MIN_SCORE, NPS_SCORES } from '../../../shared/nps';
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

/** Focus ring shown on a button while its hidden radio has keyboard focus. */
const SCORE_CLASSES = 'btn btn-sm btn-outline w-full tabular-nums peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-content peer-disabled:cursor-not-allowed peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary';

const labels = computed( () => getNpsLabels( props.field ) );
const readOnly = computed( () => isReadOnlyField( props.field ) );
const selected = computed( () => ( props.modelValue === null || props.modelValue === undefined || props.modelValue === '' ? undefined : String( props.modelValue ) ) );
const endsId = computed( () => `field-${props.field.name}-ends` );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

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
const describedBy = computed( () => [endsId.value, hintId.value, errorId.value].filter( Boolean ).join( ' ' ) );
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

			<div :class="['flex flex-col gap-1', field.css_classes ?? '']">
				<div
					role="radiogroup"
					class="grid grid-cols-6 gap-1 sm:grid-cols-11"
					:aria-labelledby="labelId"
					:aria-label="labelId ? undefined : field.name"
					:aria-describedby="describedBy"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-readonly="readOnly ? 'true' : undefined"
					:aria-invalid="error ? 'true' : undefined"
				>
					<label v-for="score in NPS_SCORES" :key="score" class="cursor-pointer">
						<input
							:id="`field-${field.name}-${score}`"
							type="radio"
							:name="field.name"
							:value="String( score )"
							class="peer sr-only"
							:aria-label="getNpsScoreLabel( field, score )"
							:checked="selected === String( score )"
							:disabled="readOnly"
							@change="emit( 'update:modelValue', String( score ) )"
						/>
						<span :class="SCORE_CLASSES" aria-hidden="true">{{ score }}</span>
					</label>
				</div>
				<div :id="endsId" class="flex justify-between gap-4 text-xs text-base-content/70">
					<span>{{ t( ':score = :label', { score: NPS_MIN_SCORE, label: labels.low } ) }}</span>
					<span class="text-right">{{ t( ':score = :label', { score: NPS_MAX_SCORE, label: labels.high } ) }}</span>
				</div>
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
