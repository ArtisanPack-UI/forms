<!--
  Advanced field components wrapper.

  Renders file upload and date picker fields
  using @artisanpack-ui/vue components.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { DatePicker, FileUpload, Input } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';
import {
	getFieldLayout,
	getFileAccept,
	getInputLabelProps,
	getLayoutDescribedBy,
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
	'fileChange': [file: File | File[]];
}>();

const stringValue = computed( () => String( props.modelValue ?? '' ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** The input's label, hint and ID props; `aria-describedby` is set by `v-described-by`. */
const labelProps = computed( () => {
	const { 'aria-describedby': _describedBy, ...rest } = getInputLabelProps( props.field, layout.value, props.displayConfig, props.error );

	return rest;
} );

/** The file input's label props; it has no inline label mode. */
const fileLabelProps = computed( () => {
	const { inline: _inline, ...rest } = labelProps.value;

	return rest;
} );

/** The description and error IDs the input references in a custom layout. */
const describedBy = computed( () =>
	layout.value.custom ? getLayoutDescribedBy( props.field, layout.value, props.error ) : undefined,
);

const allowedTypes = computed( () => getFileAccept( props.field ) );

/** Read-only dates stay focusable and announced, but cannot be edited. */
const readOnly = computed( () => isReadOnlyField( props.field ) );

function handleFilesSelected( fileList: FileList | null ): void {
	if ( !fileList || fileList.length === 0 ) {
		emit( 'fileChange', [] as File[] );

		return;
	}

	if ( fileList.length === 1 ) {
		emit( 'fileChange', fileList[0] );
	} else {
		emit( 'fileChange', Array.from( fileList ) );
	}
}
</script>

<template>
	<FieldLayout :field="field" :layout="layout">
		<!-- File -->
		<FileUpload
			v-if="field.type === 'file'"
			v-described-by="describedBy"
			v-bind="fileLabelProps"
			:name="field.name"
			:accept="allowedTypes"
			:error="error"
			:required="field.is_required"
			:class="field.css_classes ?? undefined"
			with-drag-drop
			@files-selected="handleFilesSelected"
		/>

		<!-- Date, read-only: DatePicker has no readonly state, so use a plain date input -->
		<Input
			v-else-if="field.type === 'date' && readOnly"
			v-autocomplete="field.autocomplete"
			v-described-by="describedBy"
			type="date"
			v-bind="labelProps"
			:name="field.name"
			:model-value="stringValue"
			:error="error"
			:required="field.is_required"
			:class="field.css_classes ?? undefined"
			readonly
		/>

		<!-- Date -->
		<DatePicker
			v-else-if="field.type === 'date'"
			v-autocomplete="field.autocomplete"
			v-described-by="describedBy"
			v-bind="labelProps"
			:name="field.name"
			:model-value="stringValue"
			:error="error"
			:required="field.is_required"
			:class="field.css_classes ?? undefined"
			:min="field.validation_rules?.min_date ?? undefined"
			:max="field.validation_rules?.max_date ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</FieldLayout>
</template>
