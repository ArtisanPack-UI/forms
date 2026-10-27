<!--
  Multi-step form navigation component.

  Renders step progress bar, step title/description,
  and navigation buttons for multi-step forms. On a step change it
  announces the new step in a polite live region and moves focus to the
  step's first invalid field, else its first field, else its heading,
  mirroring the Livewire renderer (since 1.6.0). The current step's
  indicator is marked `aria-current="step"`, and with step navigation on,
  only the steps up to the current one are buttons, as in Blade (since
  1.6.0).

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@artisanpack-ui/vue/form';
import { focusFirstField, focusFirstInvalid } from '../../shared/focus';
import { t } from '../../shared/i18n';
import type { FormStep } from '../../types/artisanpack-forms';

const props = defineProps<{
	currentStepData: FormStep;
	currentStep: number;
	totalSteps: number;
	/**
	 * The form's steps in order, so the indicators show their titles as the
	 * Livewire renderer does (since 1.6.0). Numbers are shown without them.
	 */
	steps?: FormStep[];
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

/** A step's title for its indicator, or undefined to show its number. */
function stepTitle( index: number ): string | undefined {
	return props.steps?.[index]?.title || undefined;
}

/** What the live region last announced (since 1.6.0). */
const announcement = ref( '' );

/** The step heading, focused when the step has no field to focus. */
const heading = ref<HTMLElement | null>( null );

// Announce a new step; focus moves once its fields have entered (see `focusStep()`).
watch( () => props.currentStep, ( step ) => {
	announcement.value = t( 'Now on step :current of :total', { current: step + 1, total: props.totalSteps } );
} );

/** Move focus into a step that has just entered, as Livewire's `focusFirstField()` does. */
function focusStep( element: Element ): void {
	const content = element as HTMLElement;

	if ( !focusFirstInvalid( content ) && !focusFirstField( content ) ) {
		( heading.value ?? content ).focus();
	}
}
</script>

<template>
	<div class="space-y-6">
		<!-- Step changes, announced politely -->
		<div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ announcement }}</div>

		<!-- Progress bar -->
		<div v-if="showProgressBar" class="space-y-2">
			<div class="flex justify-between text-sm opacity-70">
				<span>{{ t( 'Step :current of :total', { current: currentStep + 1, total: totalSteps } ) }}</span>
				<span>{{ t( ':percentage% complete', { percentage: progressPercentage } ) }}</span>
			</div>
			<progress
				class="progress progress-primary w-full"
				:value="progressPercentage"
				max="100"
				:aria-label="t( 'Form progress: :percentage% complete', { percentage: progressPercentage } )"
			/>
		</div>

		<!-- Step indicators: only the steps reached are buttons, as in Blade -->
		<ul v-if="totalSteps > 1" class="steps steps-horizontal w-full" :aria-label="t( 'Form progress' )">
			<li
				v-for="i in stepNumbers"
				:key="i"
				:class="['step', i <= currentStep ? 'step-primary' : '']"
			>
				<button
					v-if="allowStepNavigation && i <= currentStep"
					type="button"
					class="cursor-pointer hover:opacity-80"
					:disabled="isSubmitting"
					:aria-current="i === currentStep ? 'step' : undefined"
					:aria-label="stepTitle( i ) ? undefined : t( 'Go to step :number', { number: i + 1 } )"
					@click="!isSubmitting && emit( 'goToStep', i )"
				>
					{{ stepTitle( i ) ?? i + 1 }}
				</button>
				<span v-else :aria-current="i === currentStep ? 'step' : undefined">{{ stepTitle( i ) ?? i + 1 }}</span>
			</li>
		</ul>

		<!-- Step header -->
		<div v-if="currentStepData.title || currentStepData.description" class="space-y-1">
			<h3 v-if="currentStepData.title" ref="heading" tabindex="-1" class="text-lg font-semibold outline-none">
				{{ currentStepData.title }}
			</h3>
			<p v-if="currentStepData.description" class="text-sm opacity-70">
				{{ currentStepData.description }}
			</p>
		</div>

		<!-- Form fields (slot); focus moves in once the new step has entered -->
		<Transition name="fade" mode="out-in" @after-enter="focusStep">
			<div :key="currentStep" tabindex="-1" class="outline-none">
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
					{{ currentStepData.prev_button_text || t( 'Previous' ) }}
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
					{{ isSubmitting ? t( 'Submitting...' ) : submitButtonText }}
				</Button>
				<Button
					v-else
					type="button"
					color="primary"
					:disabled="isSubmitting"
					@click="emit( 'nextStep' )"
				>
					{{ currentStepData.next_button_text || t( 'Next' ) }}
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
