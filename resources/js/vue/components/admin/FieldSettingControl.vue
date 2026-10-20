<!--
  FieldSettingControl component.

  Renders one type-specific setting from a field's `settings_schema` (see
  `FieldSettings` on the server) and emits the field update that stores it.
  Text, number, list, date and time inputs keep a local draft so typing is
  not rewritten by parsing (a trailing comma in a list, a half-typed number).

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

import { buildSettingUpdate, getSettingInput } from '../../../shared/fieldSettings';

const props = defineProps<{
	/** The field being edited, with buffered edits applied. */
	field: FormField;
	/** The setting to render. */
	setting: FieldSettingDefinition;
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

const checked = computed( (): boolean => getSettingInput( props.field, props.setting ) === true );
const selected = computed( (): string => String( getSettingInput( props.field, props.setting ) ) );

function handleDraftInput( value: unknown ): void {
	draft.value = String( value ?? '' );
	emit( 'update', buildSettingUpdate( props.field, props.setting, draft.value ) );
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
