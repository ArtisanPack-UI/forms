<!--
  Multi-step form navigation component.

  Renders step progress bar, step title/description,
  and navigation buttons for multi-step forms.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@artisanpack-ui/vue/form';
import type { FormStep } from '../../types/artisanpack-forms';

const props = defineProps<{
	currentStepData: FormStep;
	currentStep: number;
	totalSteps: number;
	progressPercentage: number;
	showProgressBar: boolean;
	allowStepNavigation: boolean;
	isSubmitting: boolean;
	isLastStep: boolean;
	submitButtonText: string;
}>();

const emit = defineEmits<{
	nextStep: [];
	prevStep: [];
	goToStep: [step: number];
	submit: [];
}>();

const stepNumbers = computed( () =>
	Array.from( { length: props.totalSteps }, ( _, i ) => i ),
);
</script>

<template>
	<div class="space-y-6">
		<!-- Progress bar -->
		<div v-if="showProgressBar" class="space-y-2">
			<div class="flex justify-between text-sm opacity-70">
				<span>Step {{ currentStep + 1 }} of {{ totalSteps }}</span>
				<span>{{ progressPercentage }}%</span>
			</div>
			<progress
				class="progress progress-primary w-full"
				:value="progressPercentage"
				max="100"
				:aria-label="`Form progress: step ${currentStep + 1} of ${totalSteps}`"
			/>
		</div>

		<!-- Step indicators -->
		<ul v-if="totalSteps > 1" class="steps steps-horizontal w-full">
			<li
				v-for="i in stepNumbers"
				:key="i"
				:class="['step', i <= currentStep ? 'step-primary' : '']"
			>
				<button
					v-if="allowStepNavigation"
					type="button"
					class="cursor-pointer hover:opacity-80"
					:disabled="isSubmitting"
					:aria-label="`Go to step ${i + 1}`"
					@click="!isSubmitting && emit( 'goToStep', i )"
				>
					{{ i + 1 }}
				</button>
				<span v-else>{{ i + 1 }}</span>
			</li>
		</ul>

		<!-- Step header -->
		<div v-if="currentStepData.title || currentStepData.description" class="space-y-1">
			<h3 v-if="currentStepData.title" class="text-lg font-semibold">
				{{ currentStepData.title }}
			</h3>
			<p v-if="currentStepData.description" class="text-sm opacity-70">
				{{ currentStepData.description }}
			</p>
		</div>

		<!-- Form fields (slot) -->
		<Transition name="fade" mode="out-in">
			<div :key="currentStep">
				<slot />
			</div>
		</Transition>

		<!-- Navigation buttons -->
		<div class="flex justify-between gap-4">
			<div>
				<Button
					v-if="currentStep > 0"
					type="button"
					:disabled="isSubmitting"
					@click="emit( 'prevStep' )"
				>
					{{ currentStepData.prev_button_text || 'Previous' }}
				</Button>
			</div>
			<div>
				<Button
					v-if="isLastStep"
					type="button"
					color="primary"
					:disabled="isSubmitting"
					@click="emit( 'submit' )"
				>
					{{ isSubmitting ? 'Submitting...' : submitButtonText }}
				</Button>
				<Button
					v-else
					type="button"
					color="primary"
					:disabled="isSubmitting"
					@click="emit( 'nextStep' )"
				>
					{{ currentStepData.next_button_text || 'Next' }}
				</Button>
			</div>
		</div>
	</div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
	transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
	opacity: 0;
}
</style>
