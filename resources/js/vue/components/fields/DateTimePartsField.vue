<!--
  Date and time fields filled in with separate inputs.

  A date field in the `dropdowns` or `fields` mode shows month, day and year
  dropdowns or number inputs, ordered by its date format; a time field in
  the `dropdowns` mode shows hour and minute dropdowns, with AM/PM on a
  12-hour clock. The group is a `<fieldset>` whose `<legend>` is the field
  label, and the parts are combined into the `YYYY-MM-DD` or 24-hour `HH:MM`
  value the field submits. Mirrors the React `DateTimeParts` and the Blade
  `date` and `time` partials.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getDateBounds } from '../../../shared/dateConstraints';
import {
	combineDate,
	combineTime,
	DATE_PART_ORDER,
	getDateFormat,
	getDateInputMode,
	getMinuteOptions,
	getMonthNames,
	getTimeFormat,
	getYearOptions,
	splitDate,
	splitTime,
} from '../../../shared/dateTime';
import type { DatePart, DatePartValues, TimePartValues } from '../../../shared/dateTime';
import { getFieldLayout, getTimeStep, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { fieldIdKey } from '../../../shared/domIds';

const props = withDefaults( defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>(), {
	error: undefined,
} );

const emit = defineEmits<{
	'update:modelValue': [value: string];
}>();

/** One input of the group. */
interface PartInput {
	key: string;
	id: string;
	label: string;
	select: boolean;
	options: Array<{ value: string; label: string }>;
	placeholder?: string;
	maxlength?: number;
	autocomplete?: string;
	/** Whether the select starts with a blank "choose" option. */
	blank: boolean;
	wide: boolean;
}

const isDate = computed( () => props.field.type === 'date' );
const dateMode = computed( () => getDateInputMode( props.field ) );
const padded = computed( () => dateMode.value === 'fields' );
const twelveHour = computed( () => getTimeFormat( props.field ) === '12' );
const readOnly = computed( () => isReadOnlyField( props.field ) );

const dateParts = ref<DatePartValues>( splitDate( props.modelValue, padded.value ) ?? { year: '', month: '', day: '' } );
const timeParts = ref<TimePartValues>( splitTime( props.modelValue, twelveHour.value ) ?? { hour: '', minute: '', period: 'am' } );

// Follow value changes from outside (a reset or prefill), keeping a half-filled value.
watch( () => props.modelValue, ( value ) => {
	if ( isDate.value ) {
		const next = splitDate( value, padded.value );

		if ( next && combineDate( next ) !== combineDate( dateParts.value ) ) {
			dateParts.value = next;
		}

		return;
	}

	const next = splitTime( value, twelveHour.value );

	if ( next && combineTime( next, twelveHour.value ) !== combineTime( timeParts.value, twelveHour.value ) ) {
		timeParts.value = next;
	}
} );

/** Where the description goes; the legend is visually hidden for a hidden label. */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );
const hiddenLabel = computed( () =>
	layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ),
);
const hintId = computed( () => ( props.field.help_text ? `${fieldIdKey( props.field )}-hint` : undefined ) );
const errorId = computed( () => ( props.error ? `${fieldIdKey( props.field )}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );

/** Pad a number to two digits. */
function pad( number: number ): string {
	return String( number ).padStart( 2, '0' );
}

/** The translated label of a date part. */
function datePartLabel( part: DatePart ): string {
	switch ( part ) {
		case 'month':
			return t( 'Month' );
		case 'day':
			return t( 'Day' );
		default:
			return t( 'Year' );
	}
}

/** The group's inputs, in order. */
const inputs = computed( (): PartInput[] => {
	const id = ( key: string ): string => `field-${fieldIdKey( props.field )}-${key}`;

	if ( isDate.value ) {
		const select = dateMode.value === 'dropdowns';
		const options: Record<DatePart, Array<{ value: string; label: string }>> = {
			month: getMonthNames().map( ( name, index ) => ( { value: String( index + 1 ), label: name } ) ),
			day: Array.from( { length: 31 }, ( _value, index ) => ( { value: String( index + 1 ), label: String( index + 1 ) } ) ),
			year: getYearOptions( getDateBounds( props.field ) ).map( ( year ) => ( { value: String( year ), label: String( year ) } ) ),
		};
		const placeholders: Record<DatePart, string> = { month: t( 'MM' ), day: t( 'DD' ), year: t( 'YYYY' ) };

		return DATE_PART_ORDER[getDateFormat( props.field )].map( ( part ) => ( {
			key: part,
			id: id( part ),
			label: datePartLabel( part ),
			select,
			options: options[part],
			placeholder: placeholders[part],
			maxlength: part === 'year' ? 4 : 2,
			autocomplete: props.field.autocomplete === 'bday' ? `bday-${part}` : undefined,
			blank: true,
			wide: select && part === 'month',
		} ) );
	}

	const hours = twelveHour.value
		? Array.from( { length: 12 }, ( _value, index ) => index + 1 )
		: Array.from( { length: 24 }, ( _value, index ) => index );
	const parts: PartInput[] = [
		{
			key: 'hour',
			id: id( 'hour' ),
			label: t( 'Hour' ),
			select: true,
			options: hours.map( ( hour ) => ( { value: String( hour ), label: twelveHour.value ? String( hour ) : pad( hour ) } ) ),
			blank: true,
			wide: false,
		},
		{
			key: 'minute',
			id: id( 'minute' ),
			label: t( 'Minute' ),
			select: true,
			options: getMinuteOptions( getTimeStep( props.field ) ).map( ( minute ) => ( { value: String( minute ), label: pad( minute ) } ) ),
			blank: true,
			wide: false,
		},
	];

	if ( twelveHour.value ) {
		parts.push( {
			key: 'period',
			id: id( 'period' ),
			label: t( 'AM or PM' ),
			select: true,
			options: [{ value: 'am', label: t( 'AM' ) }, { value: 'pm', label: t( 'PM' ) }],
			blank: false,
			wide: false,
		} );
	}

	return parts;
} );

/** The current value of one input. */
function partValue( key: string ): string {
	return isDate.value
		? dateParts.value[key as DatePart]
		: String( timeParts.value[key as keyof TimePartValues] );
}

/** Store one input's value and emit the combined value. */
function update( key: string, value: string ): void {
	if ( isDate.value ) {
		dateParts.value = { ...dateParts.value, [key]: dateMode.value === 'fields' ? value.replace( /\D/g, '' ) : value };
		emit( 'update:modelValue', combineDate( dateParts.value ) );

		return;
	}

	timeParts.value = key === 'period'
		? { ...timeParts.value, period: value === 'pm' ? 'pm' : 'am' }
		: { ...timeParts.value, [key]: value };
	emit( 'update:modelValue', combineTime( timeParts.value, twelveHour.value ) );
}
</script>

<template>
	<fieldset :id="`field-${fieldIdKey( field )}`" :class="['fieldset', field.css_classes ?? '']" :aria-describedby="describedBy">
		<legend v-if="field.label" :class="['fieldset-legend', { 'sr-only': hiddenLabel }]">
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
		</legend>

		<p v-if="hintId && layout.description === 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<div class="flex flex-wrap gap-3">
			<div v-for="input in inputs" :key="input.key" :class="['flex flex-col gap-1', input.wide ? 'grow basis-40' : 'w-24']">
				<label :for="input.id" class="label text-sm">{{ input.label }}</label>
				<select
					v-if="input.select"
					:id="input.id"
					:class="['select select-bordered w-full', { 'select-error': error }]"
					:value="partValue( input.key )"
					:autocomplete="input.autocomplete"
					:disabled="readOnly"
					:required="field.is_required && input.blank"
					:aria-required="field.is_required && input.blank ? 'true' : undefined"
					:aria-invalid="error ? 'true' : undefined"
					@change="update( input.key, ( $event.target as HTMLSelectElement ).value )"
				>
					<option v-if="input.blank" value="">{{ input.label }}</option>
					<option v-for="option in input.options" :key="option.value" :value="option.value">{{ option.label }}</option>
				</select>
				<input
					v-else
					:id="input.id"
					type="text"
					inputmode="numeric"
					:class="['input input-bordered w-full', { 'input-error': error }]"
					:value="partValue( input.key )"
					:maxlength="input.maxlength"
					:placeholder="input.placeholder"
					:autocomplete="input.autocomplete"
					:readonly="readOnly"
					:required="field.is_required"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-invalid="error ? 'true' : undefined"
					@input="update( input.key, ( $event.target as HTMLInputElement ).value )"
				/>
			</div>
		</div>

		<p v-if="hintId && layout.description !== 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">{{ error }}</p>
	</fieldset>
</template>
