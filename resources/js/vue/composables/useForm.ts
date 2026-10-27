/**
 * useForm composable for Vue form state management.
 *
 * Fetches a form definition from the API, manages field values,
 * validation errors, file uploads, conditional logic, multi-step
 * navigation, and form submission. Calculation fields are worked out from
 * the other values as they change (since 1.6.0; see `shared/calculation.ts`).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { computed, onMounted, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

import type {
	FormField,
	FormRenderData,
	FormStep,
	SubmitFormResponse,
} from '../../types/artisanpack-forms';
import { isCalculationField, resolveCalculatedValues } from '../../shared/calculation';
import { createSubmitHooks, SubmitHookError } from '../../shared/captcha';
import type { SubmitHooks } from '../../shared/captcha';
import { clearChangedFieldErrors, getErrorFieldName } from '../../shared/composite';
import { createIdPrefix, withIdPrefix } from '../../shared/domIds';
import { getInitialFieldValue, resolveOtherChoices } from '../../shared/fieldSettings';
import { getConfirmationKey, requiresEmailConfirmation } from '../../shared/fieldSettings';
import { t } from '../../shared/i18n';
import { appendSubmissionValue, mapServerErrors } from '../../shared/submission';
import { validateFields } from '../../shared/validation';

/** Configuration options for the useForm composable. */
export interface UseFormOptions {
	/**
	 * Base URL for the forms API (e.g. "https://example.com/api/v1/forms").
	 * A ref or getter is watched, and the form reloads when it changes
	 * (since 1.6.0).
	 */
	baseUrl: MaybeRefOrGetter<string>;
	/**
	 * The form slug or ID used in the API URL. A ref or getter is watched,
	 * and the form reloads when it changes (since 1.6.0).
	 */
	formSlug: MaybeRefOrGetter<string>;
	/** Optional callback when submission succeeds. */
	onSuccess?: ( response: SubmitFormResponse ) => void;
	/** Optional callback when submission fails. */
	onError?: ( error: Error ) => void;
	/** Whether to validate on the client before submitting. Defaults to true. */
	clientValidation?: boolean;
	/**
	 * Values for fields with dynamic population on, keyed by parameter name.
	 * Takes precedence over the page's query string and the field's default.
	 *
	 * @since 1.6.0
	 */
	prefill?: Record<string, unknown>;
}

/** Layout field types that do not collect data. */
const LAYOUT_FIELDS = new Set( ['section', 'heading', 'paragraph', 'divider', 'html'] );

/**
 * Initializes default form values from field definitions.
 *
 * An email field that asks for the address twice seeds its confirmation
 * with the email's initial value, so a prefilled address is not reported
 * as a mismatch. Mirrors the Livewire renderer's `buildInitialFormData()`
 * (since 1.6.0).
 */
function buildDefaults( fields: FormField[], prefill?: Record<string, unknown> ): Record<string, unknown> {
	const defaults: Record<string, unknown> = {};

	for ( const field of fields ) {
		if ( LAYOUT_FIELDS.has( field.type ) ) {
			continue;
		}

		defaults[field.name] = getInitialFieldValue( field, { prefill } );

		if ( requiresEmailConfirmation( field ) ) {
			const email = defaults[field.name];

			defaults[getConfirmationKey( field )] = typeof email === 'string' ? email : '';
		}
	}

	return defaults;
}

/**
 * Map each email confirmation key to the name of the email field it
 * confirms (since 1.6.0).
 */
