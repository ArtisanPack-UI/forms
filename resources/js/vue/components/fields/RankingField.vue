<!--
  Ranking field component.

  Renders the field's choices as a numbered list that visitors put in order
  of preference, in a `<fieldset>` whose `<legend>` is the field label
  (since 1.6.0; see `shared/ranking.ts`). Each choice has "Move up" and
  "Move down" buttons and can be dragged with a mouse. After a move, focus
  stays on the moved choice's button and a polite live region announces its
  new position. Mirrors the Blade `ranking` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { getRankingChoices, getRankingOrder, moveRankingItem } from '../../../shared/ranking';
import { fieldIdKey } from '../../../shared/domIds';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: string[]];
}>();

const labels = computed( (): Record<string, string> => Object.fromEntries( getRankingChoices( props.field ).map( ( choice ) => [choice.value, choice.label] ) ) );
const order = computed( () => getRankingOrder( props.field, props.modelValue ) );
const readOnly = computed( () => isReadOnlyField( props.field ) );
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );
const hiddenLabel = computed( () => layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ) );
const idPrefix = computed( () => `field-${fieldIdKey( props.field )}-` );
const instructionsId = computed( () => ( readOnly.value ? undefined : `${idPrefix.value}instructions` ) );
const hintId = computed( () => ( props.field.help_text ? `${fieldIdKey( props.field )}-hint` : undefined ) );
const errorId = computed( () => ( props.error ? `${fieldIdKey( props.field )}-error` : undefined ) );
const describedBy = computed( () => [instructionsId.value, hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );

const announcement = ref( '' );
const dragging = ref<number | null>( null );

/** Move a choice, announce its new position and, after a button press, keep focus on it. */
async function move( from: number, to: number, keepFocus: boolean ): Promise<void> {
	const current = order.value;
	const next = moveRankingItem( current, from, to );

	if ( next === current ) {
		return;
	}

	emit( 'update:modelValue', next );
	announcement.value = t( ':choice moved to position :position of :total.', {
		choice: labels.value[current[from]] ?? current[from],
		position: to + 1,
		total: current.length,
	} );

	if ( !keepFocus ) {
		return;
	}

	await nextTick();

	const direction = to < from ? 'up' : 'down';
	const other = direction === 'up' ? 'down' : 'up';
	let button = document.getElementById( `${idPrefix.value}${to}-${direction}` ) as HTMLButtonElement | null;

	if ( !button || button.disabled ) {
		button = document.getElementById( `${idPrefix.value}${to}-${other}` ) as HTMLButtonElement | null;
	}

	button?.focus();
}

/** Start dragging a choice. */
function dragStart( event: DragEvent, index: number ): void {
	dragging.value = index;

	if ( event.dataTransfer ) {
		event.dataTransfer.effectAllowed = 'move';
	}
}

/** Allow a drop while a choice is being dragged. */
function dragOver( event: DragEvent ): void {
	if ( dragging.value !== null ) {
		event.preventDefault();

		if ( event.dataTransfer ) {
			event.dataTransfer.dropEffect = 'move';
		}
	}
}

/** Drop the dragged choice on another's position. */
function drop( index: number ): void {
	if ( dragging.value !== null ) {
		void move( dragging.value, index, false );
	}

	dragging.value = null;
}
</script>

<template>
	<fieldset
		:id="`field-${fieldIdKey( field )}`"
		:class="['fieldset', field.css_classes ?? '']"
		:data-forms-ranking="field.name"
		:aria-describedby="describedBy"
	>
		<legend v-if="field.label" :class="['fieldset-legend', hiddenLabel ? 'sr-only' : '']">
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
		</legend>

		<p v-if="hintId && layout.description === 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<p v-if="instructionsId" :id="instructionsId" class="fieldset-label">
			{{ t( 'Put the choices in order, most preferred first: drag them, or use the Move up and Move down buttons.' ) }}
		</p>

		<ol class="flex flex-col gap-2">
			<li
				v-for="( choice, index ) in order"
				:key="choice"
				:class="['flex items-center gap-3 rounded-box border border-base-300 bg-base-100 p-2', dragging === index ? 'opacity-50' : '']"
				:draggable="!readOnly"
				@dragstart="dragStart( $event, index )"
				@dragend="dragging = null"
				@dragover="dragOver( $event )"
				@drop.prevent="drop( index )"
			>
				<span v-if="!readOnly" class="cursor-grab text-base-content/50" aria-hidden="true">⠿</span>
				<span class="badge badge-neutral tabular-nums" aria-hidden="true">{{ index + 1 }}</span>
				<span class="flex-1">{{ labels[choice] ?? choice }}</span>
				<template v-if="!readOnly">
					<button
						:id="`${idPrefix}${index}-up`"
						type="button"
						class="btn btn-ghost btn-sm btn-square"
						:aria-label="t( 'Move :choice up', { choice: labels[choice] ?? choice } )"
						:disabled="index === 0"
						@click="move( index, index - 1, true )"
					>
						<span aria-hidden="true">↑</span>
					</button>
					<button
						:id="`${idPrefix}${index}-down`"
						type="button"
						class="btn btn-ghost btn-sm btn-square"
						:aria-label="t( 'Move :choice down', { choice: labels[choice] ?? choice } )"
						:disabled="index === order.length - 1"
						@click="move( index, index + 1, true )"
					>
						<span aria-hidden="true">↓</span>
					</button>
				</template>
			</li>
		</ol>

		<p v-if="hintId && layout.description !== 'above'" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>

		<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">{{ error }}</p>

		<div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
	</fieldset>
</template>
