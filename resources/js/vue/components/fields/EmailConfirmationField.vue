<!--
  Email field with confirmation.

  An email field that asks for the address twice: a `<fieldset>` whose
  `<legend>` is the field label, with the email and confirm inputs side by
  side, each with its own sub-label. The confirmation is stored under
  `{name}_confirmation` (see `getConfirmationKey()`) and a mismatch is the
  field's error, shown once under the group. Pasting into the confirm input
  is never blocked. Mirrors the React `EmailConfirmationField` and the Blade
  `email` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayConfig, FormField } from '../../../types/artisanpack-forms';
import { getSubLabelPlacement } from '../../../shared/composite';
import { getConfirmationKey, getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import { fieldIdKey } from '../../../shared/domIds';

const props = withDefaults( defineProps<{
	field: FormField;
	modelValue: unknown;
	error?: string;
	displayConfig: DisplayConfig;
	/** The address typed into the confirm input. */
	confirmValue?: string;
}>(), {
	error: undefined,
	confirmValue: '',
} );

const emit = defineEmits<{
	'update:modelValue': [value: string];
	'update:confirmValue': [text: string];
}>();

/** Read-only fields stay focusable and announced, but cannot be edited. */
const readOnly = computed( () => isReadOnlyField( props.field ) );

/** Where the description goes; the legend is visually hidden for a hidden label. */
const layout = computed( () => getFieldLayout( props.field, props.displayConfig ) );
const hiddenLabel = computed( () =>
	layout.value.label === 'hidden' || ( !layout.value.custom && props.displayConfig.label_position === 'hidden' ),
);

/** Where the email and confirm inputs show their sub-labels. */
const subLabelPlacement = computed( () => getSubLabelPlacement( props.field ) );

const hintId = computed( () => ( props.field.help_text ? `${fieldIdKey( props.field )}-hint` : undefined ) );
const errorId = computed( () => ( props.error ? `${fieldIdKey( props.field )}-error` : undefined ) );
const describedBy = computed( () => [hintId.value, errorId.value].filter( Boolean ).join( ' ' ) || undefined );

/** The email and confirm inputs. */
const inputs = computed( () => [
	{
		id: `field-${fieldIdKey( props.field )}`,
		name: props.field.name,
		label: props.field.email_confirmation?.email ?? '',
		placeholder: props.field.placeholder ?? undefined,
		value: String( props.modelValue ?? '' ),
		confirm: false,
	},
	{
		id: `field-${fieldIdKey( props.field )}_confirmation`,
		name: getConfirmationKey( props.field ),
		label: props.field.email_confirmation?.confirm ?? '',
		placeholder: props.field.email_confirmation?.confirm_placeholder ?? undefined,
		value: props.confirmValue,
		confirm: true,
	},
] );

function update( confirm: boolean, text: string ): void {
	if ( confirm ) {
		emit( 'update:confirmValue', text );
	} else {
		emit( 'update:modelValue', text );
	}
}

function subLabelClass(): string {
	if ( subLabelPlacement.value === 'below' ) {
		return 'fieldset-label text-xs';
	}

	return subLabelPlacement.value === 'hidden' ? 'label text-sm sr-only' : 'label text-sm';
}
</script>

<template>
	<fieldset :id="`fieldset-${fieldIdKey( field )}`" :class="['fieldset', field.css_classes ?? '']" :aria-describedby="describedBy">
		<legend v-if="field.label" :class="['fieldset-legend', hiddenLabel ? 'sr-only' : '']">
			{{ field.label }}
			<span v-if="field.is_required" class="text-error ml-1" aria-hidden="true">*</span>
		</legend>

		<p v-if="hintId && layout.description === 'above'" :id="hintId" class="fieldset-label">
			{{ field.help_text }}
		</p>

		<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
			<div v-for="input in inputs" :key="input.id" class="flex flex-col gap-1">
				<label v-if="subLabelPlacement !== 'below'" :for="input.id" :class="subLabelClass()">
					{{ input.label }}
				</label>
				<input
					:id="input.id"
					type="email"
					:name="input.name"
					:class="['input input-bordered w-full', error ? 'input-error' : '']"
					:value="input.value"
					:placeholder="input.placeholder"
					:autocomplete="field.autocomplete ?? undefined"
					:required="field.is_required"
					:aria-required="field.is_required ? 'true' : undefined"
					:aria-invalid="error ? true : undefined"
					:aria-describedby="errorId"
					:readonly="readOnly"
					@input="update( input.confirm, ( $event.target as HTMLInputElement ).value )"
				/>
				<label v-if="subLabelPlacement === 'below'" :for="input.id" :class="subLabelClass()">
					{{ input.label }}
				</label>
			</div>
		</div>

		<p v-if="hintId && layout.description !== 'above'" :id="hintId" class="fieldset-label">
			{{ field.help_text }}
		</p>

		<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">
			{{ error }}
		</p>
	</fieldset>
</template>
