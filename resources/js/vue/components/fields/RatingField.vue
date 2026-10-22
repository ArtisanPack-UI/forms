<!--
  Rating field component.

  Renders a star rating as a native radio group, so arrow keys move between
  stars, mirroring the Blade `rating` partial. A value of 0 ("No rating")
  clears the rating.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { FormField, DisplayConfig } from '../../../types/artisanpack-forms';
import {
	getFieldLayout,
	getRatingSizeClass,
	getRatingSteps,
	isReadOnlyField,
	ratingAllowsHalf,
} from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import FieldLayout from './FieldLayout.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: unknown];
}>();

/** Visible focus ring for the focused star. */
const FOCUS_CLASSES = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

/** Focus ring on the group while the hidden "No rating" radio has keyboard focus. */
const GROUP_FOCUS_CLASSES = 'has-[.rating-hidden:focus-visible]:outline has-[.rating-hidden:focus-visible]:outline-2 has-[.rating-hidden:focus-visible]:outline-offset-2 has-[.rating-hidden:focus-visible]:outline-primary';

const steps = computed( () => getRatingSteps( props.field ) );
const allowHalf = computed( () => ratingAllowsHalf( props.field ) );
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** The current rating as a number; 0 when unrated. */
const currentRating = computed( (): number => {
	const value = Number( props.modelValue );

	return Number.isFinite( value ) ? value : 0;
} );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

const labelId = computed( () => {
	if ( !props.field.label ) {
		return undefined;
	}

	return layout.value.custom ? layout.value.labelId : `${props.field.name}-label`;
} );
const hintId = computed( () => {
	if ( layout.value.custom ) {
		return props.field.help_text ? layout.value.descriptionId : undefined;
	}

	return props.field.help_text && !props.error ? `${props.field.name}-hint` : undefined;
} );
const errorId = computed( () => ( props.error ? `${props.field.name}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );
const ariaLabel = computed( () =>
	props.displayConfig.label_position === 'hidden' || !props.field.label
		? ( props.field.label ?? props.field.name )
		: undefined,
);

const groupClasses = computed( () => [
	'rating',
	getRatingSizeClass( props.field ),
	allowHalf.value ? 'rating-half' : '',
	GROUP_FOCUS_CLASSES,
	props.field.css_classes ?? '',
] );

/** The star mask classes for a step: a half star covers the left or right half. */
function starClasses( value: number ): string[] {
	const half = allowHalf.value ? ( Number.isInteger( value ) ? 'mask-half-2' : 'mask-half-1' ) : '';

	return ['mask mask-star-2 bg-warning', half, FOCUS_CLASSES];
}

/** The accessible name of a star. */
function starLabel( value: number ): string {
	return value === 1 ? t( ':count star', { count: value } ) : t( ':count stars', { count: value } );
}

function select( value: number ): void {
	emit( 'update:modelValue', String( value ) );
}
</script>

<template>
	<FieldLayout :field="field" :layout="layout" group>
		<div class="fieldset">
			<span
				v-if="!layout.custom && field.label"
				:id="labelId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</span>

			<div
				role="radiogroup"
				:class="groupClasses"
				:aria-labelledby="labelId"
				:aria-label="labelId ? undefined : ariaLabel"
				:aria-describedby="describedBy"
				:aria-required="field.is_required ? 'true' : undefined"
				:aria-readonly="readOnly ? 'true' : undefined"
				:aria-invalid="error ? 'true' : undefined"
			>
				<input
					type="radio"
					:name="field.name"
					value="0"
					class="rating-hidden"
					:aria-label="t( 'No rating' )"
					:checked="currentRating === 0"
					:disabled="readOnly"
					@change="select( 0 )"
				/>
				<input
					v-for="value in steps"
					:key="value"
					type="radio"
					:name="field.name"
					:value="String( value )"
					:class="starClasses( value )"
					:aria-label="starLabel( value )"
					:checked="currentRating === value"
					:disabled="readOnly"
					@change="select( value )"
				/>
			</div>

			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>
</template>
