/**
 * useForm composable for Vue form state management.
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

import { computed, onMounted, ref, watch } from 'vue';

import type {
	FormField,
	FormRenderData,
	FormStep,
	SubmitFormResponse,
} from '../../types/artisanpack-forms';
import { getHiddenFields } from '../../shared/conditionalLogic';
import { validateFields } from '../../shared/validation';

/** Configuration options for the useForm composable. */
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

/** Layout field types that do not collect data. */
const LAYOUT_FIELDS = new Set( ['heading', 'paragraph', 'divider', 'html'] );

/**
 * Initializes default form values from field definitions.
 */
function buildDefaults( fields: FormField[] ): Record<string, unknown> {
	const defaults: Record<string, unknown> = {};

	for ( const field of fields ) {
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

	return defaults;
}

/**
 * Vue composable for managing ArtisanPack form state.
 *
 * @example
 * ```ts
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
export function useForm( options: UseFormOptions ) {
	const {
		baseUrl,
		formSlug,
		onSuccess,
		onError,
		clientValidation = true,
	} = options;

	const form = ref<FormRenderData | null>( null );
	const values = ref<Record<string, unknown>>( {} );
	const errors = ref<Record<string, string[]>>( {} );
	const files = ref<Record<string, File | File[]>>( {} );
	const isLoading = ref( true );
	const isSubmitting = ref( false );
	const isSubmitted = ref( false );
	const currentStep = ref( 0 );
	const loadError = ref<string | null>( null );
	const formLoadedAt = ref( 0 );

	// Computed: hidden fields from conditional logic
	const hiddenFields = computed( () => {
		if ( !form.value ) {
			return {};
		}

		return getHiddenFields( form.value.fields, values.value );
	} );

	// Computed: all fields
	const allFields = computed( () => form.value?.fields ?? [] );

	// Computed: sorted steps
	const sortedSteps = computed( () => {
		if ( !form.value?.is_multi_step ) {
			return [];
		}

		return [...form.value.steps].sort( ( a, b ) => a.sort_order - b.sort_order );
	} );

	const totalSteps = computed( () =>
		form.value?.is_multi_step ? sortedSteps.value.length : 1,
	);

	const currentStepData = computed<FormStep | null>( () => {
		if ( !form.value?.is_multi_step || sortedSteps.value.length === 0 ) {
			return null;
		}

		return sortedSteps.value[currentStep.value] ?? null;
	} );

	// Computed: fields for current step
	const currentFields = computed( () => {
		if ( !form.value ) {
			return [];
		}

		if ( !form.value.is_multi_step ) {
			return allFields.value;
		}

		if ( !currentStepData.value ) {
			return [];
		}

		return allFields.value
			.filter( ( field ) => field.step_id === currentStepData.value!.id )
			.sort( ( a, b ) => a.sort_order - b.sort_order );
	} );

	const progressPercentage = computed( () => {
		if ( totalSteps.value <= 1 ) {
			return 100;
		}

		return Math.round( ( ( currentStep.value + 1 ) / totalSteps.value ) * 100 );
	} );

	// Fetch form definition
	async function fetchForm(): Promise<void> {
		isLoading.value = true;
		loadError.value = null;

		try {
			const url = `${baseUrl}/${encodeURIComponent( formSlug )}/render`;
			const response = await fetch( url );

			if ( !response.ok ) {
				throw new Error( `Failed to load form: ${response.statusText}` );
			}

			const data: { data: FormRenderData } = await response.json();
			form.value = data.data;
			formLoadedAt.value = Math.floor( Date.now() / 1000 );
			values.value = buildDefaults( data.data.fields );
		} catch ( err ) {
			loadError.value = err instanceof Error ? err.message : 'Failed to load form.';
		} finally {
			isLoading.value = false;
		}
	}

	onMounted( fetchForm );

	function setValue( name: string, value: unknown ): void {
		values.value = { ...values.value, [name]: value };

		if ( errors.value[name] ) {
			const next = { ...errors.value };
			delete next[name];
			errors.value = next;
		}
	}

	function setFile( name: string, file: File | File[] ): void {
		files.value = { ...files.value, [name]: file };

		if ( errors.value[name] ) {
			const next = { ...errors.value };
			delete next[name];
			errors.value = next;
		}
	}

	function removeFile( name: string ): void {
		const next = { ...files.value };
		delete next[name];
		files.value = next;
	}

	function validateCurrentFieldSet( fieldsToValidate: FormField[] ): Record<string, string[]> {
		if ( !clientValidation ) {
			return {};
		}

		return validateFields( fieldsToValidate, values.value, hiddenFields.value, files.value );
	}

	function nextStepFn(): boolean {
		if ( !form.value?.is_multi_step ) {
			return false;
		}

		const stepErrors = validateCurrentFieldSet( currentFields.value );

		if ( Object.keys( stepErrors ).length > 0 ) {
			errors.value = { ...errors.value, ...stepErrors };

			return false;
		}

		if ( currentStep.value < totalSteps.value - 1 ) {
			currentStep.value++;

			return true;
		}

		return false;
	}

	function prevStepFn(): void {
		if ( currentStep.value > 0 ) {
			currentStep.value--;
		}
	}

	function goToStepFn( step: number ): void {
		if ( step < 0 || step >= totalSteps.value ) {
			return;
		}

		// Allow backward navigation without validation
		if ( step <= currentStep.value ) {
			currentStep.value = step;

			return;
		}

		// For forward navigation, validate the current step first
		const stepErrors = validateCurrentFieldSet( currentFields.value );

		if ( Object.keys( stepErrors ).length > 0 ) {
			errors.value = { ...errors.value, ...stepErrors };

			return;
		}

		currentStep.value = step;
	}

	async function submit(): Promise<void> {
		if ( !form.value || isSubmitting.value ) {
			return;
		}

		// Validate all visible fields
		const allVisibleFields = allFields.value.filter(
			( field ) => !hiddenFields.value[field.name],
		);
		const validationErrors = validateCurrentFieldSet( allVisibleFields );

		if ( Object.keys( validationErrors ).length > 0 ) {
			errors.value = validationErrors;

			// Navigate to first step with errors (multi-step)
			if ( form.value.is_multi_step && sortedSteps.value.length > 0 ) {
				const errorFieldNames = new Set( Object.keys( validationErrors ) );

				for ( let i = 0; i < sortedSteps.value.length; i++ ) {
					const stepFields = allFields.value.filter(
						( f ) => f.step_id === sortedSteps.value[i].id,
					);

					if ( stepFields.some( ( f ) => errorFieldNames.has( f.name ) ) ) {
						currentStep.value = i;
						break;
					}
				}
			}

			return;
		}

		isSubmitting.value = true;
		errors.value = {};

		try {
			const url = `${baseUrl}/${encodeURIComponent( formSlug )}/submit`;
			const formDataObj = new FormData();

			// Add form data values
			for ( const [key, value] of Object.entries( values.value ) ) {
				if ( hiddenFields.value[key] ) {
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
			for ( const [key, file] of Object.entries( files.value ) ) {
				if ( hiddenFields.value[key] ) {
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
			if ( form.value.config.honeypot.enabled ) {
				formDataObj.append( form.value.config.honeypot.field_name, '' );
			}

			// Add form loaded timestamp for bot detection
			formDataObj.append( '_form_loaded_at', String( formLoadedAt.value ) );

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
						const fieldName = key.replace( /^(data|files)\./, '' );
						serverErrors[fieldName] = messages as string[];
					}
				}

				errors.value = serverErrors;

				// Navigate to first step with server errors (multi-step)
				if ( form.value.is_multi_step && sortedSteps.value.length > 0 ) {
					const errorFieldNames = new Set( Object.keys( serverErrors ) );

					for ( let i = 0; i < sortedSteps.value.length; i++ ) {
						const stepFields = allFields.value.filter(
							( f ) => f.step_id === sortedSteps.value[i].id,
						);

						if ( stepFields.some( ( f ) => errorFieldNames.has( f.name ) ) ) {
							currentStep.value = i;
							break;
						}
					}
				}

				return;
			}

			if ( response.status === 429 ) {
				errors.value = {
					_form: ['Too many submissions. Please try again later.'],
				};

				return;
			}

			if ( !response.ok ) {
				throw new Error( 'Form submission failed.' );
			}

			const result: SubmitFormResponse = await response.json();
			isSubmitted.value = true;

			if ( result.redirect_url ) {
				window.location.href = result.redirect_url;

				return;
			}

			onSuccess?.( result );
		} catch ( err ) {
			const error = err instanceof Error ? err : new Error( 'Form submission failed.' );
			onError?.( error );
		} finally {
			isSubmitting.value = false;
		}
	}

	function reset(): void {
		if ( !form.value ) {
			return;
		}

		values.value = buildDefaults( form.value.fields );
		errors.value = {};
		files.value = {};
		isSubmitted.value = false;
		currentStep.value = 0;
		formLoadedAt.value = Math.floor( Date.now() / 1000 );
	}

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
		nextStep: nextStepFn,
		prevStep: prevStepFn,
		goToStep: goToStepFn,
		submit,
		reset,
	};
}
