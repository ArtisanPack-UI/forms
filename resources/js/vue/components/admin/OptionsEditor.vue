<!--
  Options editor for choice fields.

  Edits a choice field's options: label, value and whether each starts
  selected (one for a radio or select, any number for a checkbox group or
  multi-select), plus a bulk editor that takes one `value|label` per line and
  can insert a predefined list. A field that loads its choices from a source
  shows them read-only instead. Mirrors the React `OptionsEditor`.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, ref } from 'vue';

import { Button, Input, Select, Textarea } from '@artisanpack-ui/vue';

import {
	applyBulkChoices,
	getChoicesSource,
	isMultipleChoiceField,
	parseBulkChoices,
	setOptionDefault,
	toBulkChoices,
} from '../../../shared/choices';
import { t } from '../../../shared/i18n';
import type { FieldOption, FormField, PredefinedChoiceList } from '../../../types/artisanpack-forms';

const props = withDefaults( defineProps<{
	/** The choice field being edited. */
	field: FormField;
	/** The field's stored options. */
	options: FieldOption[];
	/** The lists the bulk editor can insert (see the `fields/predefined-choices` endpoint). */
	predefinedChoices?: PredefinedChoiceList[];
}>(), {
	predefinedChoices: () => [],
} );

const emit = defineEmits<{
	change: [options: FieldOption[]];
}>();

const bulkOpen = ref( false );
const bulkText = ref( '' );
const selectedList = ref( '' );
const multiple = computed( () => isMultipleChoiceField( props.field ) );

/** Whether options can be selected by default; a ranking's are its choices to order (since 1.6.0). */
const hasDefaults = computed( () => props.field.type !== 'ranking' );
const source = computed( () => getChoicesSource( props.field ) );
const bulkId = computed( () => `bulk-choices-${props.field.id}` );
const sourceLabel = computed( () =>
	props.field.settings_schema
		?.find( ( setting ) => setting.key === 'choices_source' )
		?.options?.find( ( option ) => option.value === source.value )?.label ?? source.value ?? '',
);
const listOptions = computed( () => props.predefinedChoices.map( ( list ) => ( { value: list.key, label: list.label } ) ) );

function updateOption( index: number, key: 'label' | 'value', value: string ): void {
	emit( 'change', props.options.map( ( option, i ) => ( i === index ? { ...option, [key]: value } : option ) ) );
}

function addOption(): void {
	// Compute unique suffix to avoid collisions after deletions
	let maxSuffix = 0;

	for ( const option of props.options ) {
		const match = option.value.match( /^option_(\d+)$/ );

		if ( match ) {
			maxSuffix = Math.max( maxSuffix, Number( match[1] ) );
		}
	}

	const nextSuffix = maxSuffix + 1;

	emit( 'change', [
		...props.options,
		{ label: t( 'Option :number', { number: nextSuffix } ), value: `option_${nextSuffix}` },
	] );
}

function toggleBulk(): void {
	if ( bulkOpen.value ) {
		bulkOpen.value = false;

		return;
	}

	bulkText.value = toBulkChoices( props.options );
	bulkOpen.value = true;
}

function insertList(): void {
	const list = props.predefinedChoices.find( ( entry ) => entry.key === selectedList.value );

	if ( list ) {
		bulkText.value = toBulkChoices( list.choices );
	}
}

function applyBulk( mode: 'replace' | 'append' ): void {
	emit( 'change', applyBulkChoices( props.options, parseBulkChoices( bulkText.value ), mode ) );
	bulkOpen.value = false;
}
</script>

<template>
	<div v-if="source" class="space-y-2">
		<span class="label-text font-medium">{{ t( 'Options' ) }}</span>
		<div class="rounded-lg bg-base-200 p-3 text-xs text-base-content/70">
			{{ t( 'Choices are loaded from ":source" when the form is shown. Change "Load Choices From" to edit the options list.', { source: sourceLabel } ) }}
		</div>
		<ul v-if="field.options && field.options.length > 0" class="max-h-48 space-y-1 overflow-auto text-xs" :aria-label="t( 'Loaded choices' )">
			<li v-for="option in field.options" :key="option.value" class="rounded bg-base-100 px-2 py-1">{{ option.label }}</li>
		</ul>
	</div>

	<div v-else class="space-y-2">
		<span class="label-text font-medium">{{ t( 'Options' ) }}</span>
		<div v-for="( option, index ) in options" :key="index" class="flex items-center gap-2">
			<input
				v-if="hasDefaults"
				type="checkbox"
				class="checkbox checkbox-xs checkbox-primary"
				:checked="option.default === true"
				:title="t( 'Selected by default' )"
				:aria-label="t( 'Selected by default: :label', { label: option.label || option.value } )"
				@change="emit( 'change', setOptionDefault( options, index, ( $event.target as HTMLInputElement ).checked, multiple ) )"
			/>
			<Input
				:placeholder="t( 'Label' )"
				:aria-label="t( 'Option :number label', { number: index + 1 } )"
				:model-value="option.label"
				class="flex-1"
				@update:model-value="updateOption( index, 'label', ($event as string) )"
			/>
			<Input
				:placeholder="t( 'Value' )"
				:aria-label="t( 'Option :number value', { number: index + 1 } )"
				:model-value="option.value"
				class="flex-1"
				@update:model-value="updateOption( index, 'value', ($event as string) )"
			/>
			<Button
				color="ghost"
				size="sm"
				class="btn-circle"
				:title="t( 'Remove option' )"
				:aria-label="t( 'Remove option' )"
				@click="emit( 'change', options.filter( ( _, i ) => i !== index ) )"
			>
				&times;
			</Button>
		</div>
		<p v-if="hasDefaults && options.length > 0" class="text-xs text-base-content/60">
			{{ multiple ? t( 'Check the options that start selected.' ) : t( 'Check the option that starts selected.' ) }}
		</p>
		<div class="flex flex-wrap gap-2">
			<Button color="ghost" size="sm" @click="addOption">
				{{ t( 'Add Option' ) }}
			</Button>
			<Button
				color="ghost"
				size="sm"
				:aria-expanded="bulkOpen ? 'true' : 'false'"
				:aria-controls="bulkId"
				@click="toggleBulk"
			>
				{{ t( 'Bulk Add / Predefined Choices' ) }}
			</Button>
		</div>

		<div v-if="bulkOpen" :id="bulkId" class="space-y-2 rounded-lg bg-base-200 p-3">
			<div v-if="predefinedChoices.length > 0" class="flex items-end gap-2">
				<Select
					v-model="selectedList"
					:label="t( 'Predefined Choices' )"
					:placeholder="t( 'Choose a list...' )"
					:options="listOptions"
					option-value="value"
					option-label="label"
					class="flex-1"
				/>
				<Button size="sm" :disabled="!selectedList" @click="insertList">
					{{ t( 'Insert' ) }}
				</Button>
			</div>
			<Textarea
				v-model="bulkText"
				:label="t( 'Choices' )"
				:rows="8"
				class="font-mono text-xs"
				:hint="t( 'One choice per line. Use value|label to store a value that differs from its label.' )"
			/>
			<div class="flex flex-wrap gap-2">
				<Button color="primary" size="sm" @click="applyBulk( 'replace' )">{{ t( 'Replace Options' ) }}</Button>
				<Button size="sm" @click="applyBulk( 'append' )">{{ t( 'Add to Options' ) }}</Button>
				<Button color="ghost" size="sm" @click="bulkOpen = false">{{ t( 'Cancel' ) }}</Button>
			</div>
		</div>
	</div>
</template>
