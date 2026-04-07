/**
 * useForm hook for React form state management.
 *
 * Fetches a form definition from the API, manages field values,
 * validation errors, file uploads, conditional logic, multi-step
 * navigation, and form submission.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import type {
	FormField,
	FormRenderData,
	FormStep,
	SubmitFormResponse,
} from '../../types/artisanpack-forms';
import { getHiddenFields } from '../utils/conditionalLogic';
import { validateFields } from '../utils/validation';

/** Configuration options for the useForm hook. */
export interface UseFormOptions {
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
}

/** Return type of the useForm hook. */
export interface UseFormReturn {
	/** The form definition, null while loading. */
	form: FormRenderData | null;
	/** Current form field values keyed by field name. */
	values: Record<string, unknown>;
	/** Validation errors keyed by field name. */
	errors: Record<string, string[]>;
	/** File uploads keyed by field name. */
	files: Record<string, File | File[]>;
	/** Map of field names to hidden state (true = hidden by conditional logic). */
	hiddenFields: Record<string, boolean>;
	/** Whether the form definition is loading. */
	isLoading: boolean;
	/** Whether a submission is in progress. */
	isSubmitting: boolean;
	/** Whether the form was submitted successfully. */
	isSubmitted: boolean;
	/** The current step index (0-based) for multi-step forms. */
	currentStep: number;
	/** Total number of steps (1 for single-step forms). */
	totalSteps: number;
	/** Progress percentage (0-100) for multi-step forms. */
	progressPercentage: number;
	/** Fields visible in the current step (or all fields for single-step). */
	currentFields: FormField[];
	/** The current step definition, or null for single-step forms. */
	currentStepData: FormStep | null;
	/** Error from loading the form definition, if any. */
	loadError: string | null;
	/** Sets a single field value. */
	setValue: ( name: string, value: unknown ) => void;
	/** Sets a file for a field. */
	setFile: ( name: string, file: File | File[] ) => void;
	/** Removes a file for a field. */
	removeFile: ( name: string ) => void;
	/** Navigates to the next step with validation. Returns true if successful. */
	nextStep: () => boolean;
	/** Navigates to the previous step. */
	prevStep: () => void;
	/** Navigates to a specific step (0-based). */
	goToStep: ( step: number ) => void;
	/** Submits the form. */
	submit: () => Promise<void>;
	/** Resets the form to its initial state. */
	reset: () => void;
}

/** Layout field types that do not collect data. */
const LAYOUT_FIELDS = new Set( ['heading', 'paragraph', 'divider', 'html'] );

/**
 * React hook for managing ArtisanPack form state.
 *
 * @example
 * ```tsx
 * const {
 *   form, values, errors, currentFields,
 *   setValue, submit, isSubmitting,
 *   currentStep, nextStep, prevStep,
 * } = useForm({
 *   baseUrl: '/api/v1/forms',
 *   formSlug: 'contact-us',
 * });
 * ```
 */
