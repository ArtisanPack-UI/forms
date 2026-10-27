<!--
  Text-based field components wrapper.

  Renders text, email, phone, number, url, textarea, hidden,
  and time input fields using @artisanpack-ui/vue components. Inputs expose
  the field's `autocomplete` token (WCAG 1.3.5). Text and phone inputs apply
  their input mask, text and textarea fields can show a live counter, phone
  fields offer a country selector and number fields a format, prefix and
  suffix, and a URL typed without a scheme gets `https://` when the input
  loses focus (since 1.6.0). Rich text textareas and time dropdowns have
  components of their own (see `FieldRenderer.vue`).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Input, Textarea } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';
import { getLengthLimit } from '../../../shared/counter';
import {
	getFieldLayout,
	getInputLabelProps,
	getInputNoteIds,
	getLayoutDescribedBy,
	getTextareaRows,
	getTimeStep,
	isReadOnlyField,
	withInputNotes,
} from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { applyMask, getInputMask } from '../../../shared/mask';
import { getAffixes, getNumberFormat, isIntegerOnly } from '../../../shared/numberFormat';
import { getAllowedUrlSchemes, withUrlScheme } from '../../../shared/url';
import {
	composePhoneNumber,
	findPhoneCountry,
	getPhoneCountries,
	getPhoneDefaultCountry,
	splitPhoneNumber,
} from '../../../shared/phone';
import FieldLayout from './FieldLayout.vue';
import InputNotes from './InputNotes.vue';
import { vAutocomplete } from './autocomplete';
import { vDescribedBy } from './describedBy';
import { vInputAttrs } from './inputAttrs';
import { fieldIdKey } from '../../../shared/domIds';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
}>();

const stringValue = computed( () => String( props.modelValue ?? '' ) );

/** Read-only fields stay focusable and announced, but cannot be edited. */
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** The IDs of the input's notes: mask format, counter, prefix and suffix (since 1.6.0). */
const noteIds = computed( () => getInputNoteIds( props.field ) );

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

/** The types that show input notes under their input (since 1.6.0). */
const NOTED_TYPES = new Set( ['text', 'phone', 'number', 'textarea'] );

/** A text or phone field's input mask (since 1.6.0). */
const mask = computed( () => getInputMask( props.field ) );

/** Format a typed value with the field's mask, if it has one. */
function masked( text: string, previous: string ): string {
	return mask.value ? applyMask( text, mask.value, previous ) : text;
}

/** A text or textarea field's length limits, for `minlength` and `maxlength` (since 1.6.0). */
const lengthAttrs = computed( () => ( {
	minlength: getLengthLimit( props.field, 'min' ),
	maxlength: getLengthLimit( props.field, 'max' ),
} ) );

/** An International phone field's countries, and the one selected (since 1.6.0). */
const phoneCountries = computed( () => getPhoneCountries( props.field ) );
const phoneCountryCode = ref( findPhoneCountry( stringValue.value, phoneCountries.value, getPhoneDefaultCountry( props.field ) )?.value );
const phoneCountry = computed( () => phoneCountries.value.find( ( country ) => country.value === phoneCountryCode.value ) );

/** The number typed, without the selected country's calling code. */
const phoneNational = computed( () => splitPhoneNumber( stringValue.value, phoneCountry.value ) );

/** Submit a typed phone number with the selected country's calling code. */
function updatePhone( text: string ): void {
	emit( 'update:modelValue', composePhoneNumber( masked( text, phoneNational.value ), phoneCountry.value ) );
}

/** Select a country, keeping the number typed. */
function selectPhoneCountry( code: string ): void {
	const national = phoneNational.value;

	phoneCountryCode.value = code;
	emit( 'update:modelValue', composePhoneNumber( national, phoneCountry.value ) );
}

/** A number field's format, prefix and suffix (since 1.6.0). */
const numberFormat = computed( () => getNumberFormat( props.field ) );
const affixes = computed( () => getAffixes( props.field ) );
const numberAttrs = computed( () => ( {
	inputmode: numberFormat.value ? ( isIntegerOnly( props.field ) ? 'numeric' : 'decimal' ) : undefined,
} ) );
/** Add a scheme to a URL typed without one, as the server does before validation (since 1.6.0). */
function addUrlScheme( event: FocusEvent ): void {
	const typed = ( event.target as HTMLInputElement | null )?.value ?? stringValue.value;
	const withScheme = withUrlScheme( typed, getAllowedUrlSchemes( props.field ) );

	if ( withScheme !== typed ) {
		emit( 'update:modelValue', withScheme );
	}
}

