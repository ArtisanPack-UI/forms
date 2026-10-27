<!--
  Honeypot spam protection field.

  Renders a hidden input that bots fill in but humans don't see.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { t } from '../../shared/i18n';

withDefaults( defineProps<{
	slug: string;
	fieldName: string;
	/** The form instance's ID prefix, so two forms on a page do not share the ID (since 1.6.0). */
	idPrefix?: string;
}>(), {
	idPrefix: '',
} );
</script>

<template>
	<div
		style="position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden"
		aria-hidden="true"
	>
		<label :for="`ap-hp-${idPrefix}${slug}`">{{ t( 'Leave this field empty' ) }}</label>
		<input
			type="text"
			:id="`ap-hp-${idPrefix}${slug}`"
			:name="fieldName"
			tabindex="-1"
			autocomplete="off"
		/>
	</div>
</template>