export function useForm( options: UseFormOptions ): UseFormReturn {
	const {
		baseUrl,
		formSlug,
		onSuccess,
		onError,
		clientValidation = true,
	} = options;

	const [form, setForm] = useState<FormRenderData | null>( null );
	const [values, setValues] = useState<Record<string, unknown>>( {} );
	const [errors, setErrors] = useState<Record<string, string[]>>( {} );
	const [files, setFiles] = useState<Record<string, File | File[]>>( {} );
	const [isLoading, setIsLoading] = useState( true );
	const [isSubmitting, setIsSubmitting] = useState( false );
	const [isSubmitted, setIsSubmitted] = useState( false );
	const [currentStep, setCurrentStep] = useState( 0 );
	const [loadError, setLoadError] = useState<string | null>( null );

	const formLoadedAt = useRef<number>( 0 );

	// Fetch the form definition
	useEffect( () => {
		let cancelled = false;

		async function fetchForm(): Promise<void> {
			setIsLoading( true );
			setLoadError( null );

			try {
				const url = `${baseUrl}/${encodeURIComponent( formSlug )}/render`;
				const response = await fetch( url );

				if ( !response.ok ) {
					throw new Error( `Failed to load form: ${response.statusText}` );
				}

				const data: { data: FormRenderData } = await response.json();

				if ( cancelled ) {
					return;
				}

				setForm( data.data );
				formLoadedAt.current = Math.floor( Date.now() / 1000 );

				// Initialize default values
				const defaults: Record<string, unknown> = {};

				for ( const field of data.data.fields ) {
					if ( LAYOUT_FIELDS.has( field.type ) ) {
						continue;
					}

					if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
						defaults[field.name] = typeof field.default_value === 'string' && field.default_value
							? field.default_value.split( ',' ).map( ( v ) => v.trim() )
							: [];
					} else if ( field.type === 'checkbox' ) {
						defaults[field.name] = field.default_value === '1' || field.default_value === 'true';
					} else {
						defaults[field.name] = field.default_value ?? '';
					}
				}

				setValues( defaults );
			} catch ( err ) {
				if ( !cancelled ) {
					const message = err instanceof Error ? err.message : 'Failed to load form.';
					setLoadError( message );
				}
			} finally {
				if ( !cancelled ) {
					setIsLoading( false );
				}
			}
		}

		fetchForm();

		return () => {
			cancelled = true;
		};
	}, [baseUrl, formSlug] );

	// Compute hidden fields from conditional logic
	const hiddenFields = useMemo( () => {
		if ( !form ) {
			return {};
		}

		return getHiddenFields( form.fields, values );
	}, [form, values] );

	// Compute all fields for the entire form (with steps resolved)
	const allFields = useMemo( () => {
		if ( !form ) {
			return [];
		}

		return form.fields;
	}, [form] );

	// Sorted steps
	const sortedSteps = useMemo( () => {
		if ( !form || !form.is_multi_step ) {
			return [];
		}

		return [...form.steps].sort( ( a, b ) => a.sort_order - b.sort_order );
	}, [form] );

	const totalSteps = form?.is_multi_step ? sortedSteps.length : 1;

	const currentStepData = useMemo( () => {
		if ( !form?.is_multi_step || sortedSteps.length === 0 ) {
			return null;
		}

		return sortedSteps[currentStep] ?? null;
	}, [form, sortedSteps, currentStep] );

	// Get fields for the current step (or all fields for single-step)
	const currentFields = useMemo( () => {
		if ( !form ) {
			return [];
		}

		if ( !form.is_multi_step ) {
			return allFields;
		}

		if ( !currentStepData ) {
			return [];
		}

		return allFields
			.filter( ( field ) => field.step_id === currentStepData.id )
			.sort( ( a, b ) => a.sort_order - b.sort_order );
	}, [form, allFields, currentStepData] );

	const progressPercentage = useMemo( () => {
		if ( totalSteps <= 1 ) {
			return 100;
		}

		return Math.round( ( ( currentStep + 1 ) / totalSteps ) * 100 );
	}, [currentStep, totalSteps] );

	const setValue = useCallback( ( name: string, value: unknown ) => {
		setValues( ( prev ) => ( { ...prev, [name]: value } ) );
		setErrors( ( prev ) => {
			const next = { ...prev };
			delete next[name];

			return next;
		} );
	}, [] );

	const setFile = useCallback( ( name: string, file: File | File[] ) => {
		setFiles( ( prev ) => ( { ...prev, [name]: file } ) );
		setErrors( ( prev ) => {
			const next = { ...prev };
			delete next[name];

			return next;
		} );
	}, [] );

	const removeFile = useCallback( ( name: string ) => {
		setFiles( ( prev ) => {
			const next = { ...prev };
			delete next[name];

			return next;
		} );
	}, [] );

	/**
	 * Validates fields for the current step (or all fields).
	 */
	const validateCurrentFields = useCallback(
		( fieldsToValidate: FormField[] ): Record<string, string[]> => {
			if ( !clientValidation ) {
				return {};
			}

			return validateFields( fieldsToValidate, values, hiddenFields, files );
		},
		[values, hiddenFields, files, clientValidation],
	);

	const nextStep = useCallback( (): boolean => {
		if ( !form?.is_multi_step ) {
			return false;
		}

		const stepErrors = validateCurrentFields( currentFields );

		if ( Object.keys( stepErrors ).length > 0 ) {
			setErrors( ( prev ) => ( { ...prev, ...stepErrors } ) );

			return false;
		}

		if ( currentStep < totalSteps - 1 ) {
			setCurrentStep( ( prev ) => prev + 1 );

			return true;
		}

		return false;
	}, [form, currentFields, currentStep, totalSteps, validateCurrentFields] );

	const prevStep = useCallback( () => {
		if ( currentStep > 0 ) {
			setCurrentStep( ( prev ) => prev - 1 );
		}
	}, [currentStep] );

	const goToStep = useCallback(
		( step: number ) => {
			if ( step < 0 || step >= totalSteps ) {
				return;
			}

			// Allow backward navigation without validation
			if ( step <= currentStep ) {
				setCurrentStep( step );

				return;
			}

			// For forward navigation, validate the current step first
			const stepErrors = validateCurrentFields( currentFields );

			if ( Object.keys( stepErrors ).length > 0 ) {
				setErrors( ( prev ) => ( { ...prev, ...stepErrors } ) );

				return;
			}

			setCurrentStep( step );
		},
		[totalSteps, currentStep, currentFields, validateCurrentFields],
	);

	const submit = useCallback( async () => {
		if ( !form || isSubmitting ) {
			return;
		}

		// Validate all visible fields
		const allVisibleFields = allFields.filter(
			( field ) => !hiddenFields[field.name],
		);
		const validationErrors = validateCurrentFields( allVisibleFields );

		if ( Object.keys( validationErrors ).length > 0 ) {
			setErrors( validationErrors );

			// If multi-step, navigate to the first step with errors
			if ( form.is_multi_step && sortedSteps.length > 0 ) {
				const errorFieldNames = new Set( Object.keys( validationErrors ) );

				for ( let i = 0; i < sortedSteps.length; i++ ) {
					const stepFields = allFields.filter(
						( f ) => f.step_id === sortedSteps[i].id,
					);

					if ( stepFields.some( ( f ) => errorFieldNames.has( f.name ) ) ) {
						setCurrentStep( i );
						break;
					}
				}
			}

			return;
		}

		setIsSubmitting( true );
		setErrors( {} );

		try {
			const url = `${baseUrl}/${encodeURIComponent( formSlug )}/submit`;
			const formDataObj = new FormData();

			// Add form data values
			for ( const [key, value] of Object.entries( values ) ) {
				if ( hiddenFields[key] ) {
					continue;
				}

				if ( Array.isArray( value ) ) {
					for ( const item of value ) {
						formDataObj.append( `data[${key}][]`, String( item ) );
					}
				} else if ( value !== null && value !== undefined ) {
					formDataObj.append( `data[${key}]`, String( value ) );
				}
			}

			// Add files
			for ( const [key, file] of Object.entries( files ) ) {
				if ( hiddenFields[key] ) {
					continue;
				}

				if ( Array.isArray( file ) ) {
					for ( const f of file ) {
						formDataObj.append( `files[${key}][]`, f );
					}
				} else {
					formDataObj.append( `files[${key}]`, file );
				}
			}

			// Add honeypot field (empty value)
			if ( form.config.honeypot.enabled ) {
				formDataObj.append( form.config.honeypot.field_name, '' );
			}

			// Add form loaded timestamp for bot detection
			formDataObj.append( '_form_loaded_at', String( formLoadedAt.current ) );

			const response = await fetch( url, {
				method: 'POST',
				body: formDataObj,
				headers: {
					Accept: 'application/json',
				},
			} );

			if ( response.status === 422 ) {
				const errorData = await response.json();
				const serverErrors: Record<string, string[]> = {};

				if ( errorData.errors ) {
					for ( const [key, messages] of Object.entries( errorData.errors ) ) {
						// Server errors come as "data.field_name", strip the prefix
						const fieldName = key.replace( /^(data|files)\./, '' );
						serverErrors[fieldName] = messages as string[];
					}
				}

				setErrors( serverErrors );

				return;
			}

			if ( response.status === 429 ) {
				setErrors( {
					_form: ['Too many submissions. Please try again later.'],
				} );

				return;
			}

			if ( !response.ok ) {
				throw new Error( 'Form submission failed.' );
			}

			const result: SubmitFormResponse = await response.json();
			setIsSubmitted( true );

			if ( result.redirect_url ) {
				window.location.href = result.redirect_url;

				return;
			}

			onSuccess?.( result );
		} catch ( err ) {
			const error =
				err instanceof Error ? err : new Error( 'Form submission failed.' );

			onError?.( error );
		} finally {
			setIsSubmitting( false );
		}
	}, [
		form,
		isSubmitting,
		allFields,
		hiddenFields,
		validateCurrentFields,
		sortedSteps,
		baseUrl,
		formSlug,
		values,
		files,
		onSuccess,
		onError,
	] );

	const reset = useCallback( () => {
		if ( !form ) {
			return;
		}

		const defaults: Record<string, unknown> = {};

		for ( const field of form.fields ) {
			if ( LAYOUT_FIELDS.has( field.type ) ) {
				continue;
			}

			if ( field.type === 'checkbox_group' || field.type === 'select_multiple' ) {
				defaults[field.name] = field.default_value
					? field.default_value.split( ',' ).map( ( v ) => v.trim() )
					: [];
			} else if ( field.type === 'checkbox' ) {
				defaults[field.name] = field.default_value === '1' || field.default_value === 'true';
			} else {
				defaults[field.name] = field.default_value ?? '';
			}
		}

		setValues( defaults );
		setErrors( {} );
		setFiles( {} );
		setIsSubmitted( false );
		setCurrentStep( 0 );
		formLoadedAt.current = Math.floor( Date.now() / 1000 );
	}, [form] );

	return {
		form,
		values,
		errors,
		files,
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
		removeFile,
		nextStep,
		prevStep,
		goToStep,
		submit,
		reset,
	};
}
