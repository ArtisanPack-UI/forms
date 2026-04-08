<!--
  FieldPalette component for the form builder sidebar.

  Displays available field types grouped by category. Each field type
  can be clicked to add it to the form, or dragged into the canvas.

  @package    ArtisanPack_UI
  @subpackage Forms

  @since      1.1.0
-->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button, Input } from '@artisanpack-ui/vue';

import type {
	FieldPaletteGroup,
	FieldPaletteItem,
	FieldType,
} from '../../../types/artisanpack-forms';

/** SVG path data for each field type icon (16x16 viewBox). */
const ICON_PATHS: Record<string, string> = {
	text: 'M2 3h12v2H9v8H7V5H2V3z',
	email: 'M1 4l7 4 7-4v8H1V4zm1.5 1v.5L8 8.5l5.5-3V5h-11z',
	phone: 'M4 1h8a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V2a1 1 0 011-1zm4 12a1 1 0 100-2 1 1 0 000 2zm-3-3h6V3H5v7z',
	number: 'M6 2v5H3v2h3v5h2V9h3V7H8V2H6z',
	url: 'M6.5 7.5a2.5 2.5 0 013.5 0l1 1a2.5 2.5 0 010 3.5l-1 1a2.5 2.5 0 01-3.5 0l-.5-.5 1.4-1.4.5.5a.8.8 0 001.2 0l1-1a.8.8 0 000-1.2l-1-1a.8.8 0 00-1.2 0L6.5 7.5zM9.5 8.5a2.5 2.5 0 01-3.5 0l-1-1a2.5 2.5 0 010-3.5l1-1a2.5 2.5 0 013.5 0l.5.5L8.6 4.9l-.5-.5a.8.8 0 00-1.2 0l-1 1a.8.8 0 000 1.2l1 1a.8.8 0 001.2 0L9.5 8.5z',
	textarea: 'M2 2h12v12H2V2zm1 1v10h10V3H3zm1 2h8v1H4V5zm0 3h8v1H4V8zm0 3h5v1H4v-1z',
	hidden: 'M8 4C4.5 4 1.7 7 1 8c.7 1 3.5 4 7 4s6.3-3 7-4c-.7-1-3.5-4-7-4zm0 6a2 2 0 110-4 2 2 0 010 4zM1.3 1.3l13.4 13.4-1 1L.3 2.3l1-1z',
	select: 'M2 4h12v2H2V4zm0 3h12v2H2V7zm0 3h12v2H2v-2zm10-7l2 3h-4l2-3z',
	radio: 'M8 2a6 6 0 100 12A6 6 0 008 2zm0 1.5a4.5 4.5 0 110 9 4.5 4.5 0 010-9zm0 2a2.5 2.5 0 100 5 2.5 2.5 0 000-5z',
	checkbox: 'M3 1h10a2 2 0 012 2v10a2 2 0 01-2 2H3a2 2 0 01-2-2V3a2 2 0 012-2zm0 1.5a.5.5 0 00-.5.5v10a.5.5 0 00.5.5h10a.5.5 0 00.5-.5V3a.5.5 0 00-.5-.5H3zm8.2 3L7 9.7 4.8 7.5 3.4 8.9l3.6 3.6 5.6-5.6-1.4-1.4z',
	checkbox_group: 'M2 1h5v5H2V1zm1 1v3h3V2H3zm7-1h5v5H9V1zm1 1v3h3V2h-3zM2 9h5v5H2V9zm1 1v3h3v-3H3zm6-1h5v5H9V9zm1 1v3h3v-3h-3z',
	select_multiple: 'M2 3h12v2H2V3zm0 4h12v2H2V7zm0 4h12v2H2v-2z',
	file: 'M3 1h7l4 4v9a1 1 0 01-1 1H3a1 1 0 01-1-1V2a1 1 0 011-1zm6.5 1H3.5v12h9V5.5H9.5V2zM7 8l3 3.5H4L7 8z',
	date: 'M4 0v2H2a1 1 0 00-1 1v11a1 1 0 001 1h12a1 1 0 001-1V3a1 1 0 00-1-1h-2V0h-2v2H6V0H4zM2 6h12v8H2V6zm2 2v2h2V8H4zm4 0v2h2V8H8zm4 0v2h2V8h-2z',
	time: 'M8 1a7 7 0 100 14A7 7 0 008 1zm0 1.5a5.5 5.5 0 110 11 5.5 5.5 0 010-11zM7.25 4v4.5l3.5 2.1.75-1.2-2.75-1.65V4h-1.5z',
	heading: 'M2 2h3v5h6V2h3v12h-3V9H5v5H2V2z',
	paragraph: 'M6 1h8v2h-2v11h-2V3H8v11H6V9a4 4 0 010-8z',
	divider: 'M1 7h14v2H1V7z',
	html: 'M4.5 4L1 8l3.5 4 1.4-1.2L3.2 8l2.7-2.8L4.5 4zm7 0l-1.4 1.2L12.8 8l-2.7 2.8L11.5 12 15 8l-3.5-4z',
};

