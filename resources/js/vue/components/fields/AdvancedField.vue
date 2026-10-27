<!--
  Advanced field components wrapper.

  Renders file upload, date picker and (since 1.6.0) date and time fields
  using @artisanpack-ui/vue components. A multi-file field (since 1.6.0)
  adds each file chosen or dropped to a list with a remove button, up to its
  max files, and a polite live region announces the changes. Files upload
  with the submission. A note under a file input lists the allowed types and
  size limits (since 1.6.0). A date and time field is a native
  `datetime-local` input bounded by its min and max dates.

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
	getFileLimitsHint,
	getFileMaxCount,
	getInputLabelProps,
	getLayoutDescribedBy,
	getTimeStep,
	isReadOnlyField,
	toFileList,
	withInputNotes,
} from '../../../shared/fieldSettings';
import { getDateBounds, getDateLimits } from '../../../shared/dateConstraints';
import { t } from '../../../shared/i18n';
import FieldLayout from './FieldLayout.vue';
import { vAutocomplete } from './autocomplete';
import { vDescribedBy } from './describedBy';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
	/**
	 * The files the form holds for a file field, so its selection survives a
	 * remount on a step change or by conditional logic (since 1.6.0).
	 */
	files?: File | File[];
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
	'fileChange': [file: File | File[]];
}>();

