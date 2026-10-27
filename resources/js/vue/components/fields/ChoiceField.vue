<!--
  Choice-based field components wrapper.

  Renders select, radio, checkbox, toggle, checkbox_group, and select_multiple
  fields using @artisanpack-ui/vue components. Radio and checkbox groups can
  offer an "Other" choice that reveals a text input, arrange their choices in
  columns or inline and show them in a random order; a checkbox group can
  offer "Select All". Radios are native inputs in a labelled `radiogroup`, as
  in React. A searchable select or multi-select renders a combobox (since 1.6.0).
  Radio and checkbox groups can show their choices as image cards, each named
  by its label (`ImageChoice`, since 1.6.0).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, ref, toRaw, watchEffect } from 'vue';
import { Checkbox, Select, Toggle } from '@artisanpack-ui/vue/form';
import type { FormField, DisplayConfig, FieldOption } from '../../../types/artisanpack-forms';
import {
	getChoiceLayoutClasses,
	getFieldOptions,
	getRenderOptions,
	isSearchableField,
	offersSelectAll,
	showsImageChoices,
} from '../../../shared/choices';
import {
	allowsOtherChoice,
	getCheckLabelProps,
	getFieldLayout,
	getInputLabelProps,
	getLayoutDescribedBy,
	getOtherChoiceLabel,
	getToggleColor,
	getToggleSizeClass,
	isOtherChoiceSelected,
	isReadOnlyField,
	OTHER_CHOICE_MAX_LENGTH,
	OTHER_CHOICE_VALUE,
} from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import Combobox from './Combobox.vue';
import ImageChoice from './ImageChoice.vue';
import FieldLayout from './FieldLayout.vue';
import { vAutocomplete } from './autocomplete';
import { vDescribedBy } from './describedBy';
import { fieldIdKey } from '../../../shared/domIds';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
	/** Text typed into the field's "Other" choice (since 1.6.0). */
	otherValue?: string;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
	'update:otherValue': [text: string];
}>();

/**
 * The options, in the order they are shown. Radio and checkbox groups may
 * shuffle them, once per field object (hence `toRaw`).
 */
const options = computed<FieldOption[]>( () =>
	props.field.type === 'radio' || props.field.type === 'checkbox_group'
		? getRenderOptions( toRaw( props.field ) )
		: getFieldOptions( props.field ),
);

/** Whether a select or multi-select is a searchable combobox. */
const searchable = computed( () => isSearchableField( props.field ) );

/** Whether a radio or checkbox group shows its choices as image cards (since 1.6.0). */
const showsImages = computed( () => showsImageChoices( props.field ) );

/** The classes that arrange a radio or checkbox group's choices. */
const layoutClasses = computed( () => getChoiceLayoutClasses( props.field ) );

/** Read-only choices are announced as read-only and their inputs disabled. */
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** Whether the field offers an "Other" choice, which comes last. */
const allowsOther = computed( () => allowsOtherChoice( props.field ) );

/** The options, with the "Other" choice added when the field offers one. */
const choices = computed<FieldOption[]>( () =>
	allowsOther.value
		? [...options.value, { value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( props.field ) }]
		: options.value,
);

/** The values of the options "Select All" controls ("Other" is left alone). */
const optionValues = computed( () => options.value.map( ( option ) => option.value ) );

/** Whether a checkbox group shows "Select All". */
const showSelectAll = computed( () => offersSelectAll( props.field ) && optionValues.value.length > 1 );

/** Whether the "Other" choice is selected, revealing its text input. */
const otherSelected = computed( () => allowsOther.value && isOtherChoiceSelected( props.modelValue ) );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

/** The select's label, hint and ID props; `aria-describedby` is set by `v-described-by`. */
const selectLabelProps = computed( () => {
	const { 'aria-describedby': _describedBy, ...rest } = getInputLabelProps( props.field, layout.value, props.displayConfig, props.error );

	return rest;
} );

/** A single checkbox or toggle's label, hint and ID props; `aria-describedby` is set by `v-described-by`. */
const checkLabelProps = computed( () => {
	const { 'aria-describedby': _describedBy, ...rest } = getCheckLabelProps( props.field, layout.value, props.displayConfig, props.error );

	return rest;
} );

/** The description and error IDs the input references in a custom layout. */
const layoutDescribedBy = computed( () =>
	layout.value.custom ? getLayoutDescribedBy( props.field, layout.value, props.error ) : undefined,
);

