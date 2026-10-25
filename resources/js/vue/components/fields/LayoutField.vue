<!--
  Layout field components wrapper.

  Renders non-data layout elements: heading, paragraph, divider, and HTML.
  Since 1.6.0 a heading has an alignment, a paragraph keeps its basic
  formatting (sanitized by the server as `content_html`) and an HTML block's
  `{field_name}` merge tags show what the visitor entered, as they type.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { FormField } from '../../../types/artisanpack-forms';
import { getDividerClasses, getHeadingAlignClass, getHeadingLevel, getLayoutContent } from '../../../shared/fieldSettings';
import { applyLiveMergeTags } from '../../../shared/liveMergeTags';
import { useFormValues } from '../../composables/formValues';

const props = defineProps<{
	field: FormField;
}>();

const form = useFormValues();

const headingTag = computed( () => getHeadingLevel( props.field ) );
const layoutContent = computed( () => getLayoutContent( props.field ) );

/** An HTML block's content with its merge tags filled in from the form's values (escaped). */
const htmlContent = computed( () => (
	form ? applyLiveMergeTags( layoutContent.value, form.value.fields, form.value ) : layoutContent.value
) );
</script>

<template>
	<!-- Heading -->
	<component
		v-if="field.type === 'heading'"
		:is="headingTag"
		:class="['font-bold', getHeadingAlignClass( field ), field.css_classes ?? '']"
	>
		{{ layoutContent }}
	</component>

	<!-- Paragraph, with the server's sanitized formatting when it sends it -->
	<!-- eslint-disable vue/no-v-html -->
	<p
		v-else-if="field.type === 'paragraph' && field.content_html !== undefined"
		:class="field.css_classes ?? undefined"
		v-html="field.content_html"
	/>
	<!-- eslint-enable vue/no-v-html -->
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
		v-html="htmlContent"
	/>
	<!-- eslint-enable vue/no-v-html -->
</template>
