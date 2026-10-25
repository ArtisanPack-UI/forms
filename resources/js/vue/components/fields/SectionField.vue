<!--
  Section component.

  Wraps the fields a section groups (the default slot) in a `<fieldset>`
  whose `<legend>` is the section's label, with its description (since
  1.6.0; see `getFieldSegments()`). A collapsible section's legend is a
  disclosure button (`aria-expanded`, `aria-controls`); it opens when one of
  its fields has an error. A hidden section's fields are hidden by
  conditional logic; its fieldset stays shown, without the legend, only when
  it holds a CAPTCHA, which is never hidden. Mirrors the Blade `section`
  partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { FormField } from '../../../types/artisanpack-forms';
import { getSectionDescription, isCollapsibleSection, startsCollapsed } from '../../../shared/sections';

const props = withDefaults( defineProps<{
	/** The section field. */
	field: FormField;
	/** Whether conditional logic hides the section. */
	hidden?: boolean;
	/** Whether one of the section's fields is a CAPTCHA, which stays shown. */
	holdsCaptcha?: boolean;
	/** Whether one of the section's fields has an error. */
	hasErrors?: boolean;
}>(), {
	hidden: false,
	holdsCaptcha: false,
	hasErrors: false,
} );

const collapsible = computed( () => isCollapsibleSection( props.field ) );
const description = computed( () => getSectionDescription( props.field ) );
const contentId = computed( () => `section-${props.field.name}-content` );
const descriptionId = computed( () => `section-${props.field.name}-description` );
const open = ref( !startsCollapsed( props.field ) );

// A collapsed section opens to show its errors.
watch( () => props.hasErrors, ( hasErrors ) => {
	if ( hasErrors ) {
		open.value = true;
	}
}, { immediate: true } );
</script>

<template>
	<fieldset
		v-if="!hidden || holdsCaptcha"
		:id="`section-${field.name}`"
		:class="['fieldset w-full rounded-box border border-base-300 p-4', field.css_classes ?? '']"
		:data-forms-section="field.name"
		:aria-describedby="description && !hidden ? descriptionId : undefined"
	>
		<legend v-if="!hidden" class="fieldset-legend text-lg font-semibold">
			<button
				v-if="collapsible"
				type="button"
				class="flex items-center gap-2 rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
				:aria-controls="contentId"
				:aria-expanded="open ? 'true' : 'false'"
				@click="open = !open"
			>
				<span aria-hidden="true" :class="['inline-block transition-transform', open ? 'rotate-90' : '']">›</span>
				<span>{{ field.label }}</span>
			</button>
			<template v-else>{{ field.label }}</template>
		</legend>

		<p v-if="description && !hidden" :id="descriptionId" class="text-sm text-base-content/70 whitespace-pre-line">{{ description }}</p>

		<div :id="contentId" :class="['mt-2 flex-wrap gap-4', collapsible && !open && !hidden ? 'hidden' : 'flex']">
			<slot />
		</div>
	</fieldset>
</template>
