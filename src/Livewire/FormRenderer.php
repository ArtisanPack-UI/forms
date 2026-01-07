<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * FormRenderer Livewire Component
 *
 * Public-facing form renderer that displays forms, handles validation,
 * processes submissions, and provides user feedback. Includes spam
 * protection, file uploads, and multi-step navigation.
 *
 * @since 1.0.0
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
    public function boot(SubmissionService $submissionService, ConditionalLogicService $conditionalLogicService): void
    {
        $this->submissionService = $submissionService;
        $this->conditionalLogicService = $conditionalLogicService;
    }

    /**
     * Mount the component.
     */
    public function mount(Form $form): void
    {
        $this->form = $form->load(['fields', 'steps.fields']);
        $this->formLoadedAt = time();
        $this->initializeFormData();
        $this->evaluateConditionalLogic();
    }

    /**
     * Initialize form data with default values.
     */
    protected function initializeFormData(): void
    {
        foreach ($this->form->fields as $field) {
            if ($field->default_value !== null) {
                $this->formData[$field->name] = $field->default_value;
            } elseif (in_array($field->type, ['checkbox_group', 'select_multiple'])) {
                $this->formData[$field->name] = [];
            } else {
                $this->formData[$field->name] = '';
            }
        }
    }

    // =========================================
    // Computed Properties
    // =========================================

    /**
     * Get fields for the current view (current step or all fields).
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function currentFields(): Collection
    {
        if ($this->form->is_multi_step) {
            $currentStep = $this->currentStep;

            return $currentStep
                ? $currentStep->fields()->orderBy('sort_order')->get()
                : collect();
        }

        return $this->form->fields()->whereNull('step_id')->orderBy('sort_order')->get();
    }

    /**
     * Get the current step for multi-step forms.
     */
    #[Computed]
    public function currentStep(): ?FormStep
    {
        if (! $this->form->is_multi_step) {
            return null;
        }

        $steps = $this->form->steps()->orderBy('sort_order')->get();

        return $steps->get($this->currentStepIndex);
    }

    /**
     * Get the total number of steps.
     */
    #[Computed]
    public function totalSteps(): int
    {
        if (! $this->form->is_multi_step) {
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
        return $this->currentStepIndex === 0;
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
        if ($this->totalSteps <= 1) {
            return 100;
        }

        return (int) round((($this->currentStepIndex + 1) / $this->totalSteps) * 100);
    }

    /**
     * Get all steps for multi-step forms.
     *
     * @return Collection<int, FormStep>
     */
    #[Computed]
    public function steps(): Collection
    {
        return $this->form->steps()->orderBy('sort_order')->get();
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
        foreach ($this->form->fields as $field) {
            $map[$field->name] = $field->uuid;
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
        foreach ($this->form->fields as $field) {
            if ($field->has_conditional_logic) {
                $config[$field->name] = $field->conditional_logic;
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
        if (! $this->form->is_multi_step || $this->isLastStep) {
            return;
        }

        // Validate current step fields before proceeding
        if (! $this->validateCurrentStep()) {
            return;
        }

        $this->currentStepIndex++;
        $this->clearComputedProperties();

        $this->dispatch('step-changed', step: $this->currentStepIndex + 1);
    }

    /**
     * Go to the previous step.
     */
    public function previousStep(): void
    {
        if (! $this->form->is_multi_step || $this->isFirstStep) {
            return;
        }

        $this->currentStepIndex--;
        $this->clearComputedProperties();

        $this->dispatch('step-changed', step: $this->currentStepIndex + 1);
    }

    /**
     * Go to a specific step (if allowed).
     */
    public function goToStep(int $stepIndex): void
    {
        if (! $this->form->is_multi_step) {
            return;
        }

        if (! $this->form->allow_step_navigation) {
            return;
        }

        if ($stepIndex < 0 || $stepIndex >= $this->totalSteps) {
            return;
        }

        // Save original index to restore on validation failure
        $originalStepIndex = $this->currentStepIndex;

        // Only allow going back or to current step without validation
        if ($stepIndex > $this->currentStepIndex) {
            // Validate all steps up to the target step
            for ($i = $originalStepIndex; $i < $stepIndex; $i++) {
                // Temporarily set index for validation
                $this->currentStepIndex = $i;
                $this->clearComputedProperties();

                if (! $this->validateCurrentStep()) {
                    // Restore original index on validation failure
                    $this->currentStepIndex = $originalStepIndex;
                    $this->clearComputedProperties();

                    return;
                }
            }
        }

        $this->currentStepIndex = $stepIndex;
        $this->clearComputedProperties();

        $this->dispatch('step-changed', step: $this->currentStepIndex + 1);
    }

    // =========================================
    // Validation Methods
    // =========================================

    /**
     * Validate the current step's fields.
     */
    protected function validateCurrentStep(): bool
    {
        $rules = $this->submissionService->buildValidationRules(
            $this->currentFields,
            $this->hiddenFields
        );

        $messages = $this->submissionService->buildValidationMessages($this->currentFields);

        if (empty($rules)) {
            return true;
        }

        $this->validate($rules, $messages);

        return true;
    }

    /**
     * Validate all form fields.
     */
    protected function validateAllFields(): bool
    {
        $allFields = $this->form->is_multi_step
            ? $this->form->fields_ordered
            : $this->form->fields()->whereNull('step_id')->orderBy('sort_order')->get();

        $rules = $this->submissionService->buildValidationRules($allFields, $this->hiddenFields);
        $messages = $this->submissionService->buildValidationMessages($allFields);

        if (empty($rules)) {
            return true;
        }

        $this->validate($rules, $messages);

        return true;
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
        if ($this->submissionService->isSpam($this->honeypot, $this->formLoadedAt)) {
            // Silent success for bots - don't save but pretend it worked
            $this->isSubmitted = true;
            $this->dispatch('form-submitted', formId: $this->form->id, isSpam: true);

            return;
        }

        // Check rate limiting
        $ipAddress = request()->ip() ?? 'unknown';
        if ($this->submissionService->isRateLimited($ipAddress, $this->form->id)) {
            $this->errorMessage = 'Too many submissions. Please try again later.';

            return;
        }

        // Validate all fields
        try {
            if (! $this->validateAllFields()) {
                return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors are handled by Livewire
            throw $e;
        }

        // Record the attempt for rate limiting
        $this->submissionService->recordAttempt($ipAddress, $this->form->id);

        try {
            // Get metadata
            $metadata = $this->submissionService->getRequestMetadata(request());

            // Create the submission
            $submission = $this->submissionService->create(
                $this->form,
                $this->formData,
                $this->files,
                $metadata
            );

            $this->isSubmitted = true;

            // Dispatch success event
            $this->dispatch('form-submitted', formId: $this->form->id, submissionId: $submission->id);

            // Handle redirect if configured
            if ($this->form->redirect_url) {
                $this->dispatch('redirect-to', url: $this->form->redirect_url);
            }

        } catch (\Exception $e) {
            Log::error('Form submission failed', [
                'form_id' => $this->form->id,
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'An error occurred while submitting the form. Please try again.';
        }
    }

    /**
     * Reset the form to allow another submission.
     */
    public function resetForm(): void
    {
        $this->isSubmitted = false;
        $this->currentStepIndex = 0;
        $this->errorMessage = null;
        $this->files = [];
        $this->formLoadedAt = time();
        $this->initializeFormData();
        $this->clearComputedProperties();

        $this->dispatch('form-reset', formId: $this->form->id);
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
     * Evaluate conditional logic for all fields.
     */
    protected function evaluateConditionalLogic(): void
    {
        $this->hiddenFields = $this->conditionalLogicService->getHiddenFields(
            $this->form->fields,
            $this->formData
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
            $this->conditionalLogicConfig
        );
    }

    /**
     * Check if a field should be visible.
     */
    public function isFieldVisible(string $fieldName): bool
    {
        return ! ($this->hiddenFields[$fieldName] ?? false);
    }

    /**
     * Get the CSS class for a field based on its width.
     */
    public function getFieldWidthClass(FormField $field): string
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
            'formData' => $this->formData,
            'hiddenFields' => $this->hiddenFields,
            'currentStepIndex' => $this->currentStepIndex,
            'isSubmitted' => $this->isSubmitted,
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
        return view('forms::livewire.form-renderer');
    }
}
