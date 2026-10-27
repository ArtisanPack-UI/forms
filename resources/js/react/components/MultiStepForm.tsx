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

import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@artisanpack-ui/react/form';

import { focusFirstField, focusFirstInvalid } from '../../shared/focus';
import { t } from '../../shared/i18n';
import type { FormStep } from '../../types/artisanpack-forms';

/** Props for the MultiStepForm component. */
export interface MultiStepFormProps {
	/** The current step definition. */
	currentStepData: FormStep;
	/** The current step index (0-based). */
	currentStep: number;
	/** Total number of steps. */
	totalSteps: number;
	/**
	 * The form's steps in order, so the indicators show their titles as the
	 * Livewire renderer does (since 1.6.0). Numbers are shown without them.
	 */
	steps?: FormStep[];
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
 * On a step change it announces the new step in a polite live region and
 * moves focus to the step's first invalid field, else its first field, else
 * its heading, mirroring the Livewire renderer (since 1.6.0). The current
 * step's indicator is marked `aria-current="step"`, and with step
 * navigation on, only the steps up to the current one are buttons, as in
 * Blade (since 1.6.0).
 *
 * @example
 * ```tsx
 * <MultiStepForm
 *   currentStepData={currentStepData}
 *   currentStep={currentStep}
 *   totalSteps={totalSteps}
 *   steps={sortedSteps}
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
	steps,
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
	const contentRef = useRef<HTMLDivElement>( null );
	const headingRef = useRef<HTMLHeadingElement>( null );
	const shownStep = useRef( currentStep );
	const [announcement, setAnnouncement] = useState( '' );

	// Announce a new step and move focus into it (not on the first render)
	useEffect( () => {
		if ( shownStep.current === currentStep ) {
			return;
		}

		shownStep.current = currentStep;
		setAnnouncement( t( 'Now on step :current of :total', { current: currentStep + 1, total: totalSteps } ) );

		if ( !focusFirstInvalid( contentRef.current ) && !focusFirstField( contentRef.current ) ) {
			( headingRef.current ?? contentRef.current )?.focus();
		}
	}, [currentStep, totalSteps] );

	return (
		<div className="space-y-6">
			{/* Step changes, announced politely */}
			<div className="sr-only" role="status" aria-live="polite" aria-atomic="true">{announcement}</div>

			{/* Progress bar */}
			{showProgressBar && (
				<div className="space-y-2">
					<div className="flex justify-between text-sm opacity-70">
						<span>{t( 'Step :current of :total', { current: currentStep + 1, total: totalSteps } )}</span>
						<span>{t( ':percentage% complete', { percentage: progressPercentage } )}</span>
					</div>
					<progress
						className="progress progress-primary w-full"
						value={progressPercentage}
						max="100"
						aria-label={t( 'Form progress: :percentage% complete', { percentage: progressPercentage } )}
					/>
				</div>
			)}

			{/* Step indicators: only the steps reached are buttons, as in Blade */}
			{totalSteps > 1 && (
				<ul className="steps steps-horizontal w-full" aria-label={t( 'Form progress' )}>
					{Array.from( { length: totalSteps }, ( _, i ) => {
						const title = steps?.[i]?.title || undefined;

						return (
							<li
								key={i}
								className={`step ${i <= currentStep ? 'step-primary' : ''}`}
							>
								{allowStepNavigation && i <= currentStep ? (
									<button
										type="button"
										className="cursor-pointer hover:opacity-80"
										onClick={() => onGoToStep( i )}
										aria-current={i === currentStep ? 'step' : undefined}
										aria-label={title ? undefined : t( 'Go to step :number', { number: i + 1 } )}
									>
										{title ?? i + 1}
									</button>
								) : (
									<span aria-current={i === currentStep ? 'step' : undefined}>{title ?? i + 1}</span>
								)}
							</li>
						);
					} )}
				</ul>
			)}

			{/* Step header */}
			{( currentStepData.title || currentStepData.description ) && (
				<div className="space-y-1">
					{currentStepData.title && (
						<h3 ref={headingRef} tabIndex={-1} className="text-lg font-semibold outline-none">{currentStepData.title}</h3>
					)}
					{currentStepData.description && (
						<p className="text-sm opacity-70">{currentStepData.description}</p>
					)}
				</div>
			)}

			{/* Form fields */}
			<div ref={contentRef} tabIndex={-1} className="outline-none">
				{children}
			</div>

			{/* Navigation buttons */}
			<div className="flex justify-between gap-4">
				<div>
					{currentStep > 0 && (
						<Button
							type="button"
							onClick={onPrevStep}
						>
							{currentStepData.prev_button_text || t( 'Previous' )}
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
							{isSubmitting ? t( 'Submitting...' ) : submitButtonText}
						</Button>
					) : (
						<Button
							type="button"
							color="primary"
							onClick={onNextStep}
						>
							{currentStepData.next_button_text || t( 'Next' )}
						</Button>
					)}
				</div>
			</div>
		</div>
	);
}
