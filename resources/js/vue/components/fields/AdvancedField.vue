<!--
  Advanced field components wrapper.

  Renders file upload and date picker fields
  using @artisanpack-ui/vue components. A multi-file field (since 1.6.0)
  adds each file chosen or dropped to a list with a remove button, up to its
  max files, and a polite live region announces the changes. Files upload
  with the submission.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { DatePicker, FileUpload, Input } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';
import {
	allowsMultipleFiles,
	getFieldLayout,
	getFileAccept,
	getFileMaxCount,
	getInputLabelProps,
	getLayoutDescribedBy,
	isReadOnlyField,
} from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
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

/** Whether the field takes several files (since 1.6.0). */
const multiple = computed( () => allowsMultipleFiles( props.field ) );

/** A multi-file field's files, and what the live region last announced (since 1.6.0). */
const files = ref<File[]>( [] );
const announcement = ref( '' );

/** Store a multi-file field's files and announce the change. */
function updateFiles( next: File[], message: string ): void {
	files.value = next;
	announcement.value = message;
	emit( 'fileChange', next );
}

/** Add chosen or dropped files to a multi-file field, up to its max files. */
function addFiles( fileList: FileList | null ): void {
	const added = Array.from( fileList ?? [] );
	const maxFiles = getFileMaxCount( props.field );
	const room = maxFiles === undefined ? added.length : Math.max( 0, maxFiles - files.value.length );
	const accepted = added.slice( 0, room );

	if ( accepted.length === 0 && added.length > 0 ) {
		announcement.value = t( 'You may upload no more than :max files.', { max: maxFiles ?? 0 } );

		return;
	}

	updateFiles(
		[...files.value, ...accepted],
		accepted.length < added.length
			? t( 'You may upload no more than :max files.', { max: maxFiles ?? 0 } )
			: t( ':count files ready to upload.', { count: files.value.length + accepted.length } ),
	);
}

/** Remove one file from a multi-file field. */
function removeFile( index: number ): void {
	const removed = files.value[index];

	updateFiles( files.value.filter( ( _file, position ) => position !== index ), t( ':name removed.', { name: removed?.name ?? '' } ) );
}

/** Show a file size in KB or MB. */
function formatFileSize( bytes: number ): string {
	return bytes >= 1048576 ? `${( bytes / 1048576 ).toFixed( 1 )} MB` : `${Math.max( 1, Math.round( bytes / 1024 ) )} KB`;
}

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
		<!-- File, multiple: a drop zone and the list of files to upload (since 1.6.0) -->
		<div v-if="field.type === 'file' && multiple">
			<FileUpload
				v-described-by="describedBy"
				v-bind="fileLabelProps"
				:name="field.name"
				:accept="allowedTypes"
				:error="error"
				:class="field.css_classes ?? undefined"
				multiple
				with-drag-drop
				@files-selected="addFiles"
			/>

			<ul
				v-if="files.length > 0"
				class="mt-3 flex flex-col gap-2"
				:aria-label="t( 'Files for :field', { field: field.label ?? field.name } )"
			>
				<li
					v-for="( file, index ) in files"
					:key="`${file.name}-${file.size}-${file.lastModified}-${index}`"
					class="flex items-center gap-3 rounded-box bg-base-200 p-2"
				>
					<span class="min-w-0 grow truncate text-sm">{{ file.name }}</span>
					<span class="shrink-0 text-xs text-base-content/60">{{ formatFileSize( file.size ) }}</span>
					<button
						type="button"
						class="btn btn-ghost btn-sm btn-square"
						:aria-label="t( 'Remove :name', { name: file.name } )"
						@click="removeFile( index )"
					>
						<span aria-hidden="true">✕</span>
					</button>
				</li>
			</ul>

			<div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
		</div>

		<!-- File -->
		<FileUpload
			v-else-if="field.type === 'file'"
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