const stringValue = computed( () => String( props.modelValue ?? '' ) );

const isChecked = computed( () => {
	const v = props.modelValue;

	if ( typeof v === 'boolean' ) {
		return v;
	}

	if ( typeof v === 'number' ) {
		return v === 1;
	}

	if ( typeof v === 'string' ) {
		return ['true', '1', 'yes', 'on'].includes( v.toLowerCase() );
	}

	return false;
} );

const selectedArray = computed( () =>
	Array.isArray( props.modelValue ) ? props.modelValue.map( String ) : [],
);

/** How many of the options are selected, for the "Select All" state. */
const selectedOptionCount = computed( () =>
	optionValues.value.filter( ( value ) => selectedArray.value.includes( value ) ).length,
);

const selectAllInput = ref<HTMLInputElement | null>( null );

// `indeterminate` is a property only, so it is set rather than bound.
watchEffect( () => {
	if ( selectAllInput.value ) {
		selectAllInput.value.indeterminate = selectedOptionCount.value > 0 && selectedOptionCount.value < optionValues.value.length;
	}
} );

const hintId = computed( () => {
	if ( layout.value.custom ) {
		return props.field.help_text ? layout.value.descriptionId : undefined;
	}

	return props.field.help_text && !props.error ? `${fieldIdKey( props.field )}-hint` : undefined;
} );
const errorId = computed( () =>
	props.error ? `${fieldIdKey( props.field )}-error` : undefined,
);
const describedBy = computed( () =>
	[hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined,
);
const legendId = computed( () => {
	if ( !props.field.label ) {
		return undefined;
	}

	return layout.value.custom ? layout.value.labelId : `${fieldIdKey( props.field )}-label`;
} );

/** The ID of the "Other" choice's text input. */
const otherInputId = computed( () => `field-${fieldIdKey( props.field )}-other-text` );

function handleCheckboxGroupChange( optionValue: string, checked: boolean ): void {
	const updated = checked
		? [...selectedArray.value, optionValue]
		: selectedArray.value.filter( ( v ) => v !== optionValue );

	emit( 'update:modelValue', updated );
}

function handleSelectAll( checked: boolean ): void {
	const others = selectedArray.value.filter( ( value ) => !optionValues.value.includes( value ) );

	emit( 'update:modelValue', checked ? [...optionValues.value, ...others] : others );
}

/** The `aria-describedby` value for a searchable select's input. */
const comboboxDescribedBy = computed( () =>
	layout.value.custom
		? [getLayoutDescribedBy( props.field, layout.value ), errorId.value].filter( Boolean ).join( ' ' ) || undefined
		: describedBy.value,
);

function handleMultiSelectChange( event: Event ): void {
	const target = event.target as HTMLSelectElement;
	const selected = Array.from( target.selectedOptions, ( opt ) => opt.value );
	emit( 'update:modelValue', selected );
}
</script>

<template>
	<!-- Searchable select or multi-select: an ARIA combobox -->
	<FieldLayout v-if="searchable" :field="field" :layout="layout">
		<div class="fieldset">
			<label
				v-if="!layout.custom && field.label"
				:id="`${fieldIdKey( field )}-label`"
				:for="layout.inputId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</label>
			<Combobox
				:id="layout.inputId"
				:options="options"
				:model-value="modelValue"
				:multiple="field.type === 'select_multiple'"
				:placeholder="field.placeholder ?? undefined"
				:required="field.is_required"
				:read-only="readOnly"
				:invalid="Boolean( error )"
				:described-by="comboboxDescribedBy"
				:list-label="field.label ?? field.name"
				:class="field.css_classes ?? undefined"
				@update:model-value="emit( 'update:modelValue', $event )"
			/>
			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>

	<!-- Select -->
	<FieldLayout v-else-if="field.type === 'select'" :field="field" :layout="layout">
		<Select
			v-autocomplete="field.autocomplete"
			v-described-by="layoutDescribedBy"
			v-bind="selectLabelProps"
			:name="field.name"
			:model-value="stringValue"
			:placeholder="field.placeholder ?? 'Select an option...'"
			:error="error"
			:required="field.is_required"
			:class="field.css_classes ?? undefined"
			:options="options"
			option-value="value"
			option-label="label"
			:disabled="readOnly"
			:aria-readonly="readOnly ? 'true' : undefined"
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</FieldLayout>

	<!-- Radio: native radios in a labelled radiogroup; a read-only group is wrapped in an element that carries aria-readonly, as in React -->
	<FieldLayout v-else-if="field.type === 'radio'" :field="field" :layout="layout" group>
		<div
			:role="readOnly ? 'group' : undefined"
			:aria-readonly="readOnly ? 'true' : undefined"
		>
			<div class="fieldset">
				<span
					v-if="!layout.custom && field.label"
					:id="legendId"
					:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
				>
					{{ field.label }}
					<span v-if="field.is_required" class="text-error ml-1">*</span>
				</span>
				<div
					role="radiogroup"
					:aria-labelledby="legendId"
					:aria-label="legendId ? undefined : field.name"
					:aria-describedby="describedBy"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-invalid="error ? true : undefined"
					:class="[layoutClasses, field.css_classes ?? '']"
				>
					<template v-for="( option, index ) in choices" :key="option.value">
						<ImageChoice
							v-if="showsImages && option.value !== OTHER_CHOICE_VALUE"
							:option="option"
							:label-id="`field-${fieldIdKey( field )}-${index}-label`"
						>
							<input
								:id="`field-${fieldIdKey( field )}-${index}`"
								type="radio"
								:name="field.name"
								:value="option.value"
								class="radio radio-primary radio-sm"
								:checked="stringValue === option.value"
								:required="field.is_required && index === 0"
								:disabled="readOnly"
								:aria-labelledby="`field-${fieldIdKey( field )}-${index}-label`"
								@change="!readOnly && emit( 'update:modelValue', option.value )"
							/>
						</ImageChoice>
						<label
							v-else
							:for="`field-${fieldIdKey( field )}-${option.value === OTHER_CHOICE_VALUE ? 'other' : index}`"
							class="flex items-center gap-2 cursor-pointer"
						>
							<input
								:id="`field-${fieldIdKey( field )}-${option.value === OTHER_CHOICE_VALUE ? 'other' : index}`"
								type="radio"
								:name="field.name"
								:value="option.value"
								class="radio radio-primary"
								:checked="stringValue === option.value"
								:required="field.is_required && index === 0"
								:disabled="readOnly"
								:aria-controls="option.value === OTHER_CHOICE_VALUE && otherSelected ? otherInputId : undefined"
								@change="!readOnly && emit( 'update:modelValue', option.value )"
							/>
							<span>{{ option.label }}</span>
						</label>
					</template>
				</div>
				<div v-if="otherSelected" class="mt-2">
					<label :for="otherInputId" class="sr-only">
						{{ t( ':label, please specify', { label: getOtherChoiceLabel( field ) } ) }}
					</label>
					<input
						:id="otherInputId"
						type="text"
						:class="['input input-bordered w-full', error ? 'input-error' : '']"
						:value="otherValue ?? ''"
						:maxlength="OTHER_CHOICE_MAX_LENGTH"
						:placeholder="t( 'Please specify' )"
						required
						aria-required="true"
						:aria-invalid="error ? true : undefined"
						:aria-describedby="errorId"
						:readonly="readOnly"
						@input="emit( 'update:otherValue', ( $event.target as HTMLInputElement ).value )"
					/>
				</div>
				<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
					{{ field.help_text }}
				</p>
				<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
					{{ error }}
				</p>
			</div>
		</div>
	</FieldLayout>

	<!-- Checkbox -->
	<FieldLayout v-else-if="field.type === 'checkbox'" :field="field" :layout="layout" :render-label="layout.label === 'hidden'">
		<Checkbox
			v-described-by="layoutDescribedBy"
			v-bind="checkLabelProps"
			:name="field.name"
			:model-value="isChecked"
			:error="error"
			:required="field.is_required"
			:class="field.css_classes ?? undefined"
			:disabled="readOnly"
			:aria-readonly="readOnly ? 'true' : undefined"
			right
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</FieldLayout>

	<!-- Toggle: a checkbox with role="switch", sized and colored by the field's settings -->
	<FieldLayout v-else-if="field.type === 'toggle'" :field="field" :layout="layout" :render-label="layout.label === 'hidden'">
		<Toggle
			v-described-by="layoutDescribedBy"
			v-bind="checkLabelProps"
			:name="field.name"
			:model-value="isChecked"
			:error="error"
			:required="field.is_required"
			:color="getToggleColor( field )"
			:class="[getToggleSizeClass( field ), field.css_classes ?? '']"
			:disabled="readOnly"
			:aria-readonly="readOnly ? 'true' : undefined"
			right
			@update:model-value="emit( 'update:modelValue', $event )"
		/>
	</FieldLayout>

	<!-- Checkbox Group -->
	<FieldLayout v-else-if="field.type === 'checkbox_group'" :field="field" :layout="layout" group>
		<fieldset
			class="fieldset"
			:aria-labelledby="layout.custom ? legendId : undefined"
			:aria-describedby="describedBy"
			:aria-readonly="readOnly ? 'true' : undefined"
		>
			<legend
				v-if="!layout.custom && field.label"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</legend>
			<label
				v-if="showSelectAll"
				:for="`field-${fieldIdKey( field )}-select-all`"
				class="mb-2 flex cursor-pointer items-center gap-2 font-medium"
			>
				<input
					:id="`field-${fieldIdKey( field )}-select-all`"
					ref="selectAllInput"
					type="checkbox"
					class="checkbox checkbox-primary"
					:checked="selectedOptionCount === optionValues.length"
					:disabled="readOnly"
					:aria-controls="optionValues.map( ( _, index ) => `field-${fieldIdKey( field )}-${index}` ).join( ' ' )"
					@change="handleSelectAll( ( $event.target as HTMLInputElement ).checked )"
				/>
				<span>{{ t( 'Select All' ) }}</span>
			</label>
			<div :class="[layoutClasses, field.css_classes ?? '']">
				<template v-for="( option, index ) in choices" :key="option.value">
					<ImageChoice
						v-if="showsImages && option.value !== OTHER_CHOICE_VALUE"
						:option="option"
						:label-id="`field-${fieldIdKey( field )}-${index}-label`"
					>
						<input
							:id="`field-${fieldIdKey( field )}-${index}`"
							type="checkbox"
							:name="`${field.name}[]`"
							:value="option.value"
							class="checkbox checkbox-primary checkbox-sm"
							:checked="selectedArray.includes( option.value )"
							:disabled="readOnly"
							:aria-labelledby="`field-${fieldIdKey( field )}-${index}-label`"
							@change="handleCheckboxGroupChange( option.value, ( $event.target as HTMLInputElement ).checked )"
						/>
					</ImageChoice>
					<Checkbox
						v-else
						:id="option.value === OTHER_CHOICE_VALUE ? `field-${fieldIdKey( field )}-other` : `field-${fieldIdKey( field )}-${index}`"
						:label="option.label"
						:name="`${field.name}[]`"
						:model-value="selectedArray.includes( option.value )"
						:disabled="readOnly"
						right
						@update:model-value="handleCheckboxGroupChange( option.value, $event as boolean )"
					/>
				</template>
			</div>
			<div v-if="otherSelected" class="mt-2">
				<label :for="otherInputId" class="sr-only">
					{{ t( ':label, please specify', { label: getOtherChoiceLabel( field ) } ) }}
				</label>
				<input
					:id="otherInputId"
					type="text"
					:class="['input input-bordered w-full', error ? 'input-error' : '']"
					:value="otherValue ?? ''"
					:maxlength="OTHER_CHOICE_MAX_LENGTH"
					:placeholder="t( 'Please specify' )"
					required
					aria-required="true"
					:aria-invalid="error ? true : undefined"
					:aria-describedby="errorId"
					:readonly="readOnly"
					@input="emit( 'update:otherValue', ( $event.target as HTMLInputElement ).value )"
				/>
			</div>
			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</fieldset>
	</FieldLayout>

	<!-- Select Multiple -->
	<FieldLayout v-else-if="field.type === 'select_multiple'" :field="field" :layout="layout">
		<fieldset class="fieldset">
			<legend
				v-if="!layout.custom && field.label"
				:id="legendId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</legend>
			<select
				:id="layout.custom ? layout.inputId : undefined"
				:name="field.name"
				multiple
				:required="field.is_required"
				:disabled="readOnly"
				:aria-readonly="readOnly ? 'true' : undefined"
				:class="['select select-bordered w-full min-h-32', field.css_classes ?? '', error ? 'select-error' : '']"
				:aria-invalid="error ? true : undefined"
				:aria-labelledby="layout.custom ? undefined : legendId"
				:aria-describedby="describedBy"
				@change="handleMultiSelectChange"
			>
				<!-- Each option carries its own selection: Vue sets a select's `value` as one string -->
				<option
					v-for="option in options"
					:key="option.value"
					:value="option.value"
					:selected="selectedArray.includes( option.value )"
				>
					{{ option.label }}
				</option>
			</select>
			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</fieldset>
	</FieldLayout>
</template>