const stringValue = computed( () => String( props.modelValue ?? '' ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** A file field's note of its allowed types and size limits (since 1.6.0). */
const limitsHint = computed( () => ( props.field.type === 'file' ? getFileLimitsHint( props.field ) : undefined ) );

/** The ID of the limits note, which the file input references. */
const limitsId = computed( () => `field-${props.field.name}-limits` );

/** The IDs of the input's notes. */
const noteIds = computed( () => ( limitsHint.value === undefined ? [] : [limitsId.value] ) );

/** The input's label, hint and ID props, with a known ID when it has notes; `aria-describedby` is set by `v-described-by`. */
const labelProps = computed( () => {
	const { 'aria-describedby': _describedBy, ...rest } = withInputNotes(
		getInputLabelProps( props.field, layout.value, props.displayConfig, props.error ),
		props.field,
		props.error,
		noteIds.value,
	);

	return rest;
} );

/** The file input's label props; it has no inline label mode. */
const fileLabelProps = computed( () => {
	const { inline: _inline, ...rest } = labelProps.value;

	return rest;
} );

/**
 * The IDs the input references: the description and error in a custom
 * layout, and its notes (which also need its own hint and error kept).
 */
const describedBy = computed( () => {
	const custom = layout.value.custom ? getLayoutDescribedBy( props.field, layout.value, props.error ) : undefined;

	if ( noteIds.value.length === 0 ) {
		return custom;
	}

	return withInputNotes(
		{ ...getInputLabelProps( props.field, layout.value, props.displayConfig, props.error ), 'aria-describedby': custom },
		props.field,
		props.error,
		noteIds.value,
	)['aria-describedby'];
} );

/** A date and time field's `min` and `max`, from the start of the min date to the end of the max (since 1.6.0). */
const dateTimeBounds = computed( () => {
	const limits = getDateLimits( props.field );

	return {
		min: limits.min ? `${limits.min}T00:00` : undefined,
		max: limits.max ? `${limits.max}T23:59` : undefined,
	};
} );

const allowedTypes = computed( () => getFileAccept( props.field ) );

/** A date's resolved limits and age limits, for the picker's `min` and `max` (since 1.6.0). */
const dateBounds = computed( () => getDateBounds( props.field ) );

/** Read-only dates stay focusable and announced, but cannot be edited. */
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** Whether the field takes several files (since 1.6.0). */
const multiple = computed( () => allowsMultipleFiles( props.field ) );

/**
 * A file field's files, which the form holds so a remounted field still
 * lists them, and what the live region last announced (since 1.6.0).
 */
const selectedFiles = computed( () => toFileList( props.files ) );
const announcement = ref( '' );

/** Remounts a single-file input when its file is removed, so it forgets it too. */
const fileInputKey = ref( 0 );

/** Store a multi-file field's files and announce the change. */
function updateFiles( next: File[], message: string ): void {
	announcement.value = message;
	emit( 'fileChange', next );
}

/** Remove a single-file field's file. */
function clearFile(): void {
	emit( 'fileChange', [] as File[] );
	fileInputKey.value++;
}

/** Add chosen or dropped files to a multi-file field, up to its max files. */
function addFiles( fileList: FileList | null ): void {
	const added = Array.from( fileList ?? [] );
	const maxFiles = getFileMaxCount( props.field );
	const room = maxFiles === undefined ? added.length : Math.max( 0, maxFiles - selectedFiles.value.length );
	const accepted = added.slice( 0, room );

	if ( accepted.length === 0 && added.length > 0 ) {
		announcement.value = t( 'You may upload no more than :max files.', { max: maxFiles ?? 0 } );

		return;
	}

	updateFiles(
		[...selectedFiles.value, ...accepted],
		accepted.length < added.length
			? t( 'You may upload no more than :max files.', { max: maxFiles ?? 0 } )
			: t( ':count files ready to upload.', { count: selectedFiles.value.length + accepted.length } ),
	);
}

/** Remove one file from a multi-file field. */
function removeFile( index: number ): void {
	const removed = selectedFiles.value[index];

	updateFiles( selectedFiles.value.filter( ( _file, position ) => position !== index ), t( ':name removed.', { name: removed?.name ?? '' } ) );
}

/** Show a file size in KB or MB. */
function formatFileSize( bytes: number ): string {
	return bytes >= 1048576 ? `${( bytes / 1048576 ).toFixed( 1 )} MB` : `${Math.max( 1, Math.round( bytes / 1024 ) )} KB`;
}

/**
 * Store a single-file field's file. Several files dropped at once keep only
 * the first, since the field takes one file (since 1.6.0).
 */
function handleFilesSelected( fileList: FileList | null ): void {
	if ( !fileList || fileList.length === 0 ) {
		emit( 'fileChange', [] as File[] );

		return;
	}

	emit( 'fileChange', fileList[0] );
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

			<p v-if="limitsHint" :id="limitsId" class="fieldset-label mt-1">{{ limitsHint }}</p>

			<ul
				v-if="selectedFiles.length > 0"
				class="mt-3 flex flex-col gap-2"
				:aria-label="t( 'Files for :field', { field: field.label ?? field.name } )"
			>
				<li
					v-for="( file, index ) in selectedFiles"
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
		<div v-else-if="field.type === 'file'">
			<FileUpload
				:key="fileInputKey"
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

			<p v-if="limitsHint" :id="limitsId" class="fieldset-label mt-1">{{ limitsHint }}</p>

			<!-- The form's file, shown since a remounted native input forgets it (since 1.6.0) -->
			<div v-if="selectedFiles[0]" class="mt-3 flex items-center gap-3 rounded-box bg-base-200 p-2">
				<span class="min-w-0 grow truncate text-sm">{{ selectedFiles[0].name }}</span>
				<span class="shrink-0 text-xs text-base-content/60">{{ formatFileSize( selectedFiles[0].size ) }}</span>
				<button
					type="button"
					class="btn btn-ghost btn-sm btn-square"
					:aria-label="t( 'Remove :name', { name: selectedFiles[0].name } )"
					@click="clearFile"
				>
					<span aria-hidden="true">✕</span>
				</button>
			</div>
		</div>

		<!-- Date and time (since 1.6.0) -->
		<Input
			v-else-if="field.type === 'datetime'"
			v-described-by="describedBy"
			type="datetime-local"
			v-bind="labelProps"
			:name="field.name"
			:model-value="stringValue"
			:error="error"
			:required="field.is_required"
			:class="field.css_classes ?? undefined"
			:readonly="readOnly"
			:min="dateTimeBounds.min"
			:max="dateTimeBounds.max"
			:step="getTimeStep( field )"
			@update:model-value="emit( 'update:modelValue', $event )"
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
			:min="dateBounds.min"
			:max="dateBounds.max"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</FieldLayout>
</template>
