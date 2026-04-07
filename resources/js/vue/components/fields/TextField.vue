<!--
  Text-based field components wrapper.

  Renders text, email, phone, number, url, textarea, hidden,
  and time input fields using @artisanpack-ui/vue components.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { Input, Textarea } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
}>();

const stringValue = computed( () => String( props.modelValue ?? '' ) );

const showLabel = computed( () => props.displayConfig.label_position !== 'hidden' );
const isInline = computed( () => props.displayConfig.label_position === 'beside' );
const labelText = computed( () => props.field.label ?? undefined );
const ariaLabel = computed( () =>
	props.displayConfig.label_position === 'hidden'
		? ( props.field.label ?? props.field.name )
		: undefined,
);
</script>

<template>
	<!-- Text -->
	<Input
		v-if="field.type === 'text'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		type="text"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? undefined"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:minlength="field.validation_rules?.min"
		:maxlength="field.validation_rules?.max"
		:pattern="field.validation_rules?.pattern ?? undefined"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Email -->
	<Input
		v-else-if="field.type === 'email'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		type="email"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? undefined"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Phone -->
	<Input
		v-else-if="field.type === 'phone'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		type="tel"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? undefined"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:pattern="field.validation_rules?.pattern ?? undefined"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Number -->
	<Input
		v-else-if="field.type === 'number'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		type="number"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? undefined"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:min="field.validation_rules?.min"
		:max="field.validation_rules?.max"
		:step="field.validation_rules?.step"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- URL -->
	<Input
		v-else-if="field.type === 'url'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		type="url"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? 'https://'"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Textarea -->
	<Textarea
		v-else-if="field.type === 'textarea'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		:model-value="stringValue"
		:placeholder="field.placeholder ?? undefined"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:minlength="field.validation_rules?.min"
		:maxlength="field.validation_rules?.max"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>

	<!-- Hidden -->
	<input
		v-else-if="field.type === 'hidden'"
		type="hidden"
		:name="field.name"
		:value="stringValue"
		readonly
	/>

	<!-- Time -->
	<Input
		v-else-if="field.type === 'time'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		type="time"
		:model-value="stringValue"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:min="field.validation_rules?.min_time ?? undefined"
		:max="field.validation_rules?.max_time ?? undefined"
		:inline="isInline"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>
</template>
