/**
 * FormRenderer component.
 *
 * Main component that fetches a form definition from the API and renders
 * all fields with conditional logic, multi-step navigation, validation,
 * honeypot protection, and submission handling. Fields reach the form's
 * submit hooks through `SubmitHooksContext` and its fields and values
 * through `FormValuesContext` (since 1.6.0). A section's fields are grouped
 * in its fieldset (since 1.6.0).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { Fragment } from 'react';
import { Button } from '@artisanpack-ui/react/form';

import { getOffStepSubmitCaptchas } from '../../shared/captcha';
import { getSubInputErrors } from '../../shared/composite';
import { getConfirmationKey, requiresEmailConfirmation } from '../../shared/fieldSettings';
import { getFieldSegments } from '../../shared/sections';
import type { FormField, FormLayout, SubmitFormResponse } from '../../types/artisanpack-forms';
import { useForm } from '../hooks/useForm';
import { FieldRenderer } from './fields/FieldRenderer';
import { FormValuesContext } from './fields/FormValuesContext';
import type { FieldComponentMap } from './fields/registry';
import { SectionGroup } from './fields/SectionField';
import { SubmitHooksContext } from './fields/SubmitHooksContext';
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
	/**
	 * How the submit button sits relative to the fields. `stacked` (the
	 * default) renders it below the field row. `inline` renders it as the last
	 * item in the field row, aligned to the bottom of the fields, so a short
	 * form such as a newsletter signup sits on one line and wraps when the row
	 * is too narrow. Multi-step forms ignore this and keep their navigation
	 * below the fields.
	 *
	 * @since 1.5.2
	 */
	layout?: FormLayout;
	/**
	 * Extra CSS classes appended to the submit button's classes, e.g. to hand
	 * the button to a host design system. Applies to single-step forms in both
	 * layouts.
	 *
	 * @since 1.5.2
	 */
	submitClassName?: string;
	/**
	 * Values for fields with dynamic population on, keyed by parameter name.
	 * Takes precedence over the page's query string and the field's default.
	 *
	 * @since 1.6.0
	 */
	prefill?: Record<string, unknown>;
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
 * // Newsletter signup: email field and button on one row
 * <FormRenderer
 *   baseUrl="/api/v1/forms"
 *   formSlug="newsletter"
 *   layout="inline"
 *   submitClassName="wp-element-button"
 * />
 * ```
 *
 * @example
 * ```tsx
 * // Populate fields that allow dynamic population
 * <FormRenderer
 *   baseUrl="/api/v1/forms"
 *   formSlug="signup"
 *   prefill={{ plan: 'pro', utm_source: 'newsletter' }}
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
	layout = 'stacked',
	submitClassName,
	prefill,
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
		otherValues,
		setOtherValue,
		nextStep,
		prevStep,
		goToStep,
		submit,
		reset,
		submitHooks,
	} = useForm( {
		baseUrl,
		formSlug,
		onSuccess,
		onError,
		clientValidation,
		prefill,
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

	// Multi-step forms keep their navigation below the fields, so they ignore
	// the inline layout.
	const isInline = layout === 'inline' && !form.is_multi_step;

	// Inline: a non-growing flex item aligned to the bottom of the fields.
	const submitClasses = [isInline ? 'shrink-0 self-end' : '', submitClassName ?? '']
		.filter( Boolean )
		.join( ' ' ) || undefined;

	const submitButton = (
		<Button
			type="submit"
			color="primary"
			disabled={isSubmitting}
			className={submitClasses}
		>
			{isSubmitting ? 'Submitting...' : form.submit_button_text}
		</Button>
	);

	// Render one field, unless conditional logic hides it
	const renderField = ( field: FormField ) => {
		if ( hiddenFields[field.name] ) {
			return null;
		}

		const confirms = requiresEmailConfirmation( field );

		return (
			<FieldRenderer
				key={field.id}
				field={field}
				value={values[field.name]}
				error={errors[field.name]?.[0]}
				onChange={( value ) => setValue( field.name, value )}
				onFileChange={( file ) => setFile( field.name, file )}
				displayConfig={form.config.display}
				otherValue={otherValues[field.name] ?? ''}
				onOtherChange={( text ) => setOtherValue( field.name, text )}
				subErrors={getSubInputErrors( errors, field.name )}
				confirmValue={confirms ? String( values[getConfirmationKey( field )] ?? '' ) : undefined}
				onConfirmChange={confirms ? ( text ) => setValue( getConfirmationKey( field ), text ) : undefined}
				fieldComponents={fieldComponents}
				layout={isInline ? 'inline' : 'stacked'}
			/>
		);
	};

	// A multi-step form's invisible CAPTCHAs on other steps (since 1.6.0)
	const offStepCaptchas = form.is_multi_step && currentStepData
		? getOffStepSubmitCaptchas( form.fields, currentStepData.id, hiddenFields )
		: [];

	// Whether a field, or an input nested under it, has an error
	const hasFieldErrors = ( field: FormField ) => Object.keys( errors ).some( ( key ) => key === field.name || key.startsWith( `${field.name}.` ) );

	// Render form fields, each section's fields grouped in its fieldset (since 1.6.0);
	// they reach the form's submit hooks and values through the contexts
	const renderedFields = (
		<SubmitHooksContext.Provider value={submitHooks}>
			<FormValuesContext.Provider value={{ fields: form.fields, values, hiddenFields, otherValues }}>
				<div className="flex flex-wrap gap-4">
					{getFieldSegments( currentFields ).map( ( segment, index ) => ( segment.section ? (
						<SectionGroup
							key={segment.section.id}
							section={segment.section}
							hidden={hiddenFields[segment.section.name]}
							holdsCaptcha={segment.fields.some( ( field ) => field.type === 'captcha' )}
							hasErrors={segment.fields.some( hasFieldErrors )}
						>
							{segment.fields.map( renderField )}
						</SectionGroup>
					) : (
						<Fragment key={`fields-${index}`}>{segment.fields.map( renderField )}</Fragment>
					) ) )}

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

					{/* Inline layout: the submit button is the last item in the field row */}
					{isInline && submitButton}
				</div>

				{/* Invisible CAPTCHAs on other steps stay mounted, so their submit hooks fetch a token */}
				{offStepCaptchas.length > 0 && (
					<div className="sr-only">
						{offStepCaptchas.map( renderField )}
					</div>
				)}
			</FormValuesContext.Provider>
		</SubmitHooksContext.Provider>
	);

	// Form-level errors
	const formErrors = errors._form;

	// Enter in a field submits the form; before the last step it moves on instead, as in Blade
	const handleFormSubmit = ( e: React.FormEvent ) => {
		e.preventDefault();

		if ( form.is_multi_step && currentStep < totalSteps - 1 ) {
			nextStep();

			return;
		}

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
					{!isInline && (
						<div>
							{submitButton}
						</div>
					)}
				</div>
			)}
		</form>
	);
}
