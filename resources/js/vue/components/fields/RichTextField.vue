<!--
  Rich text textarea.

  A textarea with "Use rich text editor" on: a labelled `role="toolbar"` of
  formatting buttons above a `contenteditable` `role="textbox"`, with the
  field's label, description, error and counter. One toolbar button is in
  the tab order at a time; the arrow keys, Home and End move between them,
  and each toggle reports its state with `aria-pressed`. The field submits
  the editor's HTML, which the server sanitizes with `kses()` when it is
  stored and shown. Mirrors the React `RichTextEditor` and the Blade
  `rich-text` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import {
	getFieldLayout,
	getInputNoteIds,
	getLayoutDescribedBy,
	getTextareaRows,
	isReadOnlyField,
} from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import {
	getPressedCommands,
	getRichTextCommands,
	getToolbarFocusIndex,
	richTextToPlainText,
	runRichTextCommand,
} from '../../../shared/richText';
import type { RichTextCommand } from '../../../shared/richText';
import FieldLayout from './FieldLayout.vue';
import InputNotes from './InputNotes.vue';

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

const editor = ref<HTMLDivElement | null>( null );
const toolbar = ref<HTMLDivElement | null>( null );
const current = ref( 0 );
const pressed = ref<Partial<Record<RichTextCommand['command'], boolean>>>( {} );

const html = computed( () => String( props.modelValue ?? '' ) );
const commands = computed( () => getRichTextCommands() );
const readOnly = computed( () => isReadOnlyField( props.field ) );
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );
const fieldName = computed( () => props.field.label ?? props.field.name );
const labelHidden = computed( () => props.displayConfig.label_position === 'hidden' );
const labelId = computed( () => {
	if ( !props.field.label ) {
		return undefined;
	}

	return layout.value.custom ? layout.value.labelId : `${props.field.name}-label`;
} );
const hintId = computed( () => ( !layout.value.custom && props.field.help_text ? `${props.field.name}-hint` : undefined ) );
const errorId = computed( () => ( props.error ? `${props.field.name}-error` : undefined ) );
const describedBy = computed( () => [
	layout.value.custom ? getLayoutDescribedBy( props.field, layout.value ) : hintId.value,
	errorId.value,
	...getInputNoteIds( props.field ),
].filter( Boolean ).join( ' ' ) || undefined );

/** Follow value changes from outside the editor (a reset or prefill) without moving the caret while typing. */
watch( html, ( value ) => {
	if ( editor.value && editor.value.innerHTML !== value ) {
		editor.value.innerHTML = value;
	}
} );

function refresh(): void {
	if ( editor.value ) {
		const next = getPressedCommands( editor.value );

		if ( Object.keys( next ).length > 0 ) {
			pressed.value = next;
		}
	}
}

onMounted( () => {
	if ( editor.value ) {
		editor.value.innerHTML = html.value;
	}

	document.execCommand( 'defaultParagraphSeparator', false, 'p' );
	document.addEventListener( 'selectionchange', refresh );
} );

onBeforeUnmount( () => document.removeEventListener( 'selectionchange', refresh ) );

function sync(): void {
	if ( editor.value ) {
		emit( 'update:modelValue', editor.value.innerHTML );
	}
}

function run( command: RichTextCommand['command'] ): void {
	if ( !editor.value ) {
		return;
	}

	runRichTextCommand( editor.value, command );
	sync();
	pressed.value = getPressedCommands( editor.value );
}

function onToolbarKeydown( event: KeyboardEvent ): void {
	const next = getToolbarFocusIndex( event.key, current.value, commands.value.length );

	if ( next === undefined ) {
		return;
	}

	event.preventDefault();
	current.value = next;
	toolbar.value?.querySelectorAll( 'button' )[next]?.focus();
}
</script>

<template>
	<FieldLayout :field="field" :layout="layout" group>
		<div class="fieldset">
			<span
				v-if="!layout.custom && field.label"
				:id="labelId"
				:class="['fieldset-legend', { 'sr-only': labelHidden }]"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</span>

			<div class="flex flex-col">
				<div
					ref="toolbar"
					role="toolbar"
					class="flex flex-wrap gap-1 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-1"
					:aria-label="t( 'Formatting for :field', { field: fieldName } )"
					:aria-controls="`field-${field.name}`"
					@keydown="onToolbarKeydown"
				>
					<button
						v-for="( command, index ) in commands"
						:key="command.command"
						type="button"
						:class="['btn btn-ghost btn-sm min-w-8', command.className]"
						:aria-label="command.label"
						:aria-pressed="pressed[command.command] ? 'true' : 'false'"
						:tabindex="current === index ? 0 : -1"
						:disabled="readOnly"
						@focus="current = index"
						@mousedown.prevent
						@click="run( command.command )"
					>
						<span aria-hidden="true">{{ command.text }}</span>
					</button>
				</div>
				<div
					:id="`field-${field.name}`"
					ref="editor"
					role="textbox"
					aria-multiline="true"
					:aria-labelledby="labelId"
					:aria-label="labelId ? undefined : fieldName"
					:aria-describedby="describedBy"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-readonly="readOnly ? 'true' : undefined"
					:aria-invalid="error ? 'true' : undefined"
					:data-placeholder="field.placeholder ?? undefined"
					:contenteditable="readOnly ? 'false' : 'true'"
					:class="[
						'prose prose-sm max-w-none rounded-b-box border bg-base-100 px-4 py-2 focus:outline-2 focus:outline-offset-2 focus:outline-primary empty:before:text-base-content/50 empty:before:content-[attr(data-placeholder)]',
						error ? 'border-error' : 'border-base-300',
						field.css_classes ?? '',
					]"
					:style="{ minHeight: `${getTextareaRows( field ) * 1.5}rem` }"
					@input="sync"
					@blur="sync"
				/>
			</div>

			<p v-if="hintId" :id="hintId" class="fieldset-label">{{ field.help_text }}</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">{{ error }}</p>
			<InputNotes :field="field" :value="richTextToPlainText( html )" />
		</div>
	</FieldLayout>
</template>