function getConfirmationFields( fields: FormField[] ): Record<string, string> {
	return Object.fromEntries(
		fields.filter( requiresEmailConfirmation ).map( ( field ) => [getConfirmationKey( field ), field.name] ),
	);
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
		prefill,
	} = options;

	const form = ref<FormRenderData | null>( null );
	const values = ref<Record<string, unknown>>( {} );
	const errors = ref<Record<string, string[]>>( {} );
	const files = ref<Record<string, File | File[]>>( {} );

	/**
	 * Text typed into each field's "Other" choice, keyed by field name. The
	 * choice stays selected in `values` and is replaced with this text on
	 * submit (since 1.6.0).
	 */
	const otherValues = ref<Record<string, string>>( {} );
	const isLoading = ref( true );
	const isSubmitting = ref( false );
	const isSubmitted = ref( false );
	const currentStep = ref( 0 );
	const loadError = ref<string | null>( null );

	/**
	 * Counts the times validation failed (a step, or the submission on the
	 * client or the server), so the renderer can move focus to the first
	 * invalid field each time (since 1.6.0).
	 */
	const validationFailures = ref( 0 );
	const formLoadedAt = ref( 0 );

	/**
	 * The prefix this form instance's element IDs start with, so two forms on
	 * one page do not share IDs. Each field carries it as `id_prefix` (since
	 * 1.6.0; see `fieldIdKey()`).
	 */
	const idPrefix = createIdPrefix();

	/** The form's submit hooks, which fields such as the CAPTCHA register with (since 1.6.0). */
	const submitHooks: SubmitHooks = createSubmitHooks();

	/** Each email confirmation key, mapped to its email field's name (since 1.6.0). */
	const confirmationFields = computed( () => getConfirmationFields( form.value?.fields ?? [] ) );

	// Computed: calculations and hidden fields from conditional logic (since 1.6.0)
	const resolved = computed( () => {
		if ( !form.value ) {
			return { values: values.value, hiddenFields: {} as Record<string, boolean> };
		}

		return resolveCalculatedValues( form.value.fields, values.value );
	} );

	const hiddenFields = computed( () => resolved.value.hiddenFields );

	// Keep each calculation's result in `values`, so it renders and submits like any other value
	watch( () => resolved.value.values, ( next ) => {
		const changed = ( form.value?.fields ?? [] )
			.filter( ( field ) => isCalculationField( field ) && values.value[field.name] !== next[field.name] );

		if ( changed.length > 0 ) {
			values.value = { ...values.value, ...Object.fromEntries( changed.map( ( field ) => [field.name, next[field.name]] ) ) };
		}
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

	/** Counts form loads, so a response for a slug since replaced is ignored. */
	let latestLoad = 0;

	// Fetch form definition
	async function fetchForm(): Promise<void> {
		const load = ++latestLoad;

		isLoading.value = true;
		loadError.value = null;

		try {
			const url = `${toValue( baseUrl )}/${encodeURIComponent( toValue( formSlug ) )}/render`;
			const response = await fetch( url );

			if ( !response.ok ) {
				throw new Error( t( 'Failed to load form: :status', { status: response.statusText } ) );
			}

			const data: { data: FormRenderData } = await response.json();

			if ( load !== latestLoad ) {
				return;
			}

			// Each field carries the instance's ID prefix for its element IDs (since 1.6.0)
			form.value = { ...data.data, fields: withIdPrefix( data.data.fields, idPrefix ) };
			formLoadedAt.value = Math.floor( Date.now() / 1000 );
			values.value = buildDefaults( data.data.fields, prefill );
			errors.value = {};
			files.value = {};
			otherValues.value = {};
			isSubmitted.value = false;
			currentStep.value = 0;
		} catch ( err ) {
			if ( load === latestLoad ) {
				loadError.value = err instanceof Error ? err.message : t( 'Failed to load form.' );
			}
		} finally {
			if ( load === latestLoad ) {
				isLoading.value = false;
			}
		}
	}

	onMounted( fetchForm );

	// Load the new form when the slug or API changes, as the React hook does (since 1.6.0)
	watch( [() => toValue( baseUrl ), () => toValue( formSlug )], () => {
		fetchForm();
	} );

	function setValue( name: string, value: unknown ): void {
		const previous = values.value[name];

		values.value = { ...values.value, [name]: value };

		// A composite field's errors are keyed by sub-input; only the changed parts are cleared.
		if ( Object.keys( errors.value ).some( ( key ) => key === name || key.startsWith( `${name}.` ) ) ) {
			errors.value = clearChangedFieldErrors( errors.value, name, previous, value );
		}

		// A mismatched confirmation is its email field's error.
		const confirmedField = confirmationFields.value[name];

		if ( confirmedField && errors.value[confirmedField] ) {
			const next = { ...errors.value };
			delete next[confirmedField];
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

	function setOtherValue( name: string, text: string ): void {
		otherValues.value = { ...otherValues.value, [name]: text };

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

		return validateFields(
			fieldsToValidate,
			resolveOtherChoices( fieldsToValidate, values.value, otherValues.value ),
			hiddenFields.value,
			files.value,
		);
	}

	function nextStepFn(): boolean {
		if ( !form.value?.is_multi_step ) {
			return false;
		}

		const stepErrors = validateCurrentFieldSet( currentFields.value );

		if ( Object.keys( stepErrors ).length > 0 ) {
			errors.value = { ...errors.value, ...stepErrors };
			validationFailures.value++;

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
			validationFailures.value++;

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
			validationFailures.value++;

			// Navigate to first step with errors (multi-step)
			if ( form.value.is_multi_step && sortedSteps.value.length > 0 ) {
				const errorFieldNames = new Set( Object.keys( validationErrors ).map( getErrorFieldName ) );

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

		/** How the request went, told to every submit hook once it was sent. */
		let outcome: { ok: boolean; errors: Record<string, string[]> } | null = null;

		try {
			// Values fields add when the form is submitted, such as an invisible CAPTCHA's token.
			let collected: Record<string, unknown>;

			try {
				collected = await submitHooks.collect();
			} catch ( hookError ) {
				if ( hookError instanceof SubmitHookError ) {
					errors.value = { [hookError.field]: [t( 'The verification check failed. Please try again.' )] };
					validationFailures.value++;

					return;
				}

				throw hookError;
			}

			const url = `${toValue( baseUrl )}/${encodeURIComponent( toValue( formSlug ) )}/submit`;
			const formDataObj = new FormData();
			const submitted = {
				...resolveOtherChoices( form.value.fields, values.value, otherValues.value ),
				...collected,
			};

			// Add form data values, with each "Other" choice replaced by its text
			for ( const [key, value] of Object.entries( submitted ) ) {
				if ( hiddenFields.value[key] ) {
					continue;
				}

				// An email confirmation is left out with its hidden email field.
				const confirmedField = confirmationFields.value[key];

				if ( confirmedField && hiddenFields.value[confirmedField] ) {
					continue;
				}

				// Lists, arrays and composite maps are expanded; booleans are sent as '1'/'0'.
				appendSubmissionValue( formDataObj, key, value );
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

			outcome = { ok: false, errors: {} };

			const response = await fetch( url, {
				method: 'POST',
				body: formDataObj,
				headers: {
					Accept: 'application/json',
				},
			} );

			if ( response.status === 422 ) {
				const errorData = await response.json();
				// "data.field_name" becomes "field_name"; errors for no field on the form go to `_form`.
				const serverErrors = mapServerErrors( errorData.errors, allFields.value );

				errors.value = serverErrors;
				validationFailures.value++;
				outcome = { ok: false, errors: serverErrors };

				// Navigate to first step with server errors (multi-step)
				if ( form.value.is_multi_step && sortedSteps.value.length > 0 ) {
					const errorFieldNames = new Set( Object.keys( serverErrors ).map( getErrorFieldName ) );

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
					_form: [t( 'Too many submissions. Please try again later.' )],
				};
				validationFailures.value++;

				return;
			}

			if ( !response.ok ) {
				throw new Error( t( 'Form submission failed.' ) );
			}

			const result: SubmitFormResponse = await response.json();
			isSubmitted.value = true;
			outcome = { ok: true, errors: {} };

			if ( result.redirect_url ) {
				window.location.href = result.redirect_url;

				return;
			}

			onSuccess?.( result );
		} catch ( err ) {
			const error = err instanceof Error ? err : new Error( t( 'Form submission failed.' ) );
			onError?.( error );
		} finally {
			isSubmitting.value = false;

			// Fields learn how the submission went, e.g. a spent CAPTCHA token is reset.
			if ( outcome ) {
				submitHooks.settle( outcome );
			}
		}
	}

	function reset(): void {
		if ( !form.value ) {
			return;
		}

		values.value = buildDefaults( form.value.fields, prefill );
		errors.value = {};
		files.value = {};
		otherValues.value = {};
		isSubmitted.value = false;
		currentStep.value = 0;
		formLoadedAt.value = Math.floor( Date.now() / 1000 );
	}

	return {
		form,
		values,
		errors,
		files,
		otherValues,
		hiddenFields,
		isLoading,
		isSubmitting,
		isSubmitted,
		currentStep,
		totalSteps,
		/** A multi-step form's steps in order; empty for single-step forms (since 1.6.0). */
		steps: sortedSteps,
		validationFailures,
		progressPercentage,
		currentFields,
		currentStepData,
		loadError,
		idPrefix,
		setValue,
		setFile,
		setOtherValue,
		removeFile,
		/**
		 * The form's submit hook registry. `FormRenderer` provides it to its
		 * fields under `SUBMIT_HOOKS_KEY`; a field registers a hook to add
		 * values when the form is submitted (`beforeSubmit`) and to hear how
		 * the submission went (`afterSubmit`).
		 *
		 * @since 1.6.0
		 */
		submitHooks,
		nextStep: nextStepFn,
		prevStep: prevStepFn,
		goToStep: goToStepFn,
		submit,
		reset,
	};
}
