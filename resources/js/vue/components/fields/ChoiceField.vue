<!--
  Choice-based field components wrapper.

  Renders select, radio, checkbox, checkbox_group, and select_multiple
  fields using @artisanpack-ui/vue components.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox, Radio, Select } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig, FieldOption, OptionsFieldConfig } from '../../../types/artisanpack-forms';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
}>();

const options = computed<FieldOption[]>( () => {
	const config = props.field.field_config as OptionsFieldConfig | null;

	return props.field.options ?? config?.options ?? [];
} );

const showLabel = computed( () => props.displayConfig.label_position !== 'hidden' );
const labelText = computed( () => props.field.label ?? undefined );
const ariaLabel = computed( () =>
	props.displayConfig.label_position === 'hidden'
		? ( props.field.label ?? props.field.name )
		: undefined,
);

const stringValue = computed( () => String( props.modelValue ?? '' ) );

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

const selectedArray = computed( () =>
	Array.isArray( props.modelValue ) ? props.modelValue.map( String ) : [],
);

const hintId = computed( () =>
	props.field.help_text && !props.error ? `${props.field.name}-hint` : undefined,
);
const errorId = computed( () =>
	props.error ? `${props.field.name}-error` : undefined,
);
const describedBy = computed( () =>
	[hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined,
);
const legendId = computed( () =>
	props.field.label ? `${props.field.name}-label` : undefined,
);

function handleCheckboxGroupChange( optionValue: string, checked: boolean ): void {
	const updated = checked
		? [...selectedArray.value, optionValue]
		: selectedArray.value.filter( ( v ) => v !== optionValue );

	emit( 'update:modelValue', updated );
}

function handleMultiSelectChange( event: Event ): void {
	const target = event.target as HTMLSelectElement;
	const selected = Array.from( target.selectedOptions, ( opt ) => opt.value );
	emit( 'update:modelValue', selected );
}
</script>

<template>
	<!-- Select -->
	<Select
		v-if="field.type === 'select'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? 'Select an option...'"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:options="options"
		option-value="value"
		option-label="label"
		:inline="displayConfig.label_position === 'beside'"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Radio -->
	<Radio
		v-else-if="field.type === 'radio'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		:model-value="stringValue"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:options="options"
		option-value="value"
		option-label="label"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Checkbox -->
	<Checkbox
		v-else-if="field.type === 'checkbox'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		:model-value="isChecked"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		right
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Checkbox Group -->
	<fieldset
		v-else-if="field.type === 'checkbox_group'"
		class="fieldset"
		:aria-describedby="describedBy"
	>
		<legend
			v-if="field.label"
			:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
		>
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1">*</span>
		</legend>
		<div :class="['flex flex-col gap-2', field.css_classes ?? '']">
			<Checkbox
				v-for="option in options"
				:key="option.value"
				:label="option.label"
				:name="`${field.name}[]`"
				:model-value="selectedArray.includes( option.value )"
				right
				@update:model-value="handleCheckboxGroupChange( option.value, $event as boolean )"
			/>
		</div>
		<p v-if="field.help_text && !error" :id="hintId" class="fieldset-label">
			{{ field.help_text }}
		</p>
		<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
			{{ error }}
		</p>
	</fieldset>

	<!-- Select Multiple -->
	<fieldset v-else-if="field.type === 'select_multiple'" class="fieldset">
		<legend
			v-if="field.label"
			:id="legendId"
			:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
		>
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1">*</span>
		</legend>
		<select
			:name="field.name"
			multiple
			:value="selectedArray"
			:required="field.is_required"
			:class="['select select-bordered w-full min-h-32', field.css_classes ?? '', error ? 'select-error' : '']"
			:aria-invalid="error ? true : undefined"
			:aria-labelledby="legendId"
			:aria-describedby="describedBy"
			@change="handleMultiSelectChange"
		>
			<option
				v-for="option in options"
				:key="option.value"
				:value="option.value"
			>
				{{ option.label }}
			</option>
		</select>
		<p v-if="field.help_text && !error" :id="hintId" class="fieldset-label">
			{{ field.help_text }}
		</p>
		<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
			{{ error }}
		</p>
	</fieldset>
</template>
