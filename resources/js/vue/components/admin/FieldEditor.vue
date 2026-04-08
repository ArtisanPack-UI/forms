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
	FieldType,
	FieldWidth,
	FormField,
	UpdateFieldRequest,
	ValidationRules,
} from '../../../types/artisanpack-forms';

import ConditionalLogicEditor from './ConditionalLogicEditor.vue';

/** Safely parse a numeric input value, returning undefined for empty or NaN. */
function parseNumericInput( value: string ): number | undefined {
	if ( !value ) {
		return undefined;
	}

	const num = Number( value );

	return Number.isNaN( num ) ? undefined : num;
}

/** Field types that support an options list. */
const OPTION_FIELD_TYPES = new Set<FieldType>( [
	'select',
	'radio',
	'checkbox_group',
	'select_multiple',
] );

/** Field types that don't collect user data. */
const LAYOUT_FIELD_TYPES = new Set<FieldType>( [
	'heading',
	'paragraph',
	'divider',
	'html',
] );

/** Available width options. */
const WIDTH_OPTIONS: Array<{ value: FieldWidth; label: string }> = [
	{ value: 'full', label: 'Full Width' },
	{ value: 'half', label: 'Half (1/2)' },
	{ value: 'third', label: 'Third (1/3)' },
	{ value: 'two-thirds', label: 'Two-Thirds (2/3)' },
];

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

// Flush pending updates when the field changes
watch( () => props.field.id, () => {
	flushPendingUpdates();
} );

