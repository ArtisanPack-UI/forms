<!--
  FieldEditor component for per-field settings.

  Adapts its UI based on the selected field type, showing relevant
  configuration options like validation rules, options editor for
  choice fields, file upload settings, and conditional logic.

  @package    ArtisanPack_UI
  @subpackage Forms

  @since      1.1.0
-->
<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';

import { Button, Checkbox, Input, Select, Tabs, Textarea } from '@artisanpack-ui/vue';

import type {
	FieldOption,
	FieldSettingDefinition,
	FieldType,
	FieldWidth,
	FormField,
	UpdateFieldRequest,
} from '../../../types/artisanpack-forms';

import {
	buildLayoutContentUpdate,
	getLayoutContent,
	getSettingsForTab,
} from '../../../shared/fieldSettings';
import { getFieldTypeLabel } from '../../../shared/fieldTypeLabels';
import { t } from '../../../shared/i18n';

import ConditionalLogicEditor from './ConditionalLogicEditor.vue';
import FieldSettingControl from './FieldSettingControl.vue';

/** Field types that support an options list. */
const OPTION_FIELD_TYPES = new Set<FieldType>( [
	'select',
	'radio',
	'checkbox_group',
	'select_multiple',
] );

/** Field types without a placeholder. */
const NO_PLACEHOLDER_TYPES = new Set<FieldType>( ['checkbox', 'radio', 'rating'] );

/** Field types that don't collect user data. */
const LAYOUT_FIELD_TYPES = new Set<FieldType>( [
	'heading',
	'paragraph',
	'divider',
	'html',
] );

/** Available width options, translated at render. */
const widthOptions = computed( (): Array<{ value: FieldWidth; label: string }> => [
	{ value: 'full', label: t( 'Full Width' ) },
	{ value: 'half', label: t( 'Half (1/2)' ) },
	{ value: 'third', label: t( 'Third (1/3)' ) },
	{ value: 'two-thirds', label: t( 'Two-Thirds (2/3)' ) },
] );

const props = defineProps<{
	/** The field being edited. */
	field: FormField;
	/** All fields in the form (for conditional logic field references). */
	allFields: FormField[];
	/** Optional CSS class name. */
	class?: string;
}>();

const emit = defineEmits<{
	change: [fieldId: number, data: UpdateFieldRequest];
	delete: [fieldId: number];
	duplicate: [fieldId: number];
	close: [];
}>();

const activeTab = ref<string>( 'general' );

const isLayoutField = computed( () => LAYOUT_FIELD_TYPES.has( props.field.type ) );
const hasOptions    = computed( () => OPTION_FIELD_TYPES.has( props.field.type ) );

// ---------------------------------------------------------------------------
// Debounced field updates (500ms) -- accumulate changes and flush on timer
// or unmount.
// ---------------------------------------------------------------------------

const updateTimer = ref<ReturnType<typeof setTimeout> | null>( null );
const pendingUpdates = ref<UpdateFieldRequest>( {} );

let snapshotFieldId = props.field.id;

function flushPendingUpdates(): void {
	if ( updateTimer.value ) {
		clearTimeout( updateTimer.value );
		updateTimer.value = null;
	}

	if ( Object.keys( pendingUpdates.value ).length > 0 ) {
		emit( 'change', snapshotFieldId, pendingUpdates.value );
		pendingUpdates.value = {};
	}
}

/** The field with buffered edits applied, so successive edits compose. */
const currentField = computed( (): FormField => ( { ...props.field, ...pendingUpdates.value } as FormField ) );

function updateField( data: UpdateFieldRequest ): void {
	snapshotFieldId      = props.field.id;
	pendingUpdates.value = { ...pendingUpdates.value, ...data };

	if ( updateTimer.value ) {
		clearTimeout( updateTimer.value );
	}

	const fieldId = snapshotFieldId;
	updateTimer.value = setTimeout( () => {
		emit( 'change', fieldId, pendingUpdates.value );
		pendingUpdates.value = {};
		updateTimer.value    = null;
	}, 500 );
}

let deleteInProgress = false;

// Flush pending updates when the field changes and reset delete flag
watch( () => props.field.id, () => {
	flushPendingUpdates();
	deleteInProgress = false;
} );

onUnmounted( () => {
	if ( !deleteInProgress ) {
		flushPendingUpdates();
	}
} );

// ---------------------------------------------------------------------------
// Options editor for choice fields
// ---------------------------------------------------------------------------

const options = computed( (): FieldOption[] => {
	if ( !hasOptions.value ) {
		return [];
	}

	// Check pending updates first so rapid edits compose correctly
	const pending = pendingUpdates.value.field_config as { options?: FieldOption[] } | undefined;

	if ( pending?.options ) {
		return pending.options;
	}

	return ( props.field.field_config as { options?: FieldOption[] } )?.options
		?? props.field.options
		?? [];
} );

