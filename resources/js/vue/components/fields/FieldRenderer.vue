<!--
  Field renderer component.

  Maps a form field definition to the appropriate Vue component
  based on its field type. An email field that asks for the address twice
  renders `EmailConfirmationField`. Name and address fields, and any type with
  sub-inputs that has no component of its own, render as a composite field;
  consent and CAPTCHA fields have components of their own (since 1.6.0).

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
import RatingField from './RatingField.vue';
import CompositeField from './CompositeField.vue';
import EmailConfirmationField from './EmailConfirmationField.vue';
import ConsentField from './ConsentField.vue';
import CaptchaField from './CaptchaField.vue';
import { isCompositeField } from '../../../shared/composite';
import { WIDTH_CLASSES } from '../../../shared/fieldSettings';
import { requiresEmailConfirmation } from '../../../shared/fieldSettings';

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
	/** Text typed into the field's "Other" choice (since 1.6.0). */
	otherValue?: string;
	/** An email field's confirmation, when it asks for the address twice (since 1.6.0). */
	confirmValue?: string;
	/** A composite field's first error per sub-input, keyed by sub-input (since 1.6.0). */
	subErrors?: Record<string, string>;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
	'update:otherValue': [text: string];
	'update:confirmValue': [text: string];
	'fileChange': [file: File | File[]];
}>();

/** Field types handled by each component group. */
const TEXT_TYPES = new Set( ['text', 'email', 'phone', 'number', 'url', 'textarea', 'hidden', 'time'] );
const CHOICE_TYPES = new Set( ['select', 'radio', 'checkbox', 'toggle', 'checkbox_group', 'select_multiple'] );
const ADVANCED_TYPES = new Set( ['file', 'date'] );
const RATING_TYPES = new Set( ['rating'] );
const LAYOUT_TYPES = new Set( ['heading', 'paragraph', 'divider', 'html'] );
const COMPOSITE_TYPES = new Set( ['name', 'address'] );
const CONSENT_TYPES = new Set( ['consent'] );
const CAPTCHA_TYPES = new Set( ['captcha'] );

/**
 * Returns the wrapper class for a field in an inline row.
 *
 * A full-width field grows to fill the row but may shrink to 12rem, or to
 * the row width when that is narrower, so the submit button can share its
 * line and wraps below it only when the row is too narrow. A hidden input's
 * wrapper is not displayed, so it can't take up a slot in the row. Other
 * widths keep their stacked classes.
 *
 * @since 1.5.2
 */
function getInlineWidthClass( field: FormField ): string {
	if ( field.type === 'hidden' ) {
		return 'hidden';
	}

	const width = WIDTH_CLASSES[field.width] ? field.width : 'full';

	return width === 'full' ? 'flex-1 min-w-[min(12rem,100%)]' : WIDTH_CLASSES[width];
}

const widthClass = computed( () => (
	props.layout === 'inline'
		? getInlineWidthClass( props.field )
		: ( WIDTH_CLASSES[props.field.width] ?? WIDTH_CLASSES.full )
) );

const componentGroup = computed( () => {
	if ( requiresEmailConfirmation( props.field ) ) {
		return 'email_confirmation';
	}

	if ( TEXT_TYPES.has( props.field.type ) ) {
		return 'text';
	}

	if ( CHOICE_TYPES.has( props.field.type ) ) {
		return 'choice';
	}

	if ( ADVANCED_TYPES.has( props.field.type ) ) {
		return 'advanced';
	}

	if ( RATING_TYPES.has( props.field.type ) ) {
		return 'rating';
	}

	if ( LAYOUT_TYPES.has( props.field.type ) ) {
		return 'layout';
	}

	if ( CONSENT_TYPES.has( props.field.type ) ) {
		return 'consent';
	}

	if ( CAPTCHA_TYPES.has( props.field.type ) ) {
		return 'captcha';
	}

	if ( COMPOSITE_TYPES.has( props.field.type ) ) {
		return 'composite';
	}

	if ( isCompositeField( props.field ) ) {
		return 'composite';
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
		<EmailConfirmationField
			v-else-if="componentGroup === 'email_confirmation'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			:confirm-value="confirmValue"
			@update:model-value="emit( 'update:modelValue', $event )"
			@update:confirm-value="emit( 'update:confirmValue', $event )"
		/>
		<ChoiceField
			v-else-if="componentGroup === 'choice'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			:other-value="otherValue"
			@update:model-value="emit( 'update:modelValue', $event )"
			@update:other-value="emit( 'update:otherValue', $event )"
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
		<RatingField
			v-else-if="componentGroup === 'rating'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
		<LayoutField
			v-else-if="componentGroup === 'layout'"
			:field="field"
		/>
		<CompositeField
			v-else-if="componentGroup === 'composite'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:sub-errors="subErrors"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
		<ConsentField
			v-else-if="componentGroup === 'consent'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
		<CaptchaField
			v-else-if="componentGroup === 'captcha'"
			:field="field"
			:model-value="modelValue"
			:error="error"
			:display-config="displayConfig"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</div>
</template>
