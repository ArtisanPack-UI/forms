<!--
  Signature field component.

  A canvas pad to draw on with a mouse, finger or stylus, with a Clear
  button, or a text input to type a full name instead for visitors who can't
  draw (since 1.6.0; see `shared/signature.ts`). "Draw" and "Type your name"
  are toggle buttons, and switching clears the other method. A drawing is
  sent as a PNG data URL once a stroke ends. The pad is an image to screen
  readers. Mirrors the Blade `signature` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getFieldLayout } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import {
	getSignatureMode,
	getSignaturePadHeight,
	isDrawnSignature,
	SIGNATURE_TYPED_MAX_LENGTH,
	SignaturePad,
} from '../../../shared/signature';
import type { SignatureMode } from '../../../shared/signature';
import FieldLayout from './FieldLayout.vue';

const props = defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
}>();

const emit = defineEmits<{
	'update:modelValue': [value: string];
}>();

const padHeight = computed( () => getSignaturePadHeight( props.field ) );
const idPrefix = computed( () => `field-${props.field.name}-` );

/** Where the label and description go (see `FieldLayout.vue`). */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );

const labelId = computed( () => {
	if ( !props.field.label ) {
		return undefined;
	}

	return layout.value.custom ? layout.value.labelId : `${props.field.name}-label`;
} );
const hintId = computed( () => {
	if ( layout.value.custom ) {
		return props.field.help_text ? layout.value.descriptionId : undefined;
	}

	return props.field.help_text && !props.error ? `${props.field.name}-hint` : undefined;
} );
const errorId = computed( () => ( props.error ? `${props.field.name}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );

const mode = ref<SignatureMode>( getSignatureMode( props.modelValue ) );
const typed = ref( mode.value === 'type' ? String( props.modelValue ) : '' );
const hasInk = ref( isDrawnSignature( props.modelValue ) );
const announcement = ref( '' );
const canvas = ref<HTMLCanvasElement | null>( null );
const typedInput = ref<HTMLInputElement | null>( null );
let pad: SignaturePad | null = null;

// Set the pad up once, redrawing a drawing already in the form data.
onMounted( () => {
	if ( !canvas.value ) {
		return;
	}

	const signaturePad = new SignaturePad( canvas.value, padHeight.value, () => {
		hasInk.value = true;
		emit( 'update:modelValue', signaturePad.toDataUrl() );
	} );

	if ( isDrawnSignature( props.modelValue ) ) {
		signaturePad.fromDataUrl( props.modelValue );
	}

	pad = signaturePad;
} );

onBeforeUnmount( () => pad?.destroy() );

/** Clear the drawing and announce it. */
function clear(): void {
	pad?.clear();
	hasInk.value = false;
	announcement.value = t( 'Signature cleared.' );
	emit( 'update:modelValue', '' );
}

/** Switch between drawing and typing, clearing the other method. */
async function choose( next: SignatureMode ): Promise<void> {
	if ( next === mode.value ) {
		return;
	}

	mode.value = next;
	announcement.value = '';
	pad?.clear();
	hasInk.value = false;

	if ( next === 'type' ) {
		emit( 'update:modelValue', typed.value.trim() );
		await nextTick();
		typedInput.value?.focus();
	} else {
		typed.value = '';
		emit( 'update:modelValue', '' );
	}
}

/** Store the typed name. */
function onTyped( event: Event ): void {
	typed.value = ( event.target as HTMLInputElement ).value;
	emit( 'update:modelValue', typed.value.trim() );
}
</script>

<template>
	<FieldLayout :field="field" :layout="layout" group>
		<div class="fieldset">
			<span
				v-if="!layout.custom && field.label"
				:id="labelId"
				:class="['fieldset-legend', displayConfig.label_position === 'hidden' ? 'sr-only' : '']"
			>
				{{ field.label }}
				<span v-if="field.is_required" class="text-error ml-1">*</span>
			</span>

			<div
				role="group"
				:class="['flex flex-col gap-2', field.css_classes ?? '']"
				:aria-labelledby="labelId"
				:aria-label="labelId ? undefined : field.name"
				:aria-describedby="describedBy"
			>
				<div class="join" role="group" :aria-label="t( 'Signature method' )">
					<button
						type="button"
						:class="['btn btn-sm join-item', mode === 'draw' ? 'btn-primary' : '']"
						:aria-pressed="mode === 'draw' ? 'true' : 'false'"
						@click="choose( 'draw' )"
					>
						{{ t( 'Draw' ) }}
					</button>
					<button
						type="button"
						:class="['btn btn-sm join-item', mode === 'type' ? 'btn-primary' : '']"
						:aria-pressed="mode === 'type' ? 'true' : 'false'"
						@click="choose( 'type' )"
					>
						{{ t( 'Type your name' ) }}
					</button>
				</div>

				<div v-show="mode === 'draw'">
					<div class="relative rounded-box border border-base-300 bg-white">
						<canvas
							:id="`${idPrefix}pad`"
							ref="canvas"
							class="block w-full cursor-crosshair touch-none select-none rounded-box"
							:style="{ height: `${padHeight}px` }"
							role="img"
							:aria-label="t( 'Signature pad. Draw your signature with a mouse, finger or stylus, or choose Type your name instead.' )"
						/>
						<span class="pointer-events-none absolute inset-x-6 bottom-6 border-b border-dashed border-gray-300" aria-hidden="true" />
					</div>
					<div class="mt-1 flex items-center justify-between gap-2">
						<span class="text-xs text-base-content/60">{{ t( 'Sign above the line.' ) }}</span>
						<button type="button" class="btn btn-ghost btn-xs" :disabled="!hasInk" @click="clear">
							{{ t( 'Clear signature' ) }}
						</button>
					</div>
				</div>

				<div v-show="mode === 'type'" class="flex flex-col gap-2">
					<label :for="`${idPrefix}typed`" class="label text-sm">{{ t( 'Type your full name' ) }}</label>
					<input
						:id="`${idPrefix}typed`"
						ref="typedInput"
						type="text"
						class="input input-bordered w-full"
						:value="typed"
						:maxlength="SIGNATURE_TYPED_MAX_LENGTH"
						autocomplete="name"
						:aria-invalid="error ? 'true' : undefined"
						:aria-describedby="describedBy"
						@input="onTyped"
					/>
					<p class="min-h-10 border-b border-base-300 px-2 font-serif text-2xl italic" aria-hidden="true">{{ typed }}</p>
				</div>

				<div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
			</div>

			<p v-if="!layout.custom && hintId" :id="hintId" class="fieldset-label">
				{{ field.help_text }}
			</p>
			<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
				{{ error }}
			</p>
		</div>
	</FieldLayout>
</template>
