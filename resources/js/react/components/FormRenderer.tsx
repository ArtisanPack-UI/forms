/**
 * FormRenderer component.
 *
 * Main component that fetches a form definition from the API and renders
 * all fields with conditional logic, multi-step navigation, validation,
 * honeypot protection, and submission handling.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { Button } from '@artisanpack-ui/react/form';

import type { SubmitFormResponse } from '../../types/artisanpack-forms';
import { useForm } from '../hooks/useForm';
import { FieldRenderer } from './fields/FieldRenderer';
import type { FieldComponentMap } from './fields/registry';
import { MultiStepForm } from './MultiStepForm';

/** Props for the FormRenderer component. */
export interface FormRendererProps {
	/** Base URL for the forms API (e.g. "https://example.com/api/v1/forms"). */
	baseUrl: string;
	/** The form slug or ID used in the API URL. */
	formSlug: string;
	/** Optional callback when submission succeeds. */
	onSuccess?: ( response: SubmitFormResponse ) => void;
	/** Optional callback when submission fails. */
	onError?: ( error: Error ) => void;
	/** Whether to validate on the client before submitting. Defaults to true. */
	clientValidation?: boolean;
	/** Optional CSS class for the form wrapper. */
	className?: string;
	/** Custom loading component. */
	loadingComponent?: React.ReactNode;
	/** Custom error component. Receives the error message as a prop. */
	errorComponent?: React.ComponentType<{ message: string }>;
	/** Custom success component. Receives the success message as a prop. */
	successComponent?: React.ComponentType<{ message: string }>;
	/**
	 * Host-supplied field components that overlay the built-ins, letting a
	 * custom `field.type` (e.g. `booking_slot`) resolve to a host component
	 * instead of rendering nothing. Overlays the module-level registry too.
	 */
	fieldComponents?: FieldComponentMap;
}

/**
 * Default loading skeleton.
 */
function DefaultLoading() {
	return (
		<div className="animate-pulse space-y-4">
			<div className="h-8 bg-base-300 rounded w-1/3" />
			<div className="space-y-3">
				<div className="h-10 bg-base-300 rounded" />
				<div className="h-10 bg-base-300 rounded" />
				<div className="h-10 bg-base-300 rounded" />
			</div>
			<div className="h-10 bg-base-300 rounded w-1/4" />
		</div>
	);
}

/**
 * Default error display.
 */
function DefaultError( { message }: { message: string } ) {
	return (
		<div className="alert alert-error" role="alert">
			<span>{message}</span>
		</div>
	);
}

/**
 * Default success display.
 */
function DefaultSuccess( { message }: { message: string } ) {
	return (
		<div className="alert alert-success" role="status">
			<span>{message}</span>
		</div>
	);
}

/**
 * Renders an ArtisanPack form from the API.
 *
 * Handles the complete form lifecycle: fetching the definition, rendering
 * fields, evaluating conditional logic, navigating multi-step forms,
 * validating input, and submitting via the API.
 *
 * @example
 * ```tsx
 * <FormRenderer
 *   baseUrl="/api/v1/forms"
 *   formSlug="contact-us"
 *   onSuccess={(response) => console.log('Submitted!', response)}
 * />
 * ```
 *
 * @example
 * ```tsx
 * // With custom components
 * <FormRenderer
 *   baseUrl="/api/v1/forms"
 *   formSlug="feedback"
 *   loadingComponent={<MySpinner />}
 *   errorComponent={MyErrorAlert}
 *   successComponent={MyThankYou}
 * />
 * ```
 */
export function FormRenderer( {
	baseUrl,
	formSlug,
	onSuccess,
	onError,
	clientValidation = true,
	className,
	loadingComponent,
	errorComponent: ErrorComponent = DefaultError,
	successComponent: SuccessComponent = DefaultSuccess,
	fieldComponents,
}: FormRendererProps ) {
	const {
		form,
		values,
		errors,
		hiddenFields,
		isLoading,
		isSubmitting,
		isSubmitted,
		currentStep,
		totalSteps,
		progressPercentage,
		currentFields,
		currentStepData,
		loadError,
		setValue,
		setFile,
		nextStep,
		prevStep,
		goToStep,
		submit,
		reset,
	} = useForm( {
		baseUrl,
		formSlug,
		onSuccess,
		onError,
		clientValidation,
	} );

	// Loading state
	if ( isLoading ) {
		return <>{loadingComponent ?? <DefaultLoading />}</>;
	}

	// Load error
	if ( loadError ) {
		return <ErrorComponent message={loadError} />;
	}

	// No form loaded
	if ( !form ) {
		return <ErrorComponent message="Form not found." />;
	}

	// Success state
	if ( isSubmitted ) {
		return (
			<div className="space-y-4">
				<SuccessComponent
					message={form.success_message ?? 'Form submitted successfully.'}
				/>
				<Button type="button" onClick={reset}>
					Submit another response
				</Button>
			</div>
		);
	}

	// Render form fields
	const renderedFields = (
		<div className="flex flex-wrap gap-4">
			{currentFields.map( ( field ) => {
				if ( hiddenFields[field.name] ) {
					return null;
				}

				return (
					<FieldRenderer
						key={field.id}
						field={field}
						value={values[field.name]}
						error={errors[field.name]?.[0]}
						onChange={( value ) => setValue( field.name, value )}
						onFileChange={( file ) => setFile( field.name, file )}
						displayConfig={form.config.display}
						fieldComponents={fieldComponents}
					/>
				);
			} )}

			{/* Honeypot field (hidden from real users) */}
			{form.config.honeypot.enabled && (
				<div
					style={{ position: 'absolute', left: '-9999px', opacity: 0, height: 0, overflow: 'hidden' }}
					aria-hidden="true"
				>
					<label htmlFor={`ap-hp-${form.slug}`}>
						Leave this field empty
					</label>
					<input
						type="text"
						id={`ap-hp-${form.slug}`}
						name={form.config.honeypot.field_name}
						tabIndex={-1}
						autoComplete="off"
					/>
				</div>
			)}
		</div>
	);

	// Form-level errors
	const formErrors = errors._form;

	const handleFormSubmit = ( e: React.FormEvent ) => {
		e.preventDefault();
		submit();
	};

	return (
		<form className={className} onSubmit={handleFormSubmit} noValidate>
			{/* Form description */}
			{form.description && (
				<p className="mb-4 opacity-70">{form.description}</p>
			)}

			{/* Form-level error messages */}
			{formErrors && formErrors.length > 0 && (
				<div className="alert alert-error mb-4" role="alert">
					{formErrors.map( ( msg, i ) => (
						<span key={i}>{msg}</span>
					) )}
				</div>
			)}

			{/* Multi-step form */}
			{form.is_multi_step && currentStepData ? (
				<MultiStepForm
					currentStepData={currentStepData}
					currentStep={currentStep}
					totalSteps={totalSteps}
					progressPercentage={progressPercentage}
					showProgressBar={form.show_progress_bar}
					allowStepNavigation={form.allow_step_navigation}
					isSubmitting={isSubmitting}
					isLastStep={currentStep === totalSteps - 1}
					onNextStep={nextStep}
					onPrevStep={prevStep}
					onGoToStep={goToStep}
					onSubmit={submit}
					submitButtonText={form.submit_button_text}
				>
					{renderedFields}
				</MultiStepForm>
			) : (
				<div className="space-y-6">
					{renderedFields}
					<div>
						<Button
							type="submit"
							color="primary"
							disabled={isSubmitting}
						>
							{isSubmitting ? 'Submitting...' : form.submit_button_text}
						</Button>
					</div>
				</div>
			)}
		</form>
	);
}
