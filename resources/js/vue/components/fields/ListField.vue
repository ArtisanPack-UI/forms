<!--
  List (repeater) field component.

  Renders rows of the field's columns that visitors add and remove, in a
  `<fieldset>` whose `<legend>` is the field label (since 1.6.0; see
  `shared/list.ts`). Each row is a group named for its row ("Attendee 2"),
  with one labelled input per column; the column labels show as a header row
  from the `sm` breakpoint up, where the inputs' own labels are kept for
  screen readers only. After a row is added its first input takes focus,
  after one is removed the add button does, and a polite live region
  announces each change. Mirrors the Blade `list` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import {
	getEmptyListRow,
	getInitialListRows,
	getListColumns,
	getListMaxRows,
	getListRowLabel,
	LIST_CELL_MAX_LENGTH,
	toListRows,
} from '../../../shared/list';
import type { ListRow } from '../../../shared/list';

const props = withDefaults( defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	/** Each cell's first error, keyed `{row}.{column}`. */
	subErrors?: Record<string, string>;
	displayConfig: DisplayConfig;
}>(), {
	error: undefined,
	subErrors: () => ( {} ),
} );

const emit = defineEmits<{
	'update:modelValue': [value: ListRow[]];
}>();

const columns = computed( () => getListColumns( props.field ) );

const rows = computed( () => {
	const stored = toListRows( props.field, props.modelValue );

	return stored.length > 0 ? stored : getInitialListRows( props.field );
} );

