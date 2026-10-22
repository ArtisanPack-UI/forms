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

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\PopulationContext;
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
     */
    public int $currentStepIndex = 0;

    /**
     * Timestamp when the form was loaded (for bot detection).
     */
    public int $formLoadedAt;

    /**
     * Error message to display.
     */
    public ?string $errorMessage = null;

    /**
     * Fields hidden by conditional logic.
     *
     * @var array<string, bool>
     */
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
        $this->formLoadedAt    = time();
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

        try {
            // Get metadata
            $metadata = $this->submissionService->getRequestMetadata( request() );

            // Create the submission
            $submission = $this->submissionService->create(
                $this->form,
                $this->resolvedFormData(),
                $this->files,
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
        }
    }

    /**
     * Reset the form to allow another submission.
     */
    public function resetForm(): void
    {
        $this->isSubmitted      = false;
        $this->currentStepIndex = 0;
        $this->errorMessage     = null;
        $this->files            = [];
        $this->otherValues      = [];
        $this->formLoadedAt     = time();
        $this->initializeFormData();
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
     * @since 1.6.0
     *
     * @return array<string, mixed> The form data.
     */
    protected function resolvedFormData(): array
    {
        return $this->submissionService->resolveOtherChoices( $this->form->fields, $this->formData, $this->otherValues );
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
     */
    protected function evaluateConditionalLogic(): void
    {
        $this->hiddenFields = $this->conditionalLogicService->getHiddenFields(
            $this->form->fields,
            $this->formData,
        );
    }

    // =========================================
    // Helper Methods
    // =========================================

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
        );
    }
}