/** Default field palette organized by category. */
const FIELD_PALETTE: FieldPaletteGroup[] = [
	{
		label: 'Basic Fields',
		fields: [
			{ type: 'text', label: 'Text', icon: 'text', category: 'basic' },
			{ type: 'email', label: 'Email', icon: 'email', category: 'basic' },
			{ type: 'phone', label: 'Phone', icon: 'phone', category: 'basic' },
			{ type: 'number', label: 'Number', icon: 'number', category: 'basic' },
			{ type: 'url', label: 'URL', icon: 'url', category: 'basic' },
			{ type: 'textarea', label: 'Textarea', icon: 'textarea', category: 'basic' },
			{ type: 'hidden', label: 'Hidden', icon: 'hidden', category: 'basic' },
		],
	},
	{
		label: 'Choice Fields',
		fields: [
			{ type: 'select', label: 'Select', icon: 'select', category: 'choice' },
			{ type: 'radio', label: 'Radio', icon: 'radio', category: 'choice' },
			{ type: 'checkbox', label: 'Checkbox', icon: 'checkbox', category: 'choice' },
			{ type: 'checkbox_group', label: 'Checkbox Group', icon: 'checkbox_group', category: 'choice' },
			{ type: 'select_multiple', label: 'Multi-Select', icon: 'select_multiple', category: 'choice' },
		],
	},
	{
		label: 'Advanced Fields',
		fields: [
			{ type: 'file', label: 'File Upload', icon: 'file', category: 'advanced' },
			{ type: 'date', label: 'Date', icon: 'date', category: 'advanced' },
			{ type: 'time', label: 'Time', icon: 'time', category: 'advanced' },
		],
	},
	{
		label: 'Layout',
		fields: [
			{ type: 'heading', label: 'Heading', icon: 'heading', category: 'layout' },
			{ type: 'paragraph', label: 'Paragraph', icon: 'paragraph', category: 'layout' },
			{ type: 'divider', label: 'Divider', icon: 'divider', category: 'layout' },
			{ type: 'html', label: 'HTML', icon: 'html', category: 'layout' },
		],
	},
];

const props = withDefaults( defineProps<{
	/** Optional external search filter. When undefined, an internal search input is shown. */
	searchFilter?: string;
	/** Optional CSS class for the root element. */
	class?: string;
}>(), {
	searchFilter: undefined,
	class: undefined,
} );

const emit = defineEmits<{
	addField: [type: FieldType];
}>();

const internalSearch = ref( '' );

const search = computed( () => props.searchFilter ?? internalSearch.value );

const filteredGroups = computed( () => {
	if ( !search.value ) {
		return FIELD_PALETTE;
	}

	const lower = search.value.toLowerCase();

	return FIELD_PALETTE
		.map( ( group ) => ( {
			...group,
			fields: group.fields.filter( ( field ) =>
				field.label.toLowerCase().includes( lower ) ||
				field.type.toLowerCase().includes( lower ),
			),
		} ) )
		.filter( ( group ) => group.fields.length > 0 );
} );

/**
 * Handle drag start for a palette field item.
 *
 * @param event The native drag event.
 * @param type  The field type being dragged.
 */
function onDragStart( event: DragEvent, type: FieldType ): void {
	if ( event.dataTransfer ) {
		event.dataTransfer.setData( 'application/x-field-type', type );
		event.dataTransfer.effectAllowed = 'copy';
	}
}

defineExpose( { FIELD_PALETTE } );
</script>

<template>
	<div :class="['space-y-4', props.class]">
		<h3 class="text-lg font-semibold">Add Field</h3>

		<Input
			v-if="searchFilter === undefined"
			type="search"
			size="sm"
			placeholder="Search fields..."
			:model-value="internalSearch"
			class="w-full"
			@update:model-value="internalSearch = ($event as string)"
		/>

		<div class="space-y-4">
			<div
				v-for="group in filteredGroups"
				:key="group.label"
				class="space-y-1"
			>
				<h4 class="text-sm font-medium text-base-content/70 uppercase tracking-wider">
					{{ group.label }}
				</h4>
				<div class="flex flex-col">
					<Button
						v-for="field in group.fields"
						:key="field.type"
						color="ghost"
						size="sm"
						class="justify-start gap-2 w-full"
						draggable="true"
						:title="`Add ${field.label} field`"
						@click="emit( 'addField', field.type )"
						@dragstart="onDragStart( $event, field.type )"
					>
						<span class="text-base-content/50 w-5 text-center shrink-0">
							<svg
								xmlns="http://www.w3.org/2000/svg"
								viewBox="0 0 16 16"
								fill="currentColor"
								class="w-4 h-4"
							>
								<path :d="ICON_PATHS[field.icon] ?? ''" />
							</svg>
						</span>
						<span>{{ field.label }}</span>
					</Button>
				</div>
			</div>
		</div>

		<p
			v-if="filteredGroups.length === 0"
			class="text-sm text-base-content/50 text-center py-4"
		>
			No fields match your search.
		</p>
	</div>
</template>