function handleAddOption(): void {
	// Compute unique suffix to avoid collisions after deletions
	let maxSuffix = 0;

	for ( const opt of options.value ) {
		const match = opt.value.match( /^option_(\d+)$/ );

		if ( match ) {
			maxSuffix = Math.max( maxSuffix, Number( match[1] ) );
		}
	}

	const nextSuffix = maxSuffix + 1;
	const newOptions = [
		...options.value,
		{ label: t( 'Option :number', { number: nextSuffix } ), value: `option_${nextSuffix}` },
	];
	updateField( { field_config: { ...( currentField.value.field_config ?? {} ), options: newOptions } } );
}

function handleUpdateOption( index: number, key: 'label' | 'value', value: string ): void {
	const newOptions = options.value.map( ( opt, i ) =>
		i === index ? { ...opt, [key]: value } : opt,
	);
	updateField( { field_config: { ...( currentField.value.field_config ?? {} ), options: newOptions } } );
}

function handleRemoveOption( index: number ): void {
	const newOptions = options.value.filter( ( _, i ) => i !== index );
	updateField( { field_config: { ...( currentField.value.field_config ?? {} ), options: newOptions } } );
}

// ---------------------------------------------------------------------------
// Type-specific settings, rendered from the field's settings schema
// ---------------------------------------------------------------------------

const generalSettings = computed( (): FieldSettingDefinition[] => getSettingsForTab( currentField.value, 'general' ) );
const validationSettings = computed( (): FieldSettingDefinition[] => getSettingsForTab( currentField.value, 'validation' ) );
const advancedSettings = computed( (): FieldSettingDefinition[] => getSettingsForTab( currentField.value, 'advanced' ) );

// ---------------------------------------------------------------------------
// Tabs
// ---------------------------------------------------------------------------

const tabs = computed( () => {
	const items = [
		{ name: 'general', label: t( 'General' ) },
	];

	if ( validationSettings.value.length > 0 ) {
		items.push( { name: 'validation', label: t( 'Validation' ) } );
	}

	if ( !isLayoutField.value ) {
		items.push( { name: 'conditional', label: t( 'Conditional' ) } );
	}

	if ( advancedSettings.value.length > 0 ) {
		items.push( { name: 'advanced', label: t( 'Advanced' ) } );
	}

	return items;
} );

// Fall back to the General tab when the selected field has no such tab.
watch( tabs, ( items ) => {
	if ( !items.some( ( item ) => item.name === activeTab.value ) ) {
		activeTab.value = 'general';
	}
} );

function confirmDelete(): void {
	if ( window.confirm( t( 'Are you sure you want to delete this field?' ) ) ) {
		deleteInProgress = true;
		pendingUpdates.value = {};

		if ( updateTimer.value ) {
			clearTimeout( updateTimer.value );
			updateTimer.value = null;
		}

		emit( 'delete', props.field.id );
	}
}
</script>

