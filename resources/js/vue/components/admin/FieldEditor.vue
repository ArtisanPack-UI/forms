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
	PredefinedChoiceList,
	SubInputDefinition,
	UpdateFieldRequest,
} from '../../../types/artisanpack-forms';

import {
	buildLayoutContentUpdate,
	getLayoutContent,
	getSettingsForTab,
	supportsConditionalLogic,
} from '../../../shared/fieldSettings';
import { applySubInputUpdate, isCompositeField } from '../../../shared/composite';
import type { SubInputSetting } from '../../../shared/composite';
import { MERGE_TAGS } from '../../../shared/dynamicPopulation';
import { getFieldTypeLabel } from '../../../shared/fieldTypeLabels';
import { t } from '../../../shared/i18n';

import ConditionalLogicEditor from './ConditionalLogicEditor.vue';
import FieldSettingControl from './FieldSettingControl.vue';
import OptionsEditor from './OptionsEditor.vue';
import SubInputsEditor from './SubInputsEditor.vue';

/** Field types that support an options list. */
const OPTION_FIELD_TYPES = new Set<FieldType>( [
	'select',
	'radio',
	'checkbox_group',
	'select_multiple',
] );

/** Field types without a placeholder. */
const NO_PLACEHOLDER_TYPES = new Set<FieldType>( ['checkbox', 'toggle', 'radio', 'rating', 'datetime'] );

/**
 * Field types without a placeholder or a default value (since 1.6.0). Name,
 * address and date range fields are composites, whose inputs have their own,
 * and a list starts with empty rows.
 */
const NO_VALUE_SETTING_TYPES = new Set<FieldType>( ['consent', 'captcha', 'list'] );

/** Field types that don't collect user data. */
const LAYOUT_FIELD_TYPES = new Set<FieldType>( [
	'section',
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
	/**
	 * The lists the options editor's bulk editor can insert (since 1.6.0;
	 * from the `fields/predefined-choices` endpoint).
	 */
	predefinedChoices?: PredefinedChoiceList[];
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
const isComposite   = computed( () => isCompositeField( props.field ) );

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

function setOptions( newOptions: FieldOption[] ): void {
	updateField( { field_config: { ...( currentField.value.field_config ?? {} ), options: newOptions } } );
}

// ---------------------------------------------------------------------------
// Sub-inputs of a composite field: shown changed at once, rebuilt by the
// server on save.
// ---------------------------------------------------------------------------

const localSubInputs = ref<SubInputDefinition[] | null>( null );

watch( () => [props.field.id, props.field.sub_inputs], () => {
	localSubInputs.value = null;
} );

const subInputsField = computed( (): FormField => ( {
	...currentField.value,
	sub_inputs: localSubInputs.value ?? props.field.sub_inputs,
} ) );

function handleSubInputChange( data: UpdateFieldRequest, key: string, setting: SubInputSetting, value: unknown ): void {
	localSubInputs.value = applySubInputUpdate( subInputsField.value, key, setting, value ).sub_inputs ?? null;
	updateField( data );
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

	// A section's logic shows and hides the fields it groups (since 1.6.0)
	if ( supportsConditionalLogic( props.field.type ) ) {
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
					<!-- Label (a heading's label is its text, a section's its title) -->
					<Input
						v-if="field.type === 'heading'"
						:label="t( 'Heading Text' )"
						:model-value="getLayoutContent( field )"
						@update:model-value="updateField( buildLayoutContentUpdate( currentField, ($event as string) ) )"
					/>
					<Input
						v-else
						:label="field.type === 'section' ? t( 'Section Title' ) : t( 'Label' )"
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

					<!-- Placeholder (non-layout only; a composite field's inputs have their own) -->
					<template v-if="!NO_VALUE_SETTING_TYPES.has( field.type )">
						<Input
							v-if="!isLayoutField && !isComposite && !NO_PLACEHOLDER_TYPES.has( field.type )"
							:label="t( 'Placeholder' )"
							:model-value="field.placeholder ?? ''"
							@update:model-value="updateField( { placeholder: ($event as string) || null } )"
						/>
					</template>

					<!-- A composite field's sub-inputs -->
					<SubInputsEditor
						v-if="isComposite"
						:key="field.id"
						:field="subInputsField"
						@change="handleSubInputChange"
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
						v-if="!isLayoutField && !isComposite && field.type !== 'file' && !NO_VALUE_SETTING_TYPES.has( field.type )"
						:label="t( 'Default Value' )"
						:model-value="field.default_value ?? ''"
						:hint="t( 'Merge tags: :tags', { tags: MERGE_TAGS.join( ', ' ) } )"
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
						:hint="t( 'Write {field_name} to show what a visitor entered, as they type.' )"
						:model-value="getLayoutContent( field )"
						@update:model-value="updateField( buildLayoutContentUpdate( currentField, ($event as string) ) )"
					/>

					<!-- Paragraph content -->
					<Textarea
						v-if="field.type === 'paragraph'"
						:label="t( 'Content' )"
						:rows="4"
						:hint="t( 'Basic formatting is kept: <strong>, <em>, <u> and <a href>.' )"
						:model-value="getLayoutContent( field )"
						@update:model-value="updateField( buildLayoutContentUpdate( currentField, ($event as string) ) )"
					/>

					<!-- Options editor for choice fields -->
					<OptionsEditor
						v-if="hasOptions"
						:key="field.id"
						:field="currentField"
						:options="options"
						:predefined-choices="predefinedChoices"
						@change="setOptions"
					/>
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
