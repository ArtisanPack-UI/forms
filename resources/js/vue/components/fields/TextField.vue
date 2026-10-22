<!--
  Text-based field components wrapper.

  Renders text, email, phone, number, url, textarea, hidden,
  and time input fields using @artisanpack-ui/vue components. Inputs expose
  the field's `autocomplete` token (WCAG 1.3.5).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { Input, Textarea } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';
import {
	getFieldLayout,
	getInputLabelProps,
	getLayoutDescribedBy,
	getTextareaRows,
	getTimeStep,
	isReadOnlyField,
} from '../../../shared/fieldSettings';
import FieldLayout from './FieldLayout.vue';
import { vAutocomplete } from './autocomplete';
import { vDescribedBy } from './describedBy';

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

/** Read-only fields stay focusable and announced, but cannot be edited. */
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** The input's label, hint and ID props; `aria-describedby` is set by `v-described-by`. */
const labelProps = computed( () => {
	const { 'aria-describedby': _describedBy, ...rest } = getInputLabelProps( props.field, layout.value, props.displayConfig, props.error );

	return rest;
} );

/** The description and error IDs the input references in a custom layout. */
const describedBy = computed( () =>
	layout.value.custom ? getLayoutDescribedBy( props.field, layout.value, props.error ) : undefined,
);
</script>

<template>
	<FieldLayout :field="field" :layout="layout">
		<!-- Text -->
		<Input
			v-if="field.type === 'text'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="text"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:minlength="field.validation_rules?.min"
			:maxlength="field.validation_rules?.max"
			:pattern="field.validation_rules?.pattern ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Email -->
		<Input
			v-else-if="field.type === 'email'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="email"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Phone -->
		<Input
			v-else-if="field.type === 'phone'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="tel"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:pattern="field.validation_rules?.pattern ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Number -->
		<Input
			v-else-if="field.type === 'number'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="number"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:min="field.validation_rules?.min"
			:max="field.validation_rules?.max"
			:step="field.validation_rules?.step"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- URL -->
		<Input
			v-else-if="field.type === 'url'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="url"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? 'https://'"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Textarea -->
		<Textarea
			v-else-if="field.type === 'textarea'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:minlength="field.validation_rules?.min"
			:maxlength="field.validation_rules?.max"
			:rows="getTextareaRows( field )"
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
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="time"
			:model-value="stringValue"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:min="field.validation_rules?.min_time ?? undefined"
			:max="field.validation_rules?.max_time ?? undefined"
			:step="getTimeStep( field )"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</FieldLayout>
</template>