<template>
	<div :class="['flex flex-col gap-0', props.class]">
		<!-- Header -->
		<div class="flex items-center justify-between p-3 border-b border-base-300">
			<h3 class="text-sm font-semibold">
				{{ t( 'Edit :type Field', { type: getFieldTypeLabel( field.type ) } ) }}
			</h3>
			<Button
				color="ghost"
				size="sm"
				class="btn-circle"
				:aria-label="t( 'Close field editor' )"
				@click="emit( 'close' )"
			>
				&times;
			</Button>
		</div>

		<!-- Tabs -->
		<Tabs
			variant="bordered"
			:tabs="tabs"
			:active-tab="activeTab"
			@change="activeTab = $event"
		>
			<!-- General Tab -->
			<template #general>
				<div class="space-y-4 p-4">
					<!-- Label (a heading's label is its text) -->
					<Input
						v-if="field.type === 'heading'"
						:label="t( 'Heading Text' )"
						:model-value="getLayoutContent( field )"
						@update:model-value="updateField( buildLayoutContentUpdate( currentField, ($event as string) ) )"
					/>
					<Input
						v-else
						:label="t( 'Label' )"
						:model-value="field.label ?? ''"
						@update:model-value="updateField( { label: ($event as string) } )"
					/>

					<!-- Name (machine name) -->
					<Input
						:label="t( 'Name' )"
						:model-value="field.name"
						:hint="t( 'Machine-readable name. Used as the form data key.' )"
						@update:model-value="updateField( { name: ($event as string) } )"
					/>

					<!-- Placeholder (non-layout only) -->
					<Input
						v-if="!isLayoutField && !NO_PLACEHOLDER_TYPES.has( field.type )"
						:label="t( 'Placeholder' )"
						:model-value="field.placeholder ?? ''"
						@update:model-value="updateField( { placeholder: ($event as string) || null } )"
					/>

					<!-- Help text -->
					<Input
						v-if="!isLayoutField"
						:label="t( 'Help Text' )"
						:model-value="field.help_text ?? ''"
						@update:model-value="updateField( { help_text: ($event as string) || null } )"
					/>

					<!-- Default value -->
					<Input
						v-if="!isLayoutField && field.type !== 'file'"
						:label="t( 'Default Value' )"
						:model-value="field.default_value ?? ''"
						@update:model-value="updateField( { default_value: ($event as string) || null } )"
					/>

					<!-- Required toggle -->
					<Checkbox
						v-if="!isLayoutField"
						:label="t( 'Required' )"
						:model-value="field.is_required"
						@update:model-value="updateField( { is_required: ($event as boolean) } )"
					/>

					<!-- Width -->
					<Select
						:label="t( 'Width' )"
						:model-value="field.width"
						:options="widthOptions"
						option-value="value"
						option-label="label"
						@update:model-value="updateField( { width: ($event as FieldWidth) } )"
					/>

					<!-- CSS Classes -->
					<Input
						:label="t( 'CSS Classes' )"
						:model-value="field.css_classes ?? ''"
						@update:model-value="updateField( { css_classes: ($event as string) || null } )"
					/>

					<!-- Type-specific settings from the field's settings schema -->
					<FieldSettingControl
						v-for="setting in generalSettings"
						:key="`${field.id}-${setting.key}`"
						:field="currentField"
						:setting="setting"
						@update="updateField( $event )"
					/>

					<!-- HTML Content (for html field type) -->
					<Textarea
						v-if="field.type === 'html'"
						:label="t( 'HTML Content' )"
						:rows="6"
						:model-value="getLayoutContent( field )"
						@update:model-value="updateField( buildLayoutContentUpdate( currentField, ($event as string) ) )"
					/>

					<!-- Paragraph content -->
					<Textarea
						v-if="field.type === 'paragraph'"
						:label="t( 'Content' )"
						:rows="4"
						:model-value="getLayoutContent( field )"
						@update:model-value="updateField( buildLayoutContentUpdate( currentField, ($event as string) ) )"
					/>

					<!-- Options editor for choice fields -->
					<div v-if="hasOptions" class="space-y-2">
						<label class="label">
							<span class="label-text font-medium">{{ t( 'Options' ) }}</span>
						</label>
						<div
							v-for="( option, index ) in options"
							:key="index"
							class="flex items-center gap-2"
						>
							<Input
								:placeholder="t( 'Label' )"
								:aria-label="t( 'Option :number label', { number: index + 1 } )"
								:model-value="option.label"
								class="flex-1"
								@update:model-value="handleUpdateOption( index, 'label', ($event as string) )"
							/>
							<Input
								:placeholder="t( 'Value' )"
								:aria-label="t( 'Option :number value', { number: index + 1 } )"
								:model-value="option.value"
								class="flex-1"
								@update:model-value="handleUpdateOption( index, 'value', ($event as string) )"
							/>
							<Button
								color="ghost"
								size="sm"
								class="btn-circle"
								:title="t( 'Remove option' )"
								:aria-label="t( 'Remove option' )"
								@click="handleRemoveOption( index )"
							>
								&times;
							</Button>
						</div>
						<Button
							color="ghost"
							size="sm"
							@click="handleAddOption"
						>
							{{ t( 'Add Option' ) }}
						</Button>
					</div>
				</div>
			</template>

			<!-- Validation Tab -->
			<template #validation>
				<div class="space-y-4 p-4">
					<FieldSettingControl
						v-for="setting in validationSettings"
						:key="`${field.id}-${setting.key}`"
						:field="currentField"
						:setting="setting"
						@update="updateField( $event )"
					/>
				</div>
			</template>

			<!-- Advanced Tab (e.g. read-only) -->
			<template #advanced>
				<div class="space-y-4 p-4">
					<FieldSettingControl
						v-for="setting in advancedSettings"
						:key="`${field.id}-${setting.key}`"
						:field="currentField"
						:setting="setting"
						@update="updateField( $event )"
					/>
				</div>
			</template>

			<!-- Conditional Tab -->
			<template #conditional>
				<ConditionalLogicEditor
					:value="field.conditional_logic"
					:fields="allFields"
					:exclude-field-id="field.id"
					@update:value="updateField( { conditional_logic: $event } )"
				/>
			</template>
		</Tabs>

		<!-- Footer actions -->
		<div class="flex gap-2 justify-end p-3 border-t border-base-300">
			<Button
				color="ghost"
				size="sm"
				@click="() => { flushPendingUpdates(); emit( 'duplicate', field.id ); }"
			>
				{{ t( 'Duplicate' ) }}
			</Button>
			<Button
				color="error"
				size="sm"
				@click="confirmDelete"
			>
				{{ t( 'Delete' ) }}
			</Button>
		</div>
	</div>
</template>
