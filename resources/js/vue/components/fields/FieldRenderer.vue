<!--
  Field renderer component.

  Maps a form field definition to the appropriate Vue component
  based on its field type.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';

import TextField from './TextField.vue';
import ChoiceField from './ChoiceField.vue';
import AdvancedField from './AdvancedField.vue';
import LayoutField from './LayoutField.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
	'fileChange': [file: File | File[]];
}>();

/** Field types handled by each component group. */
const TEXT_TYPES = new Set( ['text', 'email', 'phone', 'number', 'url', 'textarea', 'hidden', 'time'] );
const CHOICE_TYPES = new Set( ['select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple'] );
const ADVANCED_TYPES = new Set( ['file', 'date'] );
const LAYOUT_TYPES = new Set( ['heading', 'paragraph', 'divider', 'html'] );

/** CSS class map for field width. */
const WIDTH_CLASSES: Record<string, string> = {
	full: 'w-full',
	half: 'w-full md:w-1/2',
	third: 'w-full md:w-1/3',
	'two-thirds': 'w-full md:w-2/3',
};

const widthClass = computed( () => WIDTH_CLASSES[props.field.width] ?? WIDTH_CLASSES.full );

const componentGroup = computed( () => {
	if ( TEXT_TYPES.has( props.field.type ) ) {
		return 'text';
	}

	if ( CHOICE_TYPES.has( props.field.type ) ) {
		return 'choice';
	}

	if ( ADVANCED_TYPES.has( props.field.type ) ) {
		return 'advanced';
	}

	if ( LAYOUT_TYPES.has( props.field.type ) ) {
		return 'layout';
	}

	return null;
} );
</script>

<template>
	<div :class="widthClass">
		<TextField
			v-if="componentGroup === 'text'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
		<ChoiceField
			v-else-if="componentGroup === 'choice'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
		<AdvancedField
			v-else-if="componentGroup === 'advanced'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
			@file-change="emit( 'fileChange', $event )"
		/>
		<LayoutField
			v-else-if="componentGroup === 'layout'"
			:field="field"
		/>
	</div>
</template>