onUnmounted( () => {
	flushPendingUpdates();
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
	const newOptions = [
		...options.value,
		{ label: `Option ${options.value.length + 1}`, value: `option_${options.value.length + 1}` },
	];
	updateField( { field_config: { ...( props.field.field_config ?? {} ), options: newOptions } } );
}

function handleUpdateOption( index: number, key: 'label' | 'value', value: string ): void {
	const newOptions = options.value.map( ( opt, i ) =>
		i === index ? { ...opt, [key]: value } : opt,
	);
	updateField( { field_config: { ...( props.field.field_config ?? {} ), options: newOptions } } );
}

function handleRemoveOption( index: number ): void {
	const newOptions = options.value.filter( ( _, i ) => i !== index );
	updateField( { field_config: { ...( props.field.field_config ?? {} ), options: newOptions } } );
}

// ---------------------------------------------------------------------------
// Validation rules
// ---------------------------------------------------------------------------

const validationRules = computed(
	(): ValidationRules => props.field.validation_rules ?? {},
);

function updateValidation( rules: Partial<ValidationRules> ): void {
	updateField( { validation_rules: { ...validationRules.value, ...rules } } );
}

// ---------------------------------------------------------------------------
// Tabs
// ---------------------------------------------------------------------------

const tabs = computed( () => {
	const items = [
		{ name: 'general', label: 'General' },
	];

	if ( !isLayoutField.value ) {
		items.push( { name: 'validation', label: 'Validation' } );
		items.push( { name: 'conditional', label: 'Conditional' } );
	}

	return items;
} );

// ---------------------------------------------------------------------------
// Helpers for text field type checks used in validation tab
// ---------------------------------------------------------------------------

const isTextField = computed( () => {
	const textTypes: FieldType[] = ['text', 'email', 'url', 'textarea', 'phone'];

	return textTypes.includes( props.field.type );
} );

const isPatternField = computed( () => {
	const patternTypes: FieldType[] = ['text', 'phone'];

	return patternTypes.includes( props.field.type );
} );
</script>

<template>
	<div :class="['flex flex-col gap-0', props.class]">
		<!-- Header -->
		<div class="flex items-center justify-between p-3 border-b border-base-300">
			<h3 class="text-sm font-semibold">
				Edit {{ field.type.replace( '_', ' ' ) }} Field
			</h3>
			<Button
				color="ghost"
				size="sm"
				class="btn-circle"
				aria-label="Close field editor"
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
					<!-- Label -->
					<Input
						label="Label"
						:model-value="field.label ?? ''"
						@update:model-value="updateField( { label: ($event as string) } )"
					/>

					<!-- Name (machine name) -->
					<Input
						label="Name"
						:model-value="field.name"
						hint="Machine-readable name. Used as the form data key."
						@update:model-value="updateField( { name: ($event as string) } )"
					/>

					<!-- Placeholder (non-layout only) -->
					<Input
						v-if="!isLayoutField && field.type !== 'checkbox' && field.type !== 'radio'"
						label="Placeholder"
						:model-value="field.placeholder ?? ''"
						@update:model-value="updateField( { placeholder: ($event as string) || null } )"
					/>

					<!-- Help text -->
					<Input
						v-if="!isLayoutField"
						label="Help Text"
						:model-value="field.help_text ?? ''"
						@update:model-value="updateField( { help_text: ($event as string) || null } )"
					/>

					<!-- Default value -->
					<Input
						v-if="!isLayoutField && field.type !== 'file'"
						label="Default Value"
						:model-value="field.default_value ?? ''"
						@update:model-value="updateField( { default_value: ($event as string) || null } )"
					/>

					<!-- Required toggle -->
					<Checkbox
						v-if="!isLayoutField"
						label="Required"
						:model-value="field.is_required"
						@update:model-value="updateField( { is_required: ($event as boolean) } )"
					/>

					<!-- Width -->
					<Select
						label="Width"
						:model-value="field.width"
						:options="WIDTH_OPTIONS"
						option-value="value"
						option-label="label"
						@update:model-value="updateField( { width: ($event as FieldWidth) } )"
					/>

					<!-- CSS Classes -->
					<Input
						label="CSS Classes"
						:model-value="field.css_classes ?? ''"
						@update:model-value="updateField( { css_classes: ($event as string) || null } )"
					/>

					<!-- HTML Content (for html field type) -->
					<Textarea
						v-if="field.type === 'html'"
						label="HTML Content"
						:rows="6"
						:model-value="field.default_value ?? ''"
						@update:model-value="updateField( { default_value: ($event as string) } )"
					/>

					<!-- Heading/Paragraph content -->
					<template v-if="field.type === 'heading' || field.type === 'paragraph'">
						<Input
							v-if="field.type === 'heading'"
							label="Content"
							:model-value="field.default_value ?? ''"
							@update:model-value="updateField( { default_value: ($event as string) } )"
						/>
						<Textarea
							v-else
							label="Content"
							:rows="4"
							:model-value="field.default_value ?? ''"
							@update:model-value="updateField( { default_value: ($event as string) } )"
						/>
					</template>

					<!-- Options editor for choice fields -->
					<div v-if="hasOptions" class="space-y-2">
						<label class="label">
							<span class="label-text font-medium">Options</span>
						</label>
						<div
							v-for="( option, index ) in options"
							:key="index"
							class="flex items-center gap-2"
						>
							<Input
								placeholder="Label"
								:model-value="option.label"
								class="flex-1"
								@update:model-value="handleUpdateOption( index, 'label', ($event as string) )"
							/>
							<Input
								placeholder="Value"
								:model-value="option.value"
								class="flex-1"
								@update:model-value="handleUpdateOption( index, 'value', ($event as string) )"
							/>
							<Button
								color="ghost"
								size="sm"
								class="btn-circle"
								title="Remove option"
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
							Add Option
						</Button>
					</div>

					<!-- File field config -->
					<div v-if="field.type === 'file'" class="space-y-4">
						<Input
							label="Max File Size (KB)"
							type="number"
							:min="0"
							:model-value="String( validationRules.max_size ?? '' )"
							@update:model-value="updateValidation( { max_size: parseNumericInput( ($event as string) ) } )"
						/>
						<Input
							label="Allowed File Types (comma-separated MIME types)"
							:model-value="validationRules.allowed_types?.join( ', ' ) ?? ''"
							@update:model-value="updateValidation( {
								allowed_types: ($event as string)
									? ($event as string).split( ',' ).map( ( s: string ) => s.trim() )
									: undefined,
							} )"
						/>
					</div>
				</div>
			</template>

			<!-- Validation Tab -->
			<template #validation>
				<div class="space-y-4 p-4">
					<!-- Min/Max for text fields -->
					<template v-if="isTextField">
						<Input
							label="Min Length"
							type="number"
							:min="0"
							:model-value="String( validationRules.min ?? '' )"
							@update:model-value="updateValidation( { min: parseNumericInput( ($event as string) ) } )"
						/>
						<Input
							label="Max Length"
							type="number"
							:min="0"
							:model-value="String( validationRules.max ?? '' )"
							@update:model-value="updateValidation( { max: parseNumericInput( ($event as string) ) } )"
						/>
					</template>

					<!-- Min/Max/Step for number -->
					<template v-if="field.type === 'number'">
						<Input
							label="Minimum"
							type="number"
							:model-value="String( validationRules.min ?? '' )"
							@update:model-value="updateValidation( { min: parseNumericInput( ($event as string) ) } )"
						/>
						<Input
							label="Maximum"
							type="number"
							:model-value="String( validationRules.max ?? '' )"
							@update:model-value="updateValidation( { max: parseNumericInput( ($event as string) ) } )"
						/>
						<Input
							label="Step"
							type="number"
							:min="0"
							:model-value="String( validationRules.step ?? '' )"
							@update:model-value="updateValidation( { step: parseNumericInput( ($event as string) ) } )"
						/>
					</template>

					<!-- Date range -->
					<template v-if="field.type === 'date'">
						<Input
							label="Min Date"
							type="date"
							:model-value="validationRules.min_date ?? ''"
							@update:model-value="updateValidation( { min_date: ($event as string) || undefined } )"
						/>
						<Input
							label="Max Date"
							type="date"
							:model-value="validationRules.max_date ?? ''"
							@update:model-value="updateValidation( { max_date: ($event as string) || undefined } )"
						/>
					</template>

					<!-- Time range -->
					<template v-if="field.type === 'time'">
						<Input
							label="Min Time"
							type="time"
							:model-value="validationRules.min_time ?? ''"
							@update:model-value="updateValidation( { min_time: ($event as string) || undefined } )"
						/>
						<Input
							label="Max Time"
							type="time"
							:model-value="validationRules.max_time ?? ''"
							@update:model-value="updateValidation( { max_time: ($event as string) || undefined } )"
						/>
					</template>

					<!-- Pattern -->
					<Input
						v-if="isPatternField"
						label="Pattern (RegExp)"
						:model-value="validationRules.pattern ?? ''"
						placeholder="e.g. ^[A-Z]{2}[0-9]{4}$"
						hint="Regular expression pattern for input validation."
						@update:model-value="updateValidation( { pattern: ($event as string) || undefined } )"
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
				@click="emit( 'duplicate', field.id )"
			>
				Duplicate
			</Button>
			<Button
				color="error"
				size="sm"
				@click="() => {
					if ( window.confirm( 'Are you sure you want to delete this field?' ) ) {
						emit( 'delete', field.id );
					}
				}"
			>
				Delete
			</Button>
		</div>
	</div>
</template>
