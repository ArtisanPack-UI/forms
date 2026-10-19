<!--
  Layout field components wrapper.

  Renders non-data layout elements: heading, paragraph, divider, and HTML.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { FormField } from '../../../types/artisanpack-forms';
import { getLayoutContent } from '../../../shared/fieldSettings';

const props = defineProps<{
	field: FormField;
}>();

const headingLevel = computed( () => {
	if ( props.field.field_config && 'level' in props.field.field_config ) {
		const parsed = Number( props.field.field_config.level );

		if ( Number.isFinite( parsed ) ) {
			return Math.min( Math.max( parsed, 1 ), 6 );
		}
	}

	return 2;
} );

const headingTag = computed( () => `h${headingLevel.value}` );
const layoutContent = computed( () => getLayoutContent( props.field ) );
</script>

<template>
	<!-- Heading -->
	<component
		v-if="field.type === 'heading'"
		:is="headingTag"
		:class="['font-bold', field.css_classes ?? '']"
	>
		{{ layoutContent }}
	</component>

	<!-- Paragraph -->
	<p
		v-else-if="field.type === 'paragraph'"
		:class="field.css_classes ?? undefined"
	>
		{{ layoutContent }}
	</p>

	<!-- Divider -->
	<hr
		v-else-if="field.type === 'divider'"
		:class="['divider', field.css_classes ?? '']"
	/>

	<!-- HTML -->
	<!-- eslint-disable vue/no-v-html -->
	<div
		v-else-if="field.type === 'html'"
		:class="field.css_classes ?? undefined"
		v-html="layoutContent"
	/>
	<!-- eslint-enable vue/no-v-html -->
</template>
