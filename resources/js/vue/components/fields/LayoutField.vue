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

const props = defineProps<{
	field: FormField;
}>();

const headingLevel = computed( () => {
	const level = props.field.field_config && 'level' in props.field.field_config
		? Number( props.field.field_config.level )
		: 2;

	return Math.min( Math.max( level, 1 ), 6 );
} );

const headingTag = computed( () => `h${headingLevel.value}` );
const paragraphText = computed( () => props.field.default_value ?? props.field.label ?? '' );
const htmlContent = computed( () => props.field.default_value ?? '' );
</script>

<template>
	<!-- Heading -->
	<component
		v-if="field.type === 'heading'"
		:is="headingTag"
		:class="['font-bold', field.css_classes ?? '']"
	>
		{{ field.label ?? '' }}
	</component>

	<!-- Paragraph -->
	<p
		v-else-if="field.type === 'paragraph'"
		:class="field.css_classes ?? undefined"
	>
		{{ paragraphText }}
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
		v-html="htmlContent"
	/>
	<!-- eslint-enable vue/no-v-html -->
</template>
