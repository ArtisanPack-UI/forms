/**
 * Multi-step form navigation component.
 *
 * Renders step progress bar, step title/description,
 * and navigation buttons for multi-step forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import type { ReactNode } from 'react';
import { Button } from '@artisanpack-ui/react/form';

import type { FormStep } from '../../types/artisanpack-forms';

/** Props for the MultiStepForm component. */
export interface MultiStepFormProps {
	/** The current step definition. */
	currentStepData: FormStep;
	/** The current step index (0-based). */
	currentStep: number;
	/** Total number of steps. */
	totalSteps: number;
	/** Progress percentage (0-100). */
	progressPercentage: number;
	/** Whether to show the progress bar. */
	showProgressBar: boolean;
	/** Whether to allow clicking on step indicators to navigate. */
	allowStepNavigation: boolean;
	/** Whether the form is currently submitting. */
	isSubmitting: boolean;
	/** Whether this is the last step. */
	isLastStep: boolean;
	/** Callback to navigate to the next step. */
	onNextStep: () => void;
	/** Callback to navigate to the previous step. */
	onPrevStep: () => void;
	/** Callback to navigate to a specific step. */
	onGoToStep: ( step: number ) => void;
	/** Callback to submit the form (on the last step). */
	onSubmit: () => void;
	/** The submit button text from the form definition. */
	submitButtonText: string;
	/** The rendered form fields for the current step. */
	children: ReactNode;
}

/**
 * Multi-step form wrapper with progress bar and step navigation.
 *
 * @example
 * ```tsx
 * <MultiStepForm
 *   currentStepData={currentStepData}
 *   currentStep={currentStep}
 *   totalSteps={totalSteps}
 *   progressPercentage={progressPercentage}
 *   showProgressBar={form.show_progress_bar}
 *   allowStepNavigation={form.allow_step_navigation}
 *   isSubmitting={isSubmitting}
 *   isLastStep={currentStep === totalSteps - 1}
 *   onNextStep={nextStep}
 *   onPrevStep={prevStep}
 *   onGoToStep={goToStep}
 *   onSubmit={submit}
 *   submitButtonText={form.submit_button_text}
 * >
 *   {renderedFields}
 * </MultiStepForm>
 * ```
 */
export function MultiStepForm( {
	currentStepData,
	currentStep,
	totalSteps,
	progressPercentage,
	showProgressBar,
	allowStepNavigation,
	isSubmitting,
	isLastStep,
	onNextStep,
	onPrevStep,
	onGoToStep,
	onSubmit,
	submitButtonText,
	children,
}: MultiStepFormProps ) {
	return (
		<div className="space-y-6">
			{/* Progress bar */}
			{showProgressBar && (
				<div className="space-y-2">
					<div className="flex justify-between text-sm opacity-70">
						<span>Step {currentStep + 1} of {totalSteps}</span>
						<span>{progressPercentage}%</span>
					</div>
					<progress
						className="progress progress-primary w-full"
						value={progressPercentage}
						max="100"
						aria-label={`Form progress: step ${currentStep + 1} of ${totalSteps}`}
					/>
				</div>
			)}

			{/* Step indicators */}
			{totalSteps > 1 && (
				<ul className="steps steps-horizontal w-full">
					{Array.from( { length: totalSteps }, ( _, i ) => (
						<li
							key={i}
							className={`step ${i <= currentStep ? 'step-primary' : ''}`}
						>
							{allowStepNavigation ? (
								<button
									type="button"
									className="cursor-pointer hover:opacity-80"
									onClick={() => onGoToStep( i )}
									aria-label={`Go to step ${i + 1}`}
								>
									{i + 1}
								</button>
							) : (
								<span>{i + 1}</span>
							)}
						</li>
					) )}
				</ul>
			)}

			{/* Step header */}
			{( currentStepData.title || currentStepData.description ) && (
				<div className="space-y-1">
					{currentStepData.title && (
						<h3 className="text-lg font-semibold">{currentStepData.title}</h3>
					)}
					{currentStepData.description && (
						<p className="text-sm opacity-70">{currentStepData.description}</p>
					)}
				</div>
			)}

			{/* Form fields */}
			{children}

			{/* Navigation buttons */}
			<div className="flex justify-between gap-4">
				<div>
					{currentStep > 0 && (
						<Button
							type="button"
							onClick={onPrevStep}
						>
							{currentStepData.prev_button_text || 'Previous'}
						</Button>
					)}
				</div>
				<div>
					{isLastStep ? (
						<Button
							type="button"
							color="primary"
							onClick={onSubmit}
							disabled={isSubmitting}
						>
							{isSubmitting ? 'Submitting...' : submitButtonText}
						</Button>
					) : (
						<Button
							type="button"
							color="primary"
							onClick={onNextStep}
						>
							{currentStepData.next_button_text || 'Next'}
						</Button>
					)}
				</div>
			</div>
		</div>
	);
}
