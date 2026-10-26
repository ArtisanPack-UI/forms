<!--
  Image choice component.

  One image choice of a radio or checkbox group (since 1.6.0): a card with
  the option's image above its input (the default slot) and label, mirroring
  the Blade `image-choice` partial. The input is named by the label alone
  through `aria-labelledby`, so the label stays the accessible name; the
  image's alt text describes what it shows.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { FieldOption } from '../../../types/artisanpack-forms';
import { getChoiceImageAlt, getChoiceImageUrl } from '../../../shared/choices';

const props = defineProps<{
	option: FieldOption;
	/** The label's ID, which the input's `aria-labelledby` names. */
	labelId: string;
}>();

const image = computed( () => getChoiceImageUrl( props.option ) );
const alt = computed( () => getChoiceImageAlt( props.option ) );
</script>

<template>
	<label class="flex cursor-pointer flex-col overflow-hidden rounded-box border-2 border-base-300 bg-base-100 transition-colors hover:border-primary/50 has-[:checked]:border-primary has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
		<img v-if="image" :src="image" :alt="alt" class="aspect-square w-full bg-base-200 object-cover" loading="lazy" />
		<span v-else class="block aspect-square w-full bg-base-200" aria-hidden="true" />
		<span class="flex items-center gap-2 p-3">
			<slot />
			<span :id="labelId" class="label-text">{{ option.label }}</span>
		</span>
	</label>
</template>
