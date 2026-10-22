<!--
  Field label and description placement.

  Wraps a field's input when the field or its form sets a label or
  description placement (see `getFieldLayout()`): the label on top, to the
  left (from the `md` breakpoint up) or visually hidden, and the description
  above or below the input. Without a placement the input component keeps
  rendering its own label and hint.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { FormField } from '../../../types/artisanpack-forms';
import type { FieldLayout } from '../../../shared/fieldSettings';

const props = withDefaults( defineProps<{
	field: FormField;
	layout: FieldLayout;
	/**
	 * Whether to render the label. False for a single checkbox or toggle,
	 * whose label sits beside the box.
	 */
	renderLabel?: boolean;
	/**
	 * Whether the label names a group (referenced by the group's
	 * `aria-labelledby`) rather than one input (`for`).
	 */
	group?: boolean;
}>(), {
	renderLabel: true,
	group: false,
} );

const showLabel = computed( () => props.renderLabel && Boolean( props.field.label ) );
const isLeft = computed( () => showLabel.value && props.layout.label === 'left' );
const labelClass = computed( () =>
	['fieldset-legend', props.layout.label === 'hidden' ? 'sr-only' : '', isLeft.value ? 'md:pt-2' : '']
		.filter( Boolean )
		.join( ' ' ),
);
</script>

<template>
	<slot v-if="!layout.custom" />
	<div
		v-else
		:class="isLeft ? 'md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:items-start md:gap-x-4' : undefined"
	>
		<template v-if="showLabel">
			<span v-if="group" :id="layout.labelId" :class="labelClass">
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</span>
			<label v-else :id="layout.labelId" :for="layout.inputId" :class="labelClass">
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</label>
		</template>
		<div :class="isLeft ? 'md:col-start-2' : undefined">
			<p v-if="field.help_text && layout.description === 'above'" :id="layout.descriptionId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<slot />
			<p v-if="field.help_text && layout.description === 'below'" :id="layout.descriptionId" class="fieldset-label">
				{{ field.help_text }}
			</p>
		</div>
	</div>
</template>
