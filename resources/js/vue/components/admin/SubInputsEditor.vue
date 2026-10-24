<!--
  Sub-input editor for composite fields.

  A table that turns each of a composite field's sub-inputs on or off,
  relabels it, sets its placeholder, default value and population parameter
  (since 1.6.0) and whether a required field requires it. A select
  sub-input's default is one of its options. The last shown input cannot be
  turned off. Mirrors the React
  `SubInputsEditor` and the Livewire builder's `sub-inputs-editor` partial.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.6.0
-->
<script setup lang="ts">
import { computed } from 'vue';

import { buildSubInputUpdate, getEnabledSubInputs } from '../../../shared/composite';
import type { SubInputSetting } from '../../../shared/composite';
import { t } from '../../../shared/i18n';
import type { FormField, UpdateFieldRequest } from '../../../types/artisanpack-forms';

const props = defineProps<{
	/** The composite field being edited. */
	field: FormField;
}>();

const emit = defineEmits<{
	/** The field update for one change, and the change itself. */
	change: [data: UpdateFieldRequest, key: string, setting: SubInputSetting, value: unknown];
}>();

const enabledCount = computed( () => getEnabledSubInputs( props.field ).length );
const labelId = computed( () => `sub-inputs-label-${props.field.id}` );
const hintId = computed( () => `sub-inputs-hint-${props.field.id}` );

function change( key: string, setting: SubInputSetting, value: unknown ): void {
	const update = buildSubInputUpdate( props.field, key, setting, value );

	if ( update ) {
		emit( 'change', update, key, setting, value );
	}
}
</script>

<template>
	<div class="space-y-1">
		<span :id="labelId" class="label-text font-medium">{{ t( 'Inputs' ) }}</span>
		<div class="overflow-x-auto">
			<table class="table table-xs" :aria-labelledby="labelId" :aria-describedby="hintId">
				<thead>
					<tr>
						<th scope="col">{{ t( 'Show' ) }}</th>
						<th scope="col">{{ t( 'Sub-Label' ) }}</th>
						<th scope="col">{{ t( 'Placeholder' ) }}</th>
						<th scope="col">{{ t( 'Default' ) }}</th>
						<th scope="col">{{ t( 'Parameter' ) }}</th>
						<th scope="col">{{ t( 'Required' ) }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="subInput in field.sub_inputs ?? []" :key="subInput.key">
						<td>
							<input
								type="checkbox"
								class="checkbox checkbox-xs checkbox-primary"
								:aria-label="t( 'Show :input', { input: subInput.default_label } )"
								:checked="subInput.enabled"
								:disabled="subInput.enabled && enabledCount === 1"
								@change="change( subInput.key, 'enabled', ( $event.target as HTMLInputElement ).checked )"
							/>
						</td>
						<td>
							<input
								type="text"
								class="input input-xs input-bordered w-full"
								:aria-label="t( ':input sub-label', { input: subInput.default_label } )"
								:placeholder="subInput.default_label"
								:value="subInput.label === subInput.default_label ? '' : subInput.label"
								maxlength="255"
								@change="change( subInput.key, 'label', ( $event.target as HTMLInputElement ).value )"
							/>
						</td>
						<td>
							<input
								type="text"
								class="input input-xs input-bordered w-full"
								:aria-label="t( ':input placeholder', { input: subInput.default_label } )"
								:value="subInput.placeholder ?? ''"
								maxlength="255"
								@change="change( subInput.key, 'placeholder', ( $event.target as HTMLInputElement ).value )"
							/>
						</td>
						<td>
							<select
								v-if="subInput.type === 'select'"
								class="select select-xs select-bordered w-full"
								:aria-label="t( ':input default value', { input: subInput.default_label } )"
								:value="subInput.default ?? ''"
								@change="change( subInput.key, 'default', ( $event.target as HTMLSelectElement ).value )"
							>
								<option value="">{{ t( 'None' ) }}</option>
								<option v-for="option in subInput.options ?? []" :key="option.value" :value="option.value">
									{{ option.label }}
								</option>
							</select>
							<input
								v-else
								type="text"
								class="input input-xs input-bordered w-full"
								:aria-label="t( ':input default value', { input: subInput.default_label } )"
								:value="subInput.default ?? ''"
								maxlength="255"
								@change="change( subInput.key, 'default', ( $event.target as HTMLInputElement ).value )"
							/>
						</td>
						<td>
							<input
								type="text"
								class="input input-xs input-bordered w-full font-mono"
								:aria-label="t( ':input population parameter', { input: subInput.default_label } )"
								:value="subInput.parameter ?? ''"
								maxlength="100"
								@change="change( subInput.key, 'parameter', ( $event.target as HTMLInputElement ).value )"
							/>
						</td>
						<td>
							<input
								type="checkbox"
								class="checkbox checkbox-xs checkbox-primary"
								:aria-label="t( 'Require :input', { input: subInput.default_label } )"
								:checked="subInput.required"
								@change="change( subInput.key, 'required', ( $event.target as HTMLInputElement ).checked )"
							/>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<p :id="hintId" class="text-xs text-base-content/60">
			{{ t( 'Required inputs apply when the field is required. With none checked, every shown input is required.' ) }}
			{{ t( 'A parameter fills the input from the renderer prefill or the query string.' ) }}
		</p>
	</div>
</template>
