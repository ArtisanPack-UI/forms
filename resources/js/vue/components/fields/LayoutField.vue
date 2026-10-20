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
import { getDividerClasses, getHeadingLevel, getLayoutContent } from '../../../shared/fieldSettings';

const props = defineProps<{
	field: FormField;
}>();

const headingTag = computed( () => getHeadingLevel( props.field ) );
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
		:class="['divider', getDividerClasses( field ), field.css_classes ?? '']"
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
