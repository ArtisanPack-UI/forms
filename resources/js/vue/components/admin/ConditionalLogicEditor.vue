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
import { getConditionTargets } from '../../../shared/composite';
import { getConditionOptions } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';

/** Generic conditional logic shape that accepts any action string. */
export interface GenericConditionalLogic {
	action: string;
	logic: ConditionalLogicType;
	rules: ConditionalLogicRule[];
}

/** Operator definitions with metadata, translated at render. */
const OPERATOR_DEFINITIONS = computed( (): Array<{
	value: ConditionalOperator;
	label: string;
	needsValue: boolean;
}> => [
	{ value: 'equals', label: t( 'Equals' ), needsValue: true },
	{ value: 'not_equals', label: t( 'Does not equal' ), needsValue: true },
	{ value: 'contains', label: t( 'Contains' ), needsValue: true },
	{ value: 'not_contains', label: t( 'Does not contain' ), needsValue: true },
	{ value: 'starts_with', label: t( 'Starts with' ), needsValue: true },
	{ value: 'ends_with', label: t( 'Ends with' ), needsValue: true },
	{ value: 'is_empty', label: t( 'Is empty' ), needsValue: false },
	{ value: 'is_not_empty', label: t( 'Is not empty' ), needsValue: false },
	{ value: 'greater_than', label: t( 'Greater than' ), needsValue: true },
	{ value: 'less_than', label: t( 'Less than' ), needsValue: true },
	{ value: 'greater_or_equal', label: t( 'Greater or equal' ), needsValue: true },
	{ value: 'less_or_equal', label: t( 'Less or equal' ), needsValue: true },
	{ value: 'in', label: t( 'Is one of' ), needsValue: true },
	{ value: 'not_in', label: t( 'Is not one of' ), needsValue: true },
	{ value: 'checked', label: t( 'Is checked' ), needsValue: false },
	{ value: 'unchecked', label: t( 'Is unchecked' ), needsValue: false },
	{ value: 'includes', label: t( 'Includes' ), needsValue: true },
	{ value: 'not_includes', label: t( 'Does not include' ), needsValue: true },
] );

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
	/** Label for the action dropdown. Defaults to 'this field' (translated). */
	actionLabel?: string;
}>(), {
	excludeFieldId: undefined,
	actions: undefined,
	actionLabel: undefined,
} );

const emit = defineEmits<{
	'update:value': [logic: GenericConditionalLogic | null];
}>();

const availableActions = computed( () =>
	props.actions ?? [
		{ value: 'show', label: t( 'Show' ) },
		{ value: 'hide', label: t( 'Hide' ) },
	],
);

const resolvedActionLabel = computed( () => props.actionLabel ?? t( 'this field' ) );

const logicTypeOptions = computed( () => [
	{ value: 'all', label: t( 'all' ) },
	{ value: 'any', label: t( 'any' ) },
] );

const availableFields = computed( () =>
	props.fields.filter( ( f ) =>
		( props.excludeFieldId === undefined || f.id !== props.excludeFieldId ) &&
		!LAYOUT_TYPES.has( f.type ),
	),
);

/** A composite field offers each of its enabled sub-inputs instead of itself. */
const availableTargets = computed( () => availableFields.value.flatMap( getConditionTargets ) );

const targetOptions = computed( () =>
	availableTargets.value.map( ( target ) => ( { value: target.ref, label: target.label } ) ),
);

const defaultLogic = computed( (): GenericConditionalLogic => ( {
	action: availableActions.value.length > 0 ? availableActions.value[0].value : '',
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
	const firstTarget = availableTargets.value[0];

	if ( !firstTarget ) {
		return;
	}

	const newRule: ConditionalLogicRule = {
		field: firstTarget.ref,
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
	const def = OPERATOR_DEFINITIONS.value.find( ( d ) => d.value === operator );

	return def?.needsValue ?? true;
}

function getFieldOptions( fieldRef: string ): Array<{ label: string; value: string }> | null {
	const target = availableTargets.value.find( ( entry ) => entry.ref === fieldRef );

	// A sub-input takes free text.
	if ( target?.subKey ) {
		return null;
	}

	const field = target?.field ?? props.fields.find( ( f ) => f.uuid === fieldRef );

	return field ? getConditionOptions( field ) : null;
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
				<span class="text-base-content">{{ t( ':subject when', { subject: resolvedActionLabel } ) }}</span>
				<Select
					:model-value="logic.logic"
					size="sm"
					:options="logicTypeOptions"
					option-value="value"
					option-label="label"
					@update:model-value="handleLogicTypeChange( $event as string )"
				/>
				<span class="text-base-content">{{ t( 'of the following rules match:' ) }}</span>
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
						:options="targetOptions"
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
								{ value: '', label: t( 'Select...' ) },
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
							:placeholder="t( 'Value' )"
							size="sm"
							@update:model-value="handleUpdateRule( index, { value: $event } )"
						/>
					</template>

					<!-- Remove button -->
					<Button
						color="ghost"
						size="sm"
						class="btn-circle"
						:title="t( 'Remove rule' )"
						:aria-label="t( 'Remove rule' )"
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
				{{ t( '+ Add Rule' ) }}
			</Button>
			<Button
				v-if="logic.rules.length > 0"
				color="outline"
				size="sm"
				@click="handleClearAll"
			>
				{{ t( 'Clear All Rules' ) }}
			</Button>
		</div>

		<p
			v-if="availableFields.length === 0"
			class="text-sm text-base-content/50"
		>
			{{ t( 'Add more non-layout fields to the form to create conditional rules.' ) }}
		</p>
	</div>
</template>