const numberStep = computed( () =>
	props.field.validation_rules?.step ?? ( isIntegerOnly( props.field ) ? 1 : undefined ),
);
</script>

<template>
	<FieldLayout :field="field" :layout="layout">
		<!-- Text -->
		<Input
			v-if="field.type === 'text'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="text"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			v-input-attrs="lengthAttrs"
			:pattern="field.validation_rules?.pattern ?? undefined"
			@update:model-value="emit( 'update:modelValue', masked( String( $event ?? '' ), stringValue ) )"
		/>

		<!-- Email -->
		<Input
			v-else-if="field.type === 'email'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="email"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Phone: an International field puts a country selector before the input (since 1.6.0) -->
		<div v-else-if="field.type === 'phone'" :class="{ 'flex items-end gap-2': phoneCountries.length > 0 }">
			<select
				v-if="phoneCountries.length > 0"
				:id="`field-${fieldIdKey( field )}-country`"
				class="select select-bordered w-auto max-w-48"
				:value="phoneCountryCode ?? ''"
				autocomplete="country"
				:aria-label="t( ':field country', { field: field.label ?? field.name } )"
				:disabled="readOnly"
				@change="selectPhoneCountry( ( $event.target as HTMLSelectElement ).value )"
			>
				<option v-for="country in phoneCountries" :key="country.value" :value="country.value">{{ country.label }}</option>
			</select>
			<Input
				v-autocomplete="field.autocomplete"
				v-bind="labelProps"
				v-described-by="describedBy"
				class="grow"
				:name="field.name"
				type="tel"
				:model-value="phoneNational"
				:placeholder="field.placeholder ?? undefined"
				:error="error"
				:required="field.is_required"
				:readonly="readOnly"
				:class="field.css_classes ?? undefined"
				:pattern="field.validation_rules?.pattern ?? undefined"
				@update:model-value="updatePhone( String( $event ?? '' ) )"
			/>
		</div>

		<!-- Number: a formatted number is typed into a text input with a numeric keypad (since 1.6.0) -->
		<Input
			v-else-if="field.type === 'number'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			v-input-attrs="numberAttrs"
			:name="field.name"
			:type="numberFormat ? 'text' : 'number'"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:min="numberFormat ? undefined : field.validation_rules?.min"
			:max="numberFormat ? undefined : field.validation_rules?.max"
			:step="numberFormat ? undefined : numberStep"
			@update:model-value="emit( 'update:modelValue', $event )"
		>
			<template v-if="affixes.prefix" #prefix>{{ affixes.prefix }}</template>
			<template v-if="affixes.suffix" #suffix>{{ affixes.suffix }}</template>
		</Input>

		<!-- URL -->
		<Input
			v-else-if="field.type === 'url'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="url"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? 'https://'"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
			@focusout="addUrlScheme"
		/>

		<!-- Textarea -->
		<Textarea
			v-else-if="field.type === 'textarea'"
			v-autocomplete="field.autocomplete"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? undefined"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			v-input-attrs="lengthAttrs"
			:rows="getTextareaRows( field )"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Hidden -->
		<input
			v-else-if="field.type === 'hidden'"
			type="hidden"
			:name="field.name"
			:value="stringValue"
			readonly
		/>

		<!-- Time -->
		<Input
			v-else-if="field.type === 'time'"
			v-bind="labelProps"
			v-described-by="describedBy"
			:name="field.name"
			type="time"
			:model-value="stringValue"
			:error="error"
			:required="field.is_required"
			:readonly="readOnly"
			:class="field.css_classes ?? undefined"
			:min="field.validation_rules?.min_time ?? undefined"
			:max="field.validation_rules?.max_time ?? undefined"
			:step="getTimeStep( field )"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>

		<!-- Mask format, counter, prefix and suffix notes (since 1.6.0) -->
		<InputNotes
			v-if="NOTED_TYPES.has( field.type )"
			:field="field"
			:value="field.type === 'phone' ? phoneNational : stringValue"
		/>
	</FieldLayout>
</template>