const maxRows = computed( () => getListMaxRows( props.field ) );
const rowLabel = computed( () => getListRowLabel( props.field ) );
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );
const hiddenLabel = computed( () => layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ) );
const cellErrorCount = computed( () => Object.keys( props.subErrors ).length );
const hasErrors = computed( () => Boolean( props.error ) || cellErrorCount.value > 0 );
const hintId = computed( () => ( props.field.help_text ? `${props.field.name}-hint` : undefined ) );
const errorId = computed( () => ( hasErrors.value ? `${props.field.name}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );
const gridStyle = computed( () => ( { gridTemplateColumns: `repeat(${Math.max( 1, columns.value.length )}, minmax(0, 1fr)) auto` } ) );

const summary = computed( () => props.error || ( cellErrorCount.value === 1
	? t( 'Please fix :count highlighted entry in :field.', { count: cellErrorCount.value, field: props.field.label ?? props.field.name } )
	: t( 'Please fix :count highlighted entries in :field.', { count: cellErrorCount.value, field: props.field.label ?? props.field.name } ) ) );

const announcement = ref( '' );
const addButton = ref<HTMLButtonElement | null>( null );

/** The ID of a cell's input. */
function inputId( index: number, key: string ): string {
	return `field-${props.field.name}-${index}-${key}`;
}

/** Store a cell's new text. */
function changeCell( index: number, key: string, text: string ): void {
	emit( 'update:modelValue', rows.value.map( ( row, position ) => ( position === index ? { ...row, [key]: text } : row ) ) );
}

/** Add an empty row, focus its first input and announce it. */
async function addRow(): Promise<void> {
	if ( rows.value.length >= maxRows.value ) {
		return;
	}

	const index = rows.value.length;

	emit( 'update:modelValue', [...rows.value, getEmptyListRow( props.field )] );
	announcement.value = t( ':row :number added.', { row: rowLabel.value, number: index + 1 } );

	await nextTick();

	if ( columns.value[0] ) {
		document.getElementById( inputId( index, columns.value[0].key ) )?.focus();
	}
}

/** Remove a row (keeping one empty row), focus the add button and announce it. */
async function removeRow( index: number ): Promise<void> {
	const next = rows.value.filter( ( _row, position ) => position !== index );

	emit( 'update:modelValue', next.length > 0 ? next : [getEmptyListRow( props.field )] );
	announcement.value = t( ':row :number removed.', { row: rowLabel.value, number: index + 1 } );

	await nextTick();
	addButton.value?.focus();
}
</script>

<template>
	<fieldset
		:id="`field-${field.name}`"
		:class="['fieldset', field.css_classes ?? '']"
		:data-forms-list="field.name"
		:aria-describedby="describedBy"
	>
		<legend v-if="field.label" :class="['fieldset-legend', hiddenLabel ? 'sr-only' : '']">
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
		</legend>

		<p v-if="hintId && layout.description === 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<div class="hidden gap-2 sm:grid" :style="gridStyle" aria-hidden="true">
			<span v-for="column in columns" :key="column.key" class="label text-sm">{{ column.label }}</span>
			<span class="w-8" />
		</div>

		<div class="flex flex-col gap-3 sm:gap-2">
			<div
				v-for="( row, index ) in rows"
				:key="index"
				role="group"
				:aria-labelledby="`field-${field.name}-row-${index}-label`"
				class="flex flex-col gap-2 rounded-box bg-base-200 p-3 sm:grid sm:items-start sm:bg-transparent sm:p-0"
				:style="gridStyle"
			>
				<span :id="`field-${field.name}-row-${index}-label`" class="sr-only">{{ `${rowLabel} ${index + 1}` }}</span>

				<div v-for="column in columns" :key="column.key" class="flex flex-col gap-1">
					<label :for="inputId( index, column.key )" class="label text-sm sm:sr-only">{{ column.label }}</label>
					<select
						v-if="column.type === 'select'"
						:id="inputId( index, column.key )"
						:value="row[column.key] ?? ''"
						:class="['select select-bordered w-full', subErrors[`${index}.${column.key}`] ? 'select-error' : '']"
						:aria-invalid="subErrors[`${index}.${column.key}`] ? 'true' : undefined"
						:aria-describedby="subErrors[`${index}.${column.key}`] ? `${inputId( index, column.key )}-error` : undefined"
						@change="changeCell( index, column.key, ( $event.target as HTMLSelectElement ).value )"
					>
						<option value="">{{ t( 'Select…' ) }}</option>
						<option v-for="option in column.options" :key="option.value" :value="option.value">{{ option.label }}</option>
					</select>
					<input
						v-else
						:id="inputId( index, column.key )"
						:type="column.type"
						:value="row[column.key] ?? ''"
						:maxlength="column.type === 'number' ? undefined : LIST_CELL_MAX_LENGTH"
						:class="['input input-bordered w-full', subErrors[`${index}.${column.key}`] ? 'input-error' : '']"
						:aria-invalid="subErrors[`${index}.${column.key}`] ? 'true' : undefined"
						:aria-describedby="subErrors[`${index}.${column.key}`] ? `${inputId( index, column.key )}-error` : undefined"
						@input="changeCell( index, column.key, ( $event.target as HTMLInputElement ).value )"
					/>
					<p
						v-if="subErrors[`${index}.${column.key}`]"
						:id="`${inputId( index, column.key )}-error`"
						class="text-xs text-error"
					>
						{{ subErrors[`${index}.${column.key}`] }}
					</p>
				</div>

				<button
					type="button"
					class="btn btn-ghost btn-sm btn-square self-end sm:self-start"
					:aria-label="t( 'Remove :row :number', { row: rowLabel, number: index + 1 } )"
					@click="removeRow( index )"
				>
					<span aria-hidden="true">✕</span>
				</button>
			</div>
		</div>

		<div class="mt-2 flex items-center gap-3">
			<button
				ref="addButton"
				type="button"
				class="btn btn-sm btn-outline"
				:disabled="rows.length >= maxRows"
				@click="addRow"
			>
				<span aria-hidden="true">+</span> {{ t( 'Add :row', { row: rowLabel } ) }}
			</button>
			<span v-if="rows.length >= maxRows" class="text-xs text-base-content/60">
				{{ maxRows === 1 ? t( 'Up to :count row.', { count: maxRows } ) : t( 'Up to :count rows.', { count: maxRows } ) }}
			</span>
		</div>

		<p v-if="hintId && layout.description !== 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<p v-if="hasErrors" :id="errorId" class="fieldset-label text-error" role="alert">{{ summary }}</p>

		<div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
	</fieldset>
</template>
