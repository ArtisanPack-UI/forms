<!--
  The notes shown under a text-like input (since 1.6.0).

  Mirrors the Livewire `input-extras` and `counter` partials: the input
  mask's expected format, the number field's prefix and suffix for screen
  readers, and a live character or word counter. Each note's ID is in the
  input's `aria-describedby` (see `getInputNoteIds()`). The counter updates
  as the visitor types; a polite live region repeats it once typing pauses,
  so screen readers are not interrupted on every keystroke.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { FormField } from '../../../types/artisanpack-forms';
import { COUNTER_ANNOUNCE_DELAY, getCounterState, showsCounter } from '../../../shared/counter';
import { getInputMaskHint } from '../../../shared/mask';

const props = defineProps<{
	field: FormField;
	/** The input's current value, for the counter. */
	value: string;
}>();

const maskHint = computed( () => getInputMaskHint( props.field ) );
const hasCounter = computed( () => showsCounter( props.field ) );
const counter = computed( () => getCounterState( props.field, props.value ) );

const announcement = ref( '' );
let timer: ReturnType<typeof setTimeout> | undefined;

// Announce changes only, not the count the field starts with.
watch( () => counter.value.text, ( text ) => {
	clearTimeout( timer );
	timer = setTimeout( () => {
		announcement.value = text;
	}, COUNTER_ANNOUNCE_DELAY );
} );

onBeforeUnmount( () => clearTimeout( timer ) );
</script>

<template>
	<p v-if="maskHint" :id="`field-${field.name}-mask`" class="fieldset-label mt-1">{{ maskHint }}</p>
	<span v-if="field.affix_description" :id="`field-${field.name}-affix`" class="sr-only">{{ field.affix_description }}</span>
	<template v-if="hasCounter">
		<p
			:id="`field-${field.name}-counter`"
			class="fieldset-label mt-1 justify-end"
			:class="{ 'text-error': counter.overLimit }"
		>
			{{ counter.text }}
		</p>
		<span class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</span>
	</template>
</template>
