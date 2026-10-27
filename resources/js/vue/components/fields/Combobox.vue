<!--
  Searchable select.

  An editable combobox with a listbox popup, following the WAI-ARIA 1.2
  combobox pattern: typing filters the options by label and a polite status
  announces how many match; arrow keys move the active option
  (`aria-activedescendant`), Enter chooses it, Escape closes the list and Tab
  leaves. A multi-select keeps the list open, toggles options and shows each
  choice as a removable button; Backspace in an empty input removes the last.
  Mirrors the React `Combobox` and the Livewire `forms::components.combobox` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { filterChoices } from '../../../shared/choices';
import { t } from '../../../shared/i18n';
import type { FieldOption } from '../../../types/artisanpack-forms';

const props = withDefaults( defineProps<{
	/** The input's ID; the listbox and options derive theirs from it. */
	id: string;
	/** The options, in the order to list them. */
	options: FieldOption[];
	/** The chosen value, or values for a multi-select. */
	modelValue: unknown;
	multiple?: boolean;
	placeholder?: string;
	required?: boolean;
	readOnly?: boolean;
	invalid?: boolean;
	/** The `aria-describedby` value for the input. */
	describedBy?: string;
	/** The listbox's accessible name. */
	listLabel?: string;
}>(), {
	multiple: false,
	placeholder: undefined,
	required: false,
	readOnly: false,
	invalid: false,
	describedBy: undefined,
	listLabel: undefined,
} );

const emit = defineEmits<{
	'update:modelValue': [value: string | string[]];
}>();

const query = ref( '' );
const typed = ref( false );
const open = ref( false );
const active = ref( -1 );
const root = ref<HTMLElement | null>( null );
const input = ref<HTMLInputElement | null>( null );
const listboxId = computed( () => `${props.id}-listbox` );

const selected = computed( (): string[] => {
	if ( props.multiple ) {
		return Array.isArray( props.modelValue ) ? props.modelValue.map( String ) : [];
	}

	return props.modelValue === null || props.modelValue === undefined || props.modelValue === '' ? [] : [String( props.modelValue )];
} );

const filtered = computed( () => ( typed.value ? filterChoices( props.options, query.value ) : props.options ) );
const activeOption = computed( () => ( open.value && active.value >= 0 ? filtered.value[active.value] : undefined ) );

const status = computed( () => {
	if ( !open.value ) {
		return '';
	}

	const count = filtered.value.length;

	return count === 0
		? t( 'No results found.' )
		: count === 1 ? t( '1 result available.' ) : t( ':count results available.', { count } );
} );

const inputValue = computed( () =>
	!props.multiple && !open.value ? ( selected.value.length > 0 ? labelFor( selected.value[0] ) : '' ) : query.value,
);

function labelFor( item: string ): string {
	return props.options.find( ( option ) => option.value === item )?.label ?? item;
}

function optionId( option: FieldOption ): string {
	return `${props.id}-option-${props.options.indexOf( option )}`;
}

function show(): void {
	if ( props.readOnly || open.value ) {
		return;
	}

	open.value = true;
	typed.value = false;
	query.value = props.multiple || selected.value.length === 0 ? '' : labelFor( selected.value[0] );
	active.value = props.multiple ? 0 : Math.max( 0, props.options.findIndex( ( option ) => selected.value.includes( option.value ) ) );
}

function close(): void {
	open.value = false;
	typed.value = false;
	active.value = -1;
	query.value = '';
}

function choose( option: FieldOption | undefined ): void {
	if ( !option || props.readOnly ) {
		return;
	}

	if ( props.multiple ) {
		emit( 'update:modelValue', selected.value.includes( option.value )
			? selected.value.filter( ( item ) => item !== option.value )
			: [...selected.value, option.value] );

		return;
	}

	emit( 'update:modelValue', option.value );
	close();
}

function remove( item: string ): void {
	if ( !props.readOnly ) {
		emit( 'update:modelValue', selected.value.filter( ( entry ) => entry !== item ) );
		input.value?.focus();
	}
}

function move( step: number ): void {
	if ( !open.value ) {
		show();

		return;
	}

	const count = filtered.value.length;

	if ( count > 0 ) {
		active.value = ( active.value + step + count ) % count;
	}
}

function onInput( event: Event ): void {
	if ( props.readOnly ) {
		return;
	}

	query.value = ( event.target as HTMLInputElement ).value;
	typed.value = true;
	open.value = true;
	active.value = filtered.value.length > 0 ? 0 : -1;
}

