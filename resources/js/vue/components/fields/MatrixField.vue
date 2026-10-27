<!--
  Likert / matrix field component.

  As a grid: a table of statements against the scale, in a `<fieldset>`
  whose `<legend>` is the field label. Each row's radios share a name, so
  arrow keys move along the row, and each radio is named by its row and
  column headers with `aria-labelledby`. A row's error is shown under its
  statement. As a single scale: one radio group labelled by the field label.
  Mirrors the Blade `matrix` partial (since 1.6.0; see `shared/matrix.ts`).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { getMatrixColumns, getMatrixRows, isSingleScale, toMatrixAnswers } from '../../../shared/matrix';
import FieldLayout from './FieldLayout.vue';
import { fieldIdKey } from '../../../shared/domIds';

const props = withDefaults( defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	/** Each row's first error, keyed by row. */
	subErrors?: Record<string, string>;
	displayConfig: DisplayConfig;
}>(), {
	error: undefined,
	subErrors: () => ( {} ),
} );

const emit = defineEmits<{
	'update:modelValue': [value: Record<string, string> | string];
}>();

const single = computed( () => isSingleScale( props.field ) );
const columns = computed( () => getMatrixColumns( props.field ) );
const rows = computed( () => getMatrixRows( props.field ) );
const answers = computed( () => toMatrixAnswers( props.field, props.modelValue ) );
const selected = computed( () => ( typeof props.modelValue === 'string' ? props.modelValue : '' ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

const hiddenLabel = computed( () => layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ) );
const rowErrorCount = computed( () => Object.keys( props.subErrors ).length );
const hasErrors = computed( () => Boolean( props.error ) || rowErrorCount.value > 0 );

/** The single scale's label ID, as `RatingField.vue` works it out. */
const labelId = computed( () => {
	if ( !props.field.label ) {
		return undefined;
	}

	return layout.value.custom ? layout.value.labelId : `${fieldIdKey( props.field )}-label`;
} );
const hintId = computed( () => {
	if ( !single.value ) {
		return props.field.help_text ? `${fieldIdKey( props.field )}-hint` : undefined;
	}

	if ( layout.value.custom ) {
		return props.field.help_text ? layout.value.descriptionId : undefined;
	}

	return props.field.help_text && !props.error ? `${fieldIdKey( props.field )}-hint` : undefined;
} );
const errorId = computed( () => ( hasErrors.value ? `${fieldIdKey( props.field )}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );

const summary = computed( () => props.error || ( rowErrorCount.value === 1
	? t( 'Please answer :count highlighted row in :field.', { count: rowErrorCount.value, field: props.field.label ?? props.field.name } )
	: t( 'Please answer :count highlighted rows in :field.', { count: rowErrorCount.value, field: props.field.label ?? props.field.name } ) ) );

/** The ID of a row's statement. */
function rowId( key: string ): string {
	return `field-${fieldIdKey( props.field )}-row-${key}`;
}

/** Store a row's answer. */
function answer( key: string, value: string ): void {
	emit( 'update:modelValue', { ...answers.value, [key]: value } );
}
</script>

<template>
	<FieldLayout v-if="single" :field="field" :layout="layout" group>
		<div class="fieldset">
			<span
				v-if="!layout.custom && field.label"
				:id="labelId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</span>

			<div
				role="radiogroup"
				:class="['flex flex-wrap gap-x-6 gap-y-2', field.css_classes ?? '']"
				:aria-labelledby="labelId"
				:aria-label="labelId ? undefined : field.name"
				:aria-describedby="describedBy"
				:aria-required="field.is_required ? 'true' : undefined"
				:aria-invalid="error ? 'true' : undefined"
			>
				<label v-for="( column, index ) in columns" :key="column.value" class="label cursor-pointer justify-start gap-2">
					<input
						:id="`field-${fieldIdKey( field )}-${index}`"
						type="radio"
						:name="field.name"
						:value="column.value"
						class="radio radio-primary"
						:checked="selected === column.value"
						@change="emit( 'update:modelValue', column.value )"
					/>
					<span class="label-text">{{ column.label }}</span>
				</label>
			</div>

			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>

	<fieldset
		v-else
		:id="`field-${fieldIdKey( field )}`"
		:class="['fieldset', field.css_classes ?? '']"
		:aria-describedby="describedBy"
	>
		<legend v-if="field.label" :class="['fieldset-legend', hiddenLabel ? 'sr-only' : '']">
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
		</legend>

		<p v-if="hintId && layout.description === 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<div class="overflow-x-auto">
			<table class="table table-sm">
				<thead>
					<tr>
						<td><span class="sr-only">{{ t( 'Statement' ) }}</span></td>
						<th
							v-for="( column, index ) in columns"
							:id="`field-${fieldIdKey( field )}-col-${index}`"
							:key="column.value"
							scope="col"
							class="text-center align-bottom font-medium"
						>
							{{ column.label }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in rows" :key="row.key">
						<th scope="row" class="min-w-40 font-normal">
							<span :id="rowId( row.key )">{{ row.label }}</span>
							<span v-if="subErrors[row.key]" :id="`${rowId( row.key )}-error`" class="block text-xs text-error">{{ subErrors[row.key] }}</span>
						</th>
						<td v-for="( column, index ) in columns" :key="column.value" class="text-center">
							<input
								:id="`${rowId( row.key )}-${index}`"
								type="radio"
								:name="`${field.name}[${row.key}]`"
								:value="column.value"
								class="radio radio-primary"
								:aria-labelledby="`${rowId( row.key )} field-${fieldIdKey( field )}-col-${index}`"
								:aria-invalid="subErrors[row.key] ? 'true' : undefined"
								:aria-describedby="subErrors[row.key] ? `${rowId( row.key )}-error` : undefined"
								:checked="answers[row.key] === column.value"
								@change="answer( row.key, column.value )"
							/>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<p v-if="hintId && layout.description !== 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<p v-if="hasErrors" :id="errorId" class="fieldset-label text-error" role="alert">{{ summary }}</p>
	</fieldset>
</template>
