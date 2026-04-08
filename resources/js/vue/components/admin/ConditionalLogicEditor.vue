<!--
  ConditionalLogicEditor component.

  Standalone visual rule builder for field/step visibility conditions.
  Can be used independently or embedded within the FieldEditor or
  NotificationEditor components.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { Button, Input, Select } from '@artisanpack-ui/vue';

import type {
	ConditionalLogicRule,
	ConditionalLogicType,
	ConditionalOperator,
	FormField,
} from '../../../types/artisanpack-forms';

/** Generic conditional logic shape that accepts any action string. */
export interface GenericConditionalLogic {
	action: string;
	logic: ConditionalLogicType;
	rules: ConditionalLogicRule[];
}

/** Operator definitions with metadata. */
const OPERATOR_DEFINITIONS: Array<{
	value: ConditionalOperator;
	label: string;
	needsValue: boolean;
}> = [
	{ value: 'equals', label: 'Equals', needsValue: true },
	{ value: 'not_equals', label: 'Does not equal', needsValue: true },
	{ value: 'contains', label: 'Contains', needsValue: true },
	{ value: 'not_contains', label: 'Does not contain', needsValue: true },
	{ value: 'starts_with', label: 'Starts with', needsValue: true },
	{ value: 'ends_with', label: 'Ends with', needsValue: true },
	{ value: 'is_empty', label: 'Is empty', needsValue: false },
	{ value: 'is_not_empty', label: 'Is not empty', needsValue: false },
	{ value: 'greater_than', label: 'Greater than', needsValue: true },
	{ value: 'less_than', label: 'Less than', needsValue: true },
	{ value: 'greater_or_equal', label: 'Greater or equal', needsValue: true },
	{ value: 'less_or_equal', label: 'Less or equal', needsValue: true },
	{ value: 'in', label: 'Is one of', needsValue: true },
	{ value: 'not_in', label: 'Is not one of', needsValue: true },
	{ value: 'checked', label: 'Is checked', needsValue: false },
	{ value: 'unchecked', label: 'Is unchecked', needsValue: false },
	{ value: 'includes', label: 'Includes', needsValue: true },
	{ value: 'not_includes', label: 'Does not include', needsValue: true },
];

/** Layout field types excluded from condition targets. */
const LAYOUT_TYPES = new Set( ['heading', 'paragraph', 'divider', 'html'] );

const props = withDefaults( defineProps<{
	/** Current conditional logic configuration, or null. */
	value: GenericConditionalLogic | null;
	/** All fields available as condition targets. */
	fields: FormField[];
	/** ID of the current field (excluded from targets). */
	excludeFieldId?: number;
	/** Available actions. Defaults to [{ value: 'show', label: 'Show' }, { value: 'hide', label: 'Hide' }]. */
	actions?: Array<{ value: string; label: string }>;
	/** Label for the action dropdown. Defaults to 'this field'. */
	actionLabel?: string;
}>(), {
	excludeFieldId: undefined,
	actions: undefined,
	actionLabel: 'this field',
} );

const emit = defineEmits<{
	'update:value': [logic: GenericConditionalLogic | null];
}>();

const availableActions = computed( () =>
	props.actions ?? [
		{ value: 'show', label: 'Show' },
		{ value: 'hide', label: 'Hide' },
	],
);

const availableFields = computed( () =>
	props.fields.filter( ( f ) =>
		( props.excludeFieldId === undefined || f.id !== props.excludeFieldId ) &&
		!LAYOUT_TYPES.has( f.type ),
	),
);

const defaultLogic = computed( (): GenericConditionalLogic => ( {
	action: availableActions.value[0].value,
	logic: 'all' as ConditionalLogicType,
	rules: [],
} ) );

const logic = computed( (): GenericConditionalLogic =>
	props.value ?? defaultLogic.value,
);

function handleActionChange( action: string ): void {
	emit( 'update:value', { ...logic.value, action } );
}

function handleLogicTypeChange( logicType: string ): void {
	emit( 'update:value', { ...logic.value, logic: logicType as ConditionalLogicType } );
}

function handleAddRule(): void {
	const firstField = availableFields.value[0];

	if ( !firstField ) {
		return;
	}

	const newRule: ConditionalLogicRule = {
		field: firstField.uuid,
		operator: 'equals',
		value: '',
	};

	emit( 'update:value', { ...logic.value, rules: [...logic.value.rules, newRule] } );
}

