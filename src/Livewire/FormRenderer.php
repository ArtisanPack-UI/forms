<?php

/**
 * Form renderer Livewire component.
 *
 * Public-facing form renderer that displays forms, handles validation,
 * processes submissions, and provides user feedback. Includes spam
 * protection, file uploads, and multi-step navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Captcha\CaptchaVerifier;
use ArtisanPackUI\Forms\FieldTypes\CalculationFieldType;
use ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType;
use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\FieldTypes\RankingFieldType;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\PopulationContext;
use ArtisanPackUI\Forms\Support\Sections;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form renderer Livewire component class.
 *
 * Public-facing form renderer that displays forms, handles validation,
 * processes submissions, and provides user feedback. Includes spam
 * protection, file uploads, and multi-step navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormRenderer extends Component
{
    use WithFileUploads;

    /**
     * The form being rendered.
     */
    public Form $form;

    /**
     * The form data being collected.
     *
     * @var array<string, mixed>
     */
    public array $formData = [];

    /**
     * The values the form starts with, kept so resetForm() restores them.
     *
     * Worked out once on mount, from the page request's query string and the
     * `prefill` passed to the component (see DynamicPopulation).
     *
     * @since 1.6.0
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $initialFormData = [];

    /**
     * Text typed into each field's "Other" choice, keyed by field name.
     *
     * The choice itself stays selected in $formData as FormField::OTHER_CHOICE_VALUE
     * and is replaced with this text when the form is validated and saved.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public array $otherValues = [];

    /**
     * The seed randomized choices are shuffled with, so re-renders keep their order.
     *
     * @since 1.6.0
     */
    #[Locked]
    public int $choiceSeed = 0;

    /**
     * Uploaded files keyed by field name.
     *
     * @var array<string, mixed>
     */
    public array $files = [];

    /**
     * Honeypot field value for spam protection.
     */
    public string $honeypot = '';

    /**
     * Whether the form has been successfully submitted.
     */
    public bool $isSubmitted = false;

    /**
     * Current step index for multi-step forms (0-based).
     *
     * Locked since 1.6.0: steps change only through nextStep(),
     * previousStep() and goToStep(), which validate the steps passed.
     */
    #[Locked]
    public int $currentStepIndex = 0;

    /**
     * Timestamp when the form was loaded (for bot detection).
     *
     * Locked since 1.6.0, so the browser can't backdate it to pass the
     * minimum-time spam check.
     */
    #[Locked]
    public int $formLoadedAt;

    /**
     * Error message to display.
     */
    public ?string $errorMessage = null;

    /**
     * Fields hidden by conditional logic.
     *
     * Locked since 1.6.0: the server works it out from the form data
     * (evaluateConditionalLogic()), so the browser can't hide a required
     * field to skip its validation.
     *
     * @var array<string, bool>
     */
    #[Locked]
    public array $hiddenFields = [];

    /**
     * The submission service instance.
     */
    protected SubmissionService $submissionService;

    /**
     * The conditional logic service instance.
     */
    protected ConditionalLogicService $conditionalLogicService;

    /**
     * Boot the component.
     */
    public function boot( SubmissionService $submissionService, ConditionalLogicService $conditionalLogicService ): void
    {
        $this->submissionService       = $submissionService;
        $this->conditionalLogicService = $conditionalLogicService;
    }

    /**
     * Mount the component.
     *
     * @param  Form  $form  The form to render.
     * @param  array<string, mixed>  $prefill  Values for fields with dynamic population on, keyed by
     *                                          parameter name (since 1.6.0).
     */
    public function mount( Form $form, array $prefill = [] ): void
    {
        $this->form            = $form->load( ['fields', 'steps.fields'] );
        $this->formLoadedAt    = now()->getTimestamp();
        $this->choiceSeed      = random_int( 1, 2147483647 );
        $this->initialFormData = $this->buildInitialFormData( PopulationContext::fromPageRequest( request() ), $prefill );
        $this->initializeFormData();
        $this->evaluateConditionalLogic();
    }

    // =========================================
    // Computed Properties
    // =========================================

    /**
     * Get fields for the current view (current step or all fields).
     *
     * Administrative fields are left out, since admins fill them in on the
     * submission. Each field gets the visitor's choice seed, so randomized
     * choices keep their order across re-renders.
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function currentFields(): Collection
    {
        if ( $this->form->is_multi_step ) {
            $currentStep = $this->currentStep;

            $fields = $currentStep
                ? $currentStep->fields()->orderBy( 'sort_order' )->get()
                : collect();
        } else {
            $fields = $this->form->fields()->whereNull( 'step_id' )->orderBy( 'sort_order' )->get();
        }

        return $fields
            ->reject( fn ( FormField $field ): bool => $field->isAdministrative() )
            ->each( fn ( FormField $field ): FormField => $field->withSettingsForm( $this->form )->withChoiceSeed( $this->choiceSeed ) )
            ->values();
    }

    /**
     * Get the current step for multi-step forms.
     */
    #[Computed]
    public function currentStep(): ?FormStep
    {
        if ( ! $this->form->is_multi_step ) {
            return null;
        }

        $steps = $this->form->steps()->orderBy( 'sort_order' )->get();

        return $steps->get( $this->currentStepIndex );
    }

    /**
     * Get the total number of steps.
     */
    #[Computed]
    public function totalSteps(): int
    {
        if ( ! $this->form->is_multi_step ) {
            return 1;
        }

        return $this->form->steps()->count();
    }

    /**
     * Check if on the first step.
     */
    #[Computed]
    public function isFirstStep(): bool
    {
        return 0 === $this->currentStepIndex;
    }

    /**
     * Check if on the last step.
     */
    #[Computed]
    public function isLastStep(): bool
    {
        return $this->currentStepIndex >= $this->totalSteps - 1;
    }

    /**
     * Get the progress percentage for multi-step forms.
     */
    #[Computed]
    public function progressPercentage(): int
    {
        if ( $this->totalSteps <= 1 ) {
            return 100;
        }

        return (int) round( ( ( $this->currentStepIndex + 1 ) / $this->totalSteps ) * 100 );
    }

    /**
     * Get all steps for multi-step forms.
     *
     * @return Collection<int, FormStep>
     */
    #[Computed]
    public function steps(): Collection
    {
        return $this->form->steps()->orderBy( 'sort_order' )->get();
    }

    /**
     * Get field mapping for Alpine.js (name => uuid).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function fieldMap(): array
    {
        $map = [];
        foreach ( $this->form->fields as $field ) {
            $map[ $field->name ] = $field->uuid;
        }

        return $map;
    }

    /**
     * Get conditional logic configuration for all fields.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function conditionalLogicConfig(): array
    {
        $config = [];
        foreach ( $this->form->fields as $field ) {
            if ( $field->has_conditional_logic ) {
                $config[ $field->name ] = $field->conditional_logic;
            }
        }

        return $config;
    }

    /**
     * Get the fields each section groups, for Alpine to hide with a hidden section.
     *
     * CAPTCHAs are left out, since conditional logic never hides one.
     *
     * @since 1.6.0
     *
     * @return array<string, list<string>> Member field names keyed by section name.
     */
    #[Computed]
    public function sectionMembers(): array
    {
        $captchas = $this->form->fields
            ->filter( fn ( FormField $field ): bool => CaptchaFieldType::TYPE === $field->type )
            ->pluck( 'name' )
            ->all();

        return array_map(
            fn ( array $members ): array => array_values( array_diff( $members, $captchas ) ),
            Sections::members( $this->form->fields ),
        );
    }

    // =========================================
    // Navigation Methods
    // =========================================

    /**
     * Go to the next step.
     */
    public function nextStep(): void
    {
        if ( ! $this->form->is_multi_step || $this->isLastStep ) {
            return;
        }

        // Validate current step fields before proceeding
        if ( ! $this->validateCurrentStep() ) {
            return;
        }

        $this->currentStepIndex++;
        $this->clearComputedProperties();

        $this->dispatch( 'step-changed', step: $this->currentStepIndex + 1 );
    }

    /**
     * Go to the previous step.
     */
    public function previousStep(): void
    {
        if ( ! $this->form->is_multi_step || $this->isFirstStep ) {
            return;
        }

        $this->currentStepIndex--;
        $this->clearComputedProperties();

        $this->dispatch( 'step-changed', step: $this->currentStepIndex + 1 );
    }

    /**
     * Go to a specific step (if allowed).
     */
    public function goToStep( int $stepIndex ): void
    {
        if ( ! $this->form->is_multi_step ) {
            return;
        }

        if ( ! $this->form->allow_step_navigation ) {
            return;
        }

        if ( $stepIndex < 0 || $stepIndex >= $this->totalSteps ) {
            return;
        }

        // Save original index to restore on validation failure
        $originalStepIndex = $this->currentStepIndex;

        // Only allow going back or to current step without validation
        if ( $stepIndex > $this->currentStepIndex ) {
            // Validate all steps up to the target step
            for ( $i = $originalStepIndex; $i < $stepIndex; $i++ ) {
                // Temporarily set index for validation
                $this->currentStepIndex = $i;
                $this->clearComputedProperties();

                if ( ! $this->validateCurrentStep() ) {
                    // Restore original index on validation failure
                    $this->currentStepIndex = $originalStepIndex;
                    $this->clearComputedProperties();

                    return;
                }
            }
        }

        $this->currentStepIndex = $stepIndex;
        $this->clearComputedProperties();

        $this->dispatch( 'step-changed', step: $this->currentStepIndex + 1 );
    }

    // =========================================
    // Submission Methods
    // =========================================

    /**
     * Submit the form.
     */
    public function submit(): void
    {
        $this->errorMessage = null;

        // Check for spam
        if ( $this->submissionService->isSpam( $this->honeypot, $this->formLoadedAt ) ) {
            // Silent success for bots - don't save but pretend it worked
            $this->isSubmitted = true;
            $this->dispatch( 'form-submitted', formId: $this->form->id, isSpam: true );

            return;
        }

        // Check rate limiting
        $ipAddress = request()->ip() ?? 'unknown';
        if ( $this->submissionService->isRateLimited( $ipAddress, $this->form->id ) ) {
            $this->errorMessage = 'Too many submissions. Please try again later.';

            return;
        }

        // Validate all fields
        try {
            if ( ! $this->validateAllFields() ) {
                return;
            }
        } catch ( \Illuminate\Validation\ValidationException $e ) {
            // Validation errors are handled by Livewire
            throw $e;
        }

        // Record the attempt for rate limiting
        $this->submissionService->recordAttempt( $ipAddress, $this->form->id );

        // Check CAPTCHA tokens last, since a provider accepts each token once
        if ( ! $this->passesCaptcha( request()->ip() ) ) {
            return;
        }

        try {
            // Get metadata
            $metadata = $this->submissionService->getRequestMetadata( request() );

            // Create the submission
            $submission = $this->submissionService->create(
                $this->form,
                $this->visibleFormData(),
                $this->visibleFiles(),
                $metadata,
            );

            $this->isSubmitted = true;

            // Dispatch success event
            $this->dispatch( 'form-submitted', formId: $this->form->id, submissionId: $submission->id );

            // Handle redirect if configured
            if ( $this->form->redirect_url ) {
                $this->dispatch( 'redirect-to', url: $this->form->redirect_url );
            }

        } catch ( Exception $e ) {
            Log::error( 'Form submission failed', [
                'form_id' => $this->form->id,
                'error'   => $e->getMessage(),
            ] );

            $this->errorMessage = 'An error occurred while submitting the form. Please try again.';

            // The challenge token was spent, so a retry needs a new one
            $this->dispatch( 'forms-captcha-reset', formId: $this->form->id );
        }
    }

    /**
     * Removes one file from a multi-file field before the form is submitted.
     *
     * The drop zone uploads each file under its own key in
     * `$files[$fieldName]`, so removing one leaves the others in place.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The file field's name.
     * @param  string  $key  The key the file was uploaded under.
     */
    public function removeFile( string $fieldName, string $key ): void
    {
        if ( ! isset( $this->files[ $fieldName ] ) || ! is_array( $this->files[ $fieldName ] ) ) {
            return;
        }

        unset( $this->files[ $fieldName ][ $key ] );

        $this->resetValidation( "files.{$fieldName}" );
    }

    /**
     * Adds an empty row to a list field, up to its max rows.
     *
     * Dispatches `forms-list-row-added` with the field and the new row's
     * index, so the partial can focus the row and announce it.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The list field's name.
     */
    public function addListRow( string $fieldName ): void
    {
        $field = $this->listField( $fieldName );

        if ( null === $field ) {
            return;
        }

        $rows = $this->listRows( $fieldName );

        if ( count( $rows ) >= ListFieldType::maxRows( $field ) ) {
            return;
        }

        $rows[]                       = array_fill_keys( array_column( ListFieldType::columns( $field ), 'key' ), '' );
        $this->formData[ $fieldName ] = $rows;

        $this->evaluateConditionalLogic();

        $this->dispatch( 'forms-list-row-added', field: $fieldName, index: count( $rows ) - 1 );
    }

    /**
     * Removes a row from a list field, keeping one empty row when it was the last.
     *
     * Clears the field's errors, since the rows after it move up, and
     * dispatches `forms-list-row-removed` with the field and the removed row's
     * index.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The list field's name.
     * @param  int  $index  The row's index.
     */
    public function removeListRow( string $fieldName, int $index ): void
    {
        $field = $this->listField( $fieldName );
        $rows  = $this->listRows( $fieldName );

        if ( null === $field || ! array_key_exists( $index, $rows ) ) {
            return;
        }

        unset( $rows[ $index ] );

        $this->formData[ $fieldName ] = [] === $rows
            ? [array_fill_keys( array_column( ListFieldType::columns( $field ), 'key' ), '' )]
            : array_values( $rows );

        $this->evaluateConditionalLogic();

        foreach ( $this->getErrorBag()->keys() as $key ) {
            if ( "formData.{$fieldName}" === $key || str_starts_with( (string) $key, "formData.{$fieldName}." ) ) {
                $this->resetValidation( $key );
            }
        }

        $this->dispatch( 'forms-list-row-removed', field: $fieldName, index: $index );
    }

    /**
     * Moves one choice of a ranking field to a new position.
     *
     * Positions count from 0. Dispatches `forms-ranking-moved` with the
     * field, the choice's value and its new position, so the partial can keep
     * focus on the moved choice and announce where it went.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The ranking field's name.
     * @param  int  $from  The choice's position.
     * @param  int  $to  The position to move it to.
     */
    public function moveRankingItem( string $fieldName, int $from, int $to ): void
    {
        $field = $this->form->fields->firstWhere( 'name', $fieldName );

        if ( ! $field instanceof FormField || RankingFieldType::TYPE !== $field->type || $field->isAdministrative() || $field->isReadOnly() ) {
            return;
        }

        $order = RankingFieldType::order( $field, $this->formData[ $fieldName ] ?? [] );
        $moved = RankingFieldType::move( $order, $from, $to );

        if ( $moved === $order ) {
            return;
        }

        $this->formData[ $fieldName ] = $moved;

        $this->evaluateConditionalLogic();

        $this->resetValidation( "formData.{$fieldName}" );

        $this->dispatch( 'forms-ranking-moved', field: $fieldName, value: $moved[ $to ], position: $to );
    }

    /**
     * Reset the form to allow another submission.
     *
     * Conditional logic is worked out again for the starting values, since
     * the browser no longer sends hidden fields back (since 1.6.0).
     */
    public function resetForm(): void
    {
        $this->isSubmitted      = false;
        $this->currentStepIndex = 0;
        $this->errorMessage     = null;
        $this->files            = [];
        $this->otherValues      = [];
        $this->formLoadedAt     = now()->getTimestamp();
        $this->initializeFormData();
        $this->evaluateConditionalLogic();
        $this->clearComputedProperties();

        $this->dispatch( 'form-reset', formId: $this->form->id );
    }

    // =========================================
    // Conditional Logic
    // =========================================

    /**
     * Update field visibility based on conditional logic.
     *
     * Called when form data changes to evaluate conditional rules.
     */
    public function updatedFormData(): void
    {
        $this->evaluateConditionalLogic();
    }

    /**
     * Check if a field should be visible.
     */
    public function isFieldVisible( string $fieldName ): bool
    {
        return ! ( $this->hiddenFields[ $fieldName ] ?? false );
    }

    /**
     * Get the CSS class for a field based on its width.
     */
    public function getFieldWidthClass( FormField $field ): string
    {
        return $field->width_class;
    }

    // =========================================
    // Serialization
    // =========================================

    /**
     * Return data for JSON serialization.
     *
     * This method is called by Alpine/Livewire integration when serializing
     * component data. Required to prevent "toJSON method not found" errors.
     *
     * @return array<string, mixed>
     */
    public function toJSON(): array
    {
        return [
            'formData'         => $this->formData,
            'hiddenFields'     => $this->hiddenFields,
            'currentStepIndex' => $this->currentStepIndex,
            'isSubmitted'      => $this->isSubmitted,
        ];
    }

    // =========================================
    // Render
    // =========================================

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view( 'forms::livewire.form-renderer' );
    }

    /**
     * Initialize form data with the values the form starts with.
     */
    protected function initializeFormData(): void
    {
        $this->formData = $this->initialFormData;
    }

    /**
     * Works out the value each field starts with.
     *
     * Read-only fields start with their read-only value; other fields with
     * their default value (merge tags replaced) or, when dynamic population
     * is on, the prefill, query string or filter value.
     *
     * @since 1.6.0
     *
     * @param  PopulationContext  $context  The visitor and page.
     * @param  array<string, mixed>  $prefill  Values passed to the component, keyed by parameter name.
     *
     * @return array<string, mixed> The values keyed by field name.
     */
    protected function buildInitialFormData( PopulationContext $context, array $prefill ): array
    {
        $data = [];

        foreach ( $this->form->fields as $field ) {
            // File inputs bind to $files, so seeding formData would only shadow them;
            // administrative fields are not on the public form.
            if ( $field->is_file_field || $field->isAdministrative() ) {
                continue;
            }

            $data[ $field->name ] = DynamicPopulation::initialValue( $field, $context, $prefill );

            // A populated email is confirmed with the same value
            if ( $field->requiresEmailConfirmation() && ! array_key_exists( $field->getConfirmationKey(), $data ) ) {
                $data[ $field->getConfirmationKey() ] = is_string( $data[ $field->name ] ) ? $data[ $field->name ] : '';
            }

            // The country selector starts at the field's default country
            if ( $field->usesPhoneCountrySelector() && ! array_key_exists( $field->getPhoneCountryKey(), $data ) ) {
                $data[ $field->getPhoneCountryKey() ] = $field->getPhoneDefaultCountry();
            }
        }

        return $data;
    }

    // =========================================
    // Validation Methods
    // =========================================

    /**
     * Validate the current step's fields.
     */
    protected function validateCurrentStep(): bool
    {
        $this->resetReadOnlyValues();

        $rules = $this->submissionService->buildValidationRules(
            $this->currentFields,
            $this->hiddenFields,
        );

        $messages = $this->submissionService->buildValidationMessages( $this->currentFields );

        if ( empty( $rules ) ) {
            return true;
        }

        $this->withResolvedOtherChoices()->validate( $rules, $messages );

        return true;
    }

    /**
     * Validate all form fields.
     */
    protected function validateAllFields(): bool
    {
        $this->resetReadOnlyValues();

        $allFields = $this->form->is_multi_step
            ? $this->form->fields_ordered
            : $this->form->fields()->whereNull( 'step_id' )->orderBy( 'sort_order' )->get();

        $rules    = $this->submissionService->buildValidationRules( $allFields, $this->hiddenFields );
        $messages = $this->submissionService->buildValidationMessages( $allFields );

        if ( empty( $rules ) ) {
            return true;
        }

        $this->withResolvedOtherChoices()->validate( $rules, $messages );

        return true;
    }

    /**
     * Checks the form's CAPTCHA tokens with their providers.
     *
     * On a failure the field gets the error, a multi-step form moves to the
     * field's step, and `forms-captcha-reset` is dispatched so the widget
     * asks for a new token.
     *
     * @since 1.6.0
     *
     * @param  string|null  $ipAddress  The visitor's IP address.
     *
     * @return bool True when every token passed.
     */
    protected function passesCaptcha( ?string $ipAddress ): bool
    {
        $errors = app( CaptchaVerifier::class )->verify( $this->form->fields, $this->formData, $this->hiddenFields, $ipAddress );

        if ( [] === $errors ) {
            return true;
        }

        foreach ( $errors as $fieldName => $message ) {
            $this->formData[ $fieldName ] = '';
            $this->addError( "formData.{$fieldName}", $message );
        }

        if ( $this->form->is_multi_step ) {
            $stepId = $this->form->fields->firstWhere( 'name', array_key_first( $errors ) )?->step_id;
            $index  = $this->steps->search( fn ( FormStep $step ): bool => $step->id === $stepId );

            if ( false !== $index ) {
                $this->currentStepIndex = (int) $index;
                $this->clearComputedProperties();
            }
        }

        $this->dispatch( 'forms-captcha-reset', formId: $this->form->id );

        return false;
    }

    /**
     * Validates the form data with each selected "Other" choice replaced by its text.
     *
     * The choice stays selected in $formData, so the radio or checkbox keeps
     * its state; only the data the validator sees is resolved.
     *
     * @since 1.6.0
     *
     * @return static The component.
     */
    protected function withResolvedOtherChoices(): static
    {
        return $this->withValidator( function ( Validator $validator ): void {
            $validator->setData( array_replace( $validator->getData(), ['formData' => $this->resolvedFormData()] ) );
        } );
    }

    /**
     * Gets the form data with each selected "Other" choice replaced by its text.
     *
     * Numbers typed in a number field's format are converted to plain
     * decimals, URLs typed without a scheme get one and emptied rich text is
     * blanked as well (see SubmissionService::normalizeSubmittedValues()),
     * while $formData keeps what the visitor typed.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The form data.
     */
    protected function resolvedFormData(): array
    {
        $formData = $this->submissionService->resolveOtherChoices( $this->form->fields, $this->formData, $this->otherValues );

        return $this->submissionService->normalizeSubmittedValues( $this->form->fields, $formData );
    }

    /**
     * Gets the resolved values of the fields conditional logic shows.
     *
     * Hidden fields (including every field in a hidden section) are not
     * validated, so their values are not stored either, as the submit API
     * drops them (SubmitFormApiRequest::sanitizedData()).
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The values keyed by field name.
     */
    protected function visibleFormData(): array
    {
        return array_filter(
            $this->resolvedFormData(),
            fn ( string|int $fieldName ): bool => $this->isFieldVisible( (string) $fieldName ),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Gets the uploaded files of the fields conditional logic shows.
     *
     * Hidden fields are not validated, so their files are not stored either,
     * as the submit API drops them (SubmitFormApiRequest::sanitizedFiles()).
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The files keyed by field name.
     */
    protected function visibleFiles(): array
    {
        return array_filter(
            $this->files,
            fn ( string $fieldName ): bool => $this->isFieldVisible( $fieldName ),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Put read-only fields back to their own values, discarding client edits.
     *
     * Conditional logic is re-evaluated so field visibility follows the
     * real values rather than the discarded ones.
     *
     * @since 1.6.0
     */
    protected function resetReadOnlyValues(): void
    {
        $this->formData = $this->submissionService->applyReadOnlyValues( $this->form->fields, $this->formData );

        $this->evaluateConditionalLogic();
    }

    /**
     * Evaluate conditional logic for all fields.
     *
     * Calculation fields are worked out first, so conditions can use their
     * results, and again without the values of the fields conditional logic
     * hides, since those are not submitted (since 1.6.0).
     *
     * `hiddenFields` is locked (since 1.6.0) and updatedFormData() runs only
     * for updates from the browser, so an action that changes `formData` on
     * the server (list rows, ranking moves, a reset) calls this itself.
     */
    protected function evaluateConditionalLogic(): void
    {
        $fields = $this->form->fields;

        $this->formData     = $this->submissionService->applyCalculations( $fields, $this->formData );
        $this->hiddenFields = $this->conditionalLogicService->getHiddenFields( $fields, $this->formData );

        if ( ! in_array( true, $this->hiddenFields, true ) || ! $fields->contains( 'type', CalculationFieldType::TYPE ) ) {
            return;
        }

        $visible = $this->submissionService->applyCalculations( $fields, $this->visibleFormData(), false, true );

        foreach ( $fields->where( 'type', CalculationFieldType::TYPE ) as $field ) {
            $this->formData[ $field->name ] = $visible[ $field->name ];
        }

        $this->hiddenFields = $this->conditionalLogicService->getHiddenFields( $fields, $this->formData );
    }

    // =========================================
    // Helper Methods
    // =========================================

    /**
     * Gets a list field of the form by name.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The field's name.
     *
     * @return FormField|null The field, or null when the form has no list field by that name.
     */
    protected function listField( string $fieldName ): ?FormField
    {
        $field = $this->form->fields->firstWhere( 'name', $fieldName );

        return $field instanceof FormField && ListFieldType::TYPE === $field->type && ! $field->isAdministrative() ? $field : null;
    }

    /**
     * Gets a list field's rows from the form data, numbered from 0.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The field's name.
     *
     * @return list<array<string, mixed>> The rows.
     */
    protected function listRows( string $fieldName ): array
    {
        $rows = $this->formData[ $fieldName ] ?? [];

        return is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : [];
    }

    /**
     * Clear computed properties to force recalculation.
     */
    protected function clearComputedProperties(): void
    {
        unset(
            $this->currentFields,
            $this->currentStep,
            $this->totalSteps,
            $this->isFirstStep,
            $this->isLastStep,
            $this->progressPercentage,
            $this->steps,
            $this->fieldMap,
            $this->conditionalLogicConfig,
            $this->sectionMembers,
        );
    }
}
