<!--
  Honeypot spam protection field.

  Renders a hidden input that bots fill in but humans don't see.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
defineProps<{
	slug: string;
	fieldName: string;
}>();
</script>

<template>
	<div
		style="position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden"
		aria-hidden="true"
	>
		<label :for="`ap-hp-${slug}`">Leave this field empty</label>
		<input
			type="text"
			:id="`ap-hp-${slug}`"
			:name="fieldName"
			tabindex="-1"
			autocomplete="off"
		/>
	</div>
</template>
