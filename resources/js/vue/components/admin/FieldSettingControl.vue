<!--
  FieldSettingControl component.

  Renders one type-specific setting from a field's `settings_schema` (see
  `FieldSettings` on the server) and emits the field update that stores it.
  Text, textarea, number, list, date and time inputs keep a local draft so
  typing is not rewritten by parsing (a trailing comma in a list, a
  half-typed number). A calculation formula (since 1.6.0) has a picker that
  inserts a field's merge tag at the cursor, and shows the first problem
  with the formula.

  @package    ArtisanPack_UI
  @subpackage Forms

  @since      1.6.0
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Checkbox, Input, Select } from '@artisanpack-ui/vue';

import type {
	FieldSettingDefinition,
	FormField,
	UpdateFieldRequest,
} from '../../../types/artisanpack-forms';

import { checkFormula, getFormulaFields, insertFormulaTag } from '../../../shared/calculation';
import { buildSettingUpdate, getSettingInput, isDraftStored } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';

const props = defineProps<{
	/** The field being edited, with buffered edits applied. */
	field: FormField;
	/** The setting to render. */
	setting: FieldSettingDefinition;
	/** The form's fields, for a formula's field picker (since 1.6.0). */
	allFields?: FormField[];
}>();

const emit = defineEmits<{
	update: [data: UpdateFieldRequest];
}>();

/** The HTML input type for text-like settings. */
const inputType = computed( (): string => {
	switch ( props.setting.input ) {
		case 'integer':
		case 'number':
			return 'number';
		case 'date':
			return 'date';
		case 'time':
			return 'time';
		default:
			return 'text';
	}
} );

/** The `step` attribute for number inputs. */
const inputStep = computed( (): number | string | undefined => {
	if ( props.setting.input === 'number' ) {
		return props.setting.step ?? 'any';
	}

	return props.setting.input === 'integer' ? 1 : undefined;
} );

const draft = ref<string>( String( getSettingInput( props.field, props.setting ) ) );

// Reset the draft when a different field or setting is shown.
watch( [() => props.field.id, () => props.setting.key], () => {
	draft.value = String( getSettingInput( props.field, props.setting ) );
} );

// Follow the stored value when it moves away from the draft (a rejected
// update was reverted, or the server stored something else).
watch( () => String( getSettingInput( props.field, props.setting ) ), ( stored ) => {
	if ( ! isDraftStored( props.field, props.setting, draft.value ) ) {
		draft.value = stored;
	}
} );

const checked = computed( (): boolean => getSettingInput( props.field, props.setting ) === true );
const selected = computed( (): string => String( getSettingInput( props.field, props.setting ) ) );

function handleDraftInput( value: unknown ): void {
	draft.value = String( value ?? '' );
	emit( 'update', buildSettingUpdate( props.field, props.setting, draft.value ) );
}

/** The IDs a textarea setting's label and hint use (since 1.6.0). */
const textareaId = computed( (): string => `field-setting-${props.field.id}-${props.setting.key}` );
const textareaHintId = computed( (): string | undefined => ( props.setting.hint ? `${textareaId.value}-hint` : undefined ) );

/** The fields a formula may use, and the formula's first problem (since 1.6.0). */
const formulaInput = ref<HTMLTextAreaElement | null>( null );
const formulaFields = computed( () => ( props.setting.input === 'formula' ? getFormulaFields( props.field, props.allFields ?? [] ) : [] )
	.map( ( entry ) => ( { ...entry, tag: `{${entry.name}}` } ) ) );
const formulaProblem = computed( (): string | undefined => {
	if ( props.setting.input !== 'formula' || draft.value.trim() === '' ) {
		return undefined;
	}

	return checkFormula( draft.value, formulaFields.value.map( ( entry ) => entry.name ) );
} );
const formulaDescribedBy = computed( (): string | undefined => [textareaHintId.value, formulaProblem.value ? `${textareaId.value}-problem` : undefined].filter( Boolean ).join( ' ' ) || undefined );

function insertFormulaField( event: Event ): void {
	const select = event.target as HTMLSelectElement;

	if ( select.value !== '' && formulaInput.value ) {
		handleDraftInput( insertFormulaTag( formulaInput.value, select.value ) );
	}

	select.value = '';
}

function handleChange( value: unknown ): void {
	emit( 'update', buildSettingUpdate( props.field, props.setting, value ) );
}
</script>

<template>
	<Checkbox
		v-if="setting.input === 'toggle'"
		:label="setting.label"
		:hint="setting.hint"
		:model-value="checked"
		@update:model-value="handleChange( $event )"
	/>
	<Select
		v-else-if="setting.input === 'select'"
		:label="setting.label"
		:hint="setting.hint"
		:model-value="selected"
		:options="setting.options ?? []"
		option-value="value"
		option-label="label"
		@update:model-value="handleChange( $event )"
	/>
	<div v-else-if="setting.input === 'textarea'" class="fieldset">
		<label :for="textareaId" class="fieldset-legend">{{ setting.label }}</label>
		<textarea
			:id="textareaId"
			class="textarea textarea-sm textarea-bordered w-full"
			rows="4"
			:maxlength="setting.max_length"
			:placeholder="setting.placeholder"
			:aria-describedby="textareaHintId"
			:value="draft"
			@input="handleDraftInput( ( $event.target as HTMLTextAreaElement ).value )"
		/>
		<p v-if="textareaHintId" :id="textareaHintId" class="fieldset-label">{{ setting.hint }}</p>
	</div>
	<div v-else-if="setting.input === 'formula'" class="fieldset">
		<label :for="textareaId" class="fieldset-legend">{{ setting.label }}</label>
		<textarea
			:id="textareaId"
			ref="formulaInput"
			:class="['textarea textarea-sm textarea-bordered w-full font-mono text-xs', formulaProblem ? 'textarea-error' : '']"
			rows="3"
			spellcheck="false"
			:maxlength="setting.max_length"
			:placeholder="setting.placeholder"
			:aria-invalid="formulaProblem ? 'true' : undefined"
			:aria-describedby="formulaDescribedBy"
			:value="draft"
			@input="handleDraftInput( ( $event.target as HTMLTextAreaElement ).value )"
		/>
		<template v-if="formulaFields.length > 0">
			<label :for="`${textareaId}-insert`" class="sr-only">{{ t( 'Insert a field' ) }}</label>
			<select
				:id="`${textareaId}-insert`"
				class="select select-sm select-bordered mt-2 w-full"
				@change="insertFormulaField"
			>
				<option value="">{{ t( 'Insert a field…' ) }}</option>
				<option v-for="entry in formulaFields" :key="entry.name" :value="entry.tag">
					{{ entry.label }} ({{ entry.tag }})
				</option>
			</select>
		</template>
		<p v-if="textareaHintId" :id="textareaHintId" class="fieldset-label">{{ setting.hint }}</p>
		<p v-if="formulaProblem" :id="`${textareaId}-problem`" class="fieldset-label text-error">{{ formulaProblem }}</p>
	</div>
	<Input
		v-else
		:label="setting.label"
		:hint="setting.hint"
		:placeholder="setting.placeholder"
		:type="inputType"
		:min="setting.min"
		:max="setting.max"
		:step="inputStep"
		:class="setting.monospace ? 'font-mono' : undefined"
		:model-value="draft"
		@update:model-value="handleDraftInput( $event )"
	/>
</template>
