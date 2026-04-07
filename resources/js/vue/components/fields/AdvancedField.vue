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
import { DatePicker, FileUpload } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';

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

const showLabel = computed( () => props.displayConfig.label_position !== 'hidden' );
const labelText = computed( () => props.field.label ?? undefined );
const ariaLabel = computed( () =>
	props.displayConfig.label_position === 'hidden'
		? ( props.field.label ?? props.field.name )
		: undefined,
);

const allowedTypes = computed( () =>
	props.field.validation_rules?.allowed_types?.join( ',' ) ?? undefined,
);

function handleFilesSelected( fileList: FileList | null ): void {
	if ( !fileList || fileList.length === 0 ) {
		emit( 'fileChange', [] as unknown as File[] );

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
	<!-- File -->
	<FileUpload
		v-if="field.type === 'file'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		:accept="allowedTypes"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		with-drag-drop
		@files-selected="handleFilesSelected"
	/>

	<!-- Date -->
	<DatePicker
		v-else-if="field.type === 'date'"
		:label="showLabel ? labelText : undefined"
		:aria-label="ariaLabel"
		:name="field.name"
		:model-value="stringValue"
		:hint="field.help_text ?? undefined"
		:error="error"
		:required="field.is_required"
		:class="field.css_classes ?? undefined"
		:min="field.validation_rules?.min_date ?? undefined"
		:max="field.validation_rules?.max_date ?? undefined"
		:inline="displayConfig.label_position === 'beside'"
		@update:model-value="emit( 'update:modelValue', $event )"
	/>
</template>
