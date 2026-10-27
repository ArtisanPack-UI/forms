<!--
  Composite (multi-input) field component.

  Renders a field made of several inputs stored together, such as a name or
  an address: a `<fieldset>` whose `<legend>` is the field label, with one
  labelled input per enabled sub-input carrying its `autocomplete` token and
  its own error, and a field-level summary when any part is invalid.
  Sub-labels sit below or above their input, or are visually hidden. A
  `select` sub-input renders its options and a `wide` one spans the row.
  Each input carries `data-forms-part="{key}"` and the fieldset
  `data-forms-composite="{type}"`, so address autocomplete providers can
  find them. Mirrors the React `CompositeField` and the Blade `composite`
  partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import {
	getEnabledSubInputs,
	getRequiredSubInputKeys,
	getSubInputId,
	getSubLabelPlacement,
	SUB_INPUT_MAX_LENGTH,
	toCompositeValue,
} from '../../../shared/composite';
import { getDateLimits } from '../../../shared/dateConstraints';
import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { fieldIdKey } from '../../../shared/domIds';

const props = withDefaults( defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	/** The first error per sub-input, keyed by sub-input. */
	subErrors?: Record<string, string>;
	displayConfig: DisplayConfig;
}>(), {
	error: undefined,
	subErrors: () => ( {} ),
} );

const emit = defineEmits<{
	'update:modelValue': [value: Record<string, string>];
}>();

const parts = computed( () => toCompositeValue( props.modelValue ) );
const subInputs = computed( () => getEnabledSubInputs( props.field ) );
const dateLimits = computed( () => getDateLimits( props.field ) );
const required = computed( () => new Set( getRequiredSubInputKeys( props.field ) ) );
const subLabelPlacement = computed( () => getSubLabelPlacement( props.field ) );
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );
const hiddenLabel = computed( () =>
	layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ),
);
const errorCount = computed( () => subInputs.value.filter( ( subInput ) => props.subErrors[subInput.key] ).length );
const hasErrors = computed( () => Boolean( props.error ) || errorCount.value > 0 );
const hintId = computed( () => ( props.field.help_text ? `${fieldIdKey( props.field )}-hint` : undefined ) );
const errorId = computed( () => ( hasErrors.value ? `${fieldIdKey( props.field )}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );
const summary = computed( () => {
	if ( props.error ) {
		return props.error;
	}

	const replacements = { count: errorCount.value, field: props.field.label ?? props.field.name };

	return errorCount.value === 1
		? t( 'Please fix :count highlighted part of :field.', replacements )
		: t( 'Please fix :count highlighted parts of :field.', replacements );
} );

function update( key: string, text: string ): void {
	emit( 'update:modelValue', { ...parts.value, [key]: text } );
}

function subLabelClass(): string {
	if ( subLabelPlacement.value === 'below' ) {
		return 'fieldset-label text-xs';
	}

	return subLabelPlacement.value === 'hidden' ? 'label text-sm sr-only' : 'label text-sm';
}
</script>

<template>
	<fieldset
		:id="`field-${fieldIdKey( field )}`"
		:class="['fieldset', field.css_classes ?? '']"
		:data-forms-composite="field.type"
		:aria-describedby="describedBy"
	>
		<legend v-if="field.label" :class="['fieldset-legend', hiddenLabel ? 'sr-only' : '']">
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
		</legend>

		<p v-if="hintId && layout.description === 'above'" :id="hintId" class="fieldset-label">
			{{ field.help_text }}
		</p>

		<div :class="['grid grid-cols-1 gap-3', subInputs.length > 1 ? 'sm:grid-cols-2' : '']">
			<div
				v-for="subInput in subInputs"
				:key="subInput.key"
				:class="['flex flex-col gap-1', subInput.wide && subInputs.length > 1 ? 'sm:col-span-2' : '']"
			>
				<label v-if="subLabelPlacement !== 'below'" :for="getSubInputId( field, subInput.key )" :class="subLabelClass()">
					{{ subInput.label }}
					<span v-if="required.has( subInput.key )" class="text-error ml-1" aria-hidden="true">*</span>
				</label>
				<select
					v-if="subInput.type === 'select'"
					:id="getSubInputId( field, subInput.key )"
					:name="`${field.name}[${subInput.key}]`"
					:data-forms-part="subInput.key"
					:class="['select select-bordered w-full', subErrors[subInput.key] ? 'select-error' : '']"
					:value="parts[subInput.key] ?? ''"
					:autocomplete="subInput.autocomplete ?? undefined"
					:required="required.has( subInput.key )"
					:aria-required="required.has( subInput.key ) ? 'true' : undefined"
					:aria-invalid="subErrors[subInput.key] ? true : undefined"
					:aria-describedby="subErrors[subInput.key] ? `${getSubInputId( field, subInput.key )}-error` : undefined"
					@change="update( subInput.key, ( $event.target as HTMLSelectElement ).value )"
				>
					<option value="">{{ subInput.placeholder || t( 'Select…' ) }}</option>
					<option v-for="option in subInput.options ?? []" :key="option.value" :value="option.value">
						{{ option.label }}
					</option>
				</select>
				<input
					v-else
					:id="getSubInputId( field, subInput.key )"
					:type="subInput.type"
					:name="`${field.name}[${subInput.key}]`"
					:data-forms-part="subInput.key"
					:class="['input input-bordered w-full', subErrors[subInput.key] ? 'input-error' : '']"
					:value="parts[subInput.key] ?? ''"
					:placeholder="subInput.placeholder ?? undefined"
					:autocomplete="subInput.autocomplete ?? undefined"
					:min="subInput.type === 'date' ? dateLimits.min : undefined"
					:max="subInput.type === 'date' ? dateLimits.max : undefined"
					:maxlength="subInput.type === 'number' || subInput.type === 'date' ? undefined : SUB_INPUT_MAX_LENGTH"
					:required="required.has( subInput.key )"
					:aria-required="required.has( subInput.key ) ? 'true' : undefined"
					:aria-invalid="subErrors[subInput.key] ? true : undefined"
					:aria-describedby="subErrors[subInput.key] ? `${getSubInputId( field, subInput.key )}-error` : undefined"
					@input="update( subInput.key, ( $event.target as HTMLInputElement ).value )"
				/>
				<label v-if="subLabelPlacement === 'below'" :for="getSubInputId( field, subInput.key )" :class="subLabelClass()">
					{{ subInput.label }}
					<span v-if="required.has( subInput.key )" class="text-error ml-1" aria-hidden="true">*</span>
				</label>
				<p v-if="subErrors[subInput.key]" :id="`${getSubInputId( field, subInput.key )}-error`" class="text-xs text-error">
					{{ subErrors[subInput.key] }}
				</p>
			</div>
		</div>

		<p v-if="hintId && layout.description !== 'above'" :id="hintId" class="fieldset-label">
			{{ field.help_text }}
		</p>

		<p v-if="hasErrors" :id="errorId" class="fieldset-label text-error" role="alert">
			{{ summary }}
		</p>
	</fieldset>
</template>