function onKeydown( event: KeyboardEvent ): void {
	switch ( event.key ) {
		case 'ArrowDown':
			event.preventDefault();
			move( 1 );
			break;
		case 'ArrowUp':
			event.preventDefault();
			move( -1 );
			break;
		case 'Home':
			if ( open.value && filtered.value.length > 0 ) {
				active.value = 0;
			}
			break;
		case 'End':
			if ( open.value && filtered.value.length > 0 ) {
				active.value = filtered.value.length - 1;
			}
			break;
		case 'Enter':
			if ( activeOption.value ) {
				event.preventDefault();
				choose( activeOption.value );
			}
			break;
		case 'Escape':
			if ( open.value ) {
				event.preventDefault();
				event.stopPropagation();
				close();
			}
			break;
		case 'Backspace':
			if ( props.multiple && query.value === '' && selected.value.length > 0 ) {
				remove( selected.value[selected.value.length - 1] );
			}
			break;
		case 'Tab':
			close();
			break;
		default:
			break;
	}
}

function toggleList(): void {
	if ( open.value ) {
		close();
	} else {
		show();
		input.value?.focus();
	}
}

/** Close when a click lands outside the widget. */
function onDocumentPointer( event: MouseEvent ): void {
	if ( root.value && !root.value.contains( event.target as Node ) ) {
		close();
	}
}

watch( open, ( isOpen ) => {
	if ( isOpen ) {
		document.addEventListener( 'mousedown', onDocumentPointer );
	} else {
		document.removeEventListener( 'mousedown', onDocumentPointer );
	}
} );

// Keep the active option in view.
watch( activeOption, ( option ) => {
	if ( option ) {
		nextTick( () => document.getElementById( optionId( option ) )?.scrollIntoView?.( { block: 'nearest' } ) );
	}
} );

onBeforeUnmount( () => document.removeEventListener( 'mousedown', onDocumentPointer ) );
</script>

<template>
	<div ref="root" class="relative">
		<ul v-if="multiple && selected.length > 0" class="mb-2 flex flex-wrap gap-2" :aria-label="t( 'Selected options' )">
			<li v-for="item in selected" :key="item" class="badge badge-primary badge-outline gap-1 py-3">
				<span>{{ labelFor( item ) }}</span>
				<button
					type="button"
					class="btn btn-ghost btn-xs btn-circle"
					:aria-label="t( 'Remove :label', { label: labelFor( item ) } )"
					:disabled="readOnly"
					@click="remove( item )"
				>
					<span aria-hidden="true">&times;</span>
				</button>
			</li>
		</ul>

		<div class="relative">
			<input
				:id="id"
				ref="input"
				type="text"
				role="combobox"
				aria-autocomplete="list"
				:aria-controls="listboxId"
				:aria-expanded="open ? 'true' : 'false'"
				:aria-activedescendant="activeOption ? optionId( activeOption ) : undefined"
				:aria-required="required ? 'true' : undefined"
				:aria-invalid="invalid ? true : undefined"
				:aria-describedby="describedBy"
				:aria-readonly="readOnly ? 'true' : undefined"
				autocomplete="off"
				:readonly="readOnly"
				:class="['input input-bordered w-full pr-10', invalid ? 'input-error' : '']"
				:placeholder="placeholder ?? ( multiple ? t( 'Search options...' ) : t( 'Search...' ) )"
				:value="inputValue"
				@input="onInput"
				@click="show"
				@keydown="onKeydown"
			/>
			<button
				type="button"
				tabindex="-1"
				class="btn btn-ghost btn-sm btn-square absolute right-1 top-1/2 -translate-y-1/2"
				:aria-label="t( 'Show options' )"
				:aria-expanded="open ? 'true' : 'false'"
				:aria-controls="listboxId"
				:disabled="readOnly"
				@click="toggleList"
			>
				<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
					<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
				</svg>
			</button>
		</div>

		<ul
			:id="listboxId"
			role="listbox"
			:aria-multiselectable="multiple ? 'true' : undefined"
			:aria-label="listLabel"
			:hidden="!open || filtered.length === 0"
			class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
		>
			<li
				v-for="( option, index ) in filtered"
				:id="optionId( option )"
				:key="option.value"
				role="option"
				:aria-selected="selected.includes( option.value ) ? 'true' : 'false'"
				:class="['flex cursor-pointer items-center gap-2 rounded-field px-3 py-2', index === active ? 'bg-base-200' : '']"
				@mousedown.prevent="choose( option )"
				@mouseenter="active = index"
			>
				<span class="w-4 shrink-0" aria-hidden="true">{{ selected.includes( option.value ) ? '✓' : '' }}</span>
				<span>{{ option.label }}</span>
			</li>
		</ul>

		<div
			v-if="open && filtered.length === 0"
			class="absolute z-20 mt-1 w-full rounded-box border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/60 shadow-lg"
		>
			{{ t( 'No results found.' ) }}
		</div>

		<div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ status }}</div>
	</div>
</template>