function handleUpdateRule( index: number, updates: Partial<ConditionalLogicRule> ): void {
	const newRules = logic.value.rules.map( ( rule, i ) =>
		i === index ? { ...rule, ...updates } : rule,
	);
	emit( 'update:value', { ...logic.value, rules: newRules } );
}

function handleRemoveRule( index: number ): void {
	const newRules = logic.value.rules.filter( ( _, i ) => i !== index );

	if ( newRules.length === 0 ) {
		emit( 'update:value', null );
	} else {
		emit( 'update:value', { ...logic.value, rules: newRules } );
	}
}

function handleClearAll(): void {
	emit( 'update:value', null );
}

function needsValue( operator: ConditionalOperator ): boolean {
	const def = OPERATOR_DEFINITIONS.find( ( d ) => d.value === operator );

	return def?.needsValue ?? true;
}

function getFieldOptions( fieldUuid: string ): Array<{ label: string; value: string }> | null {
	const field = props.fields.find( ( f ) => f.uuid === fieldUuid );

	if ( !field ) {
		return null;
	}

	const options = field.options ??
		( field.field_config as { options?: Array<{ label: string; value: string }> } )?.options;

	return options && options.length > 0 ? options : null;
}
</script>

<template>
	<div class="space-y-3">
		<template v-if="logic.rules.length > 0">
			<!-- Action and logic type config -->
			<div class="flex flex-wrap items-center gap-2 text-sm">
				<Select
					:model-value="logic.action"
					size="sm"
					:options="availableActions"
					option-value="value"
					option-label="label"
					@update:model-value="handleActionChange( $event as string )"
				/>
				<span class="text-base-content">{{ actionLabel }} when</span>
				<Select
					:model-value="logic.logic"
					size="sm"
					:options="[
						{ value: 'all', label: 'all' },
						{ value: 'any', label: 'any' },
					]"
					option-value="value"
					option-label="label"
					@update:model-value="handleLogicTypeChange( $event as string )"
				/>
				<span class="text-base-content">of the following rules match:</span>
			</div>

			<!-- Rules -->
			<div class="space-y-2">
				<div
					v-for="( rule, index ) in logic.rules"
					:key="index"
					class="flex flex-wrap items-center gap-2"
				>
					<!-- Field selector -->
					<Select
						:model-value="rule.field"
						size="sm"
						:options="availableFields.map( ( f ) => ( {
							value: f.uuid,
							label: f.label || f.name,
						} ) )"
						option-value="value"
						option-label="label"
						@update:model-value="handleUpdateRule( index, { field: $event as string } )"
					/>

					<!-- Operator selector -->
					<Select
						:model-value="rule.operator"
						size="sm"
						:options="OPERATOR_DEFINITIONS.map( ( op ) => ( {
							value: op.value,
							label: op.label,
						} ) )"
						option-value="value"
						option-label="label"
						@update:model-value="handleUpdateRule( index, {
							operator: $event as ConditionalOperator,
						} )"
					/>

					<!-- Value input -->
					<template v-if="needsValue( rule.operator )">
						<Select
							v-if="getFieldOptions( rule.field )"
							:model-value="String( rule.value ?? '' )"
							size="sm"
							:options="[
								{ value: '', label: 'Select...' },
								...( getFieldOptions( rule.field ) ?? [] ).map( ( opt ) => ( {
									value: opt.value,
									label: opt.label,
								} ) ),
							]"
							option-value="value"
							option-label="label"
							@update:model-value="handleUpdateRule( index, { value: $event as string } )"
						/>
						<Input
							v-else
							type="text"
							:model-value="String( rule.value ?? '' )"
							placeholder="Value"
							size="sm"
							@update:model-value="handleUpdateRule( index, { value: $event } )"
						/>
					</template>

					<!-- Remove button -->
					<Button
						color="ghost"
						size="sm"
						class="btn-circle"
						title="Remove rule"
						aria-label="Remove rule"
						@click="handleRemoveRule( index )"
					>
						&times;
					</Button>
				</div>
			</div>
		</template>

		<!-- Actions -->
		<div class="flex items-center gap-2">
			<Button
				color="ghost"
				size="sm"
				:disabled="availableFields.length === 0"
				@click="handleAddRule"
			>
				+ Add Rule
			</Button>
			<Button
				v-if="logic.rules.length > 0"
				color="outline"
				size="sm"
				@click="handleClearAll"
			>
				Clear All Rules
			</Button>
		</div>

		<p
			v-if="availableFields.length === 0"
			class="text-sm text-base-content/50"
		>
			Add more non-layout fields to the form to create conditional rules.
		</p>
	</div>
</template>
