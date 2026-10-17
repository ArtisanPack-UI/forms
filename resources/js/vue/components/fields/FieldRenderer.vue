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
import type { FormField, DisplayConfig, FormLayout } from '../../../types/artisanpack-forms';

import TextField from './TextField.vue';
import ChoiceField from './ChoiceField.vue';
import AdvancedField from './AdvancedField.vue';
import LayoutField from './LayoutField.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
	/**
	 * The layout of the row the field sits in. In `inline` mode a full-width
	 * field shrinks to share its row with the submit button, and a hidden
	 * field's wrapper is removed from the flow. Defaults to `stacked`.
	 *
	 * @since 1.5.2
	 */
	layout?: FormLayout;
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

/**
 * Returns the wrapper class for a field in an inline row.
 *
 * A full-width field grows to fill the row but may shrink to 12rem, so the
 * submit button can share its line and wraps below it only when the row is
 * too narrow. A hidden input's wrapper is not displayed, so it can't take up
 * a slot in the row. Other widths keep their stacked classes.
 *
 * @since 1.5.2
 */
function getInlineWidthClass( field: FormField ): string {
	if ( field.type === 'hidden' ) {
		return 'hidden';
	}

	const width = WIDTH_CLASSES[field.width] ? field.width : 'full';

	return width === 'full' ? 'flex-1 min-w-[12rem]' : WIDTH_CLASSES[width];
}

const widthClass = computed( () => (
	props.layout === 'inline'
		? getInlineWidthClass( props.field )
		: ( WIDTH_CLASSES[props.field.width] ?? WIDTH_CLASSES.full )
) );

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
