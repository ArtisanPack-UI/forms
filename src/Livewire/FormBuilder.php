<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\FieldService;
use ArtisanPackUI\Forms\Services\StepService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * FormBuilder Livewire Component
 *
 * Provides a drag-and-drop interface for building forms with:
 * - Field palette for adding new fields
 * - Canvas for arranging fields
 * - Field editor sidebar for configuring selected fields
 * - Multi-step form support
 * - Auto-save with dirty state tracking
 *
 * @since 1.0.0
 */
class FormBuilder extends Component
{
    /**
     * The form being edited.
     */
    public Form $form;

    /**
     * The currently selected field UUID.
     */
    public ?string $selectedFieldId = null;

    /**
     * The currently active step ID (for multi-step forms).
     */
    public ?int $activeStepId = null;

    /**
     * Whether there are unsaved changes.
     */
    public bool $isDirty = false;

    /**
     * Whether the form is currently saving.
     */
    public bool $isSaving = false;

    /**
     * Last save timestamp for display.
     */
    public ?string $lastSavedAt = null;

    /**
     * The field service instance.
     */
    protected FieldService $fieldService;

    /**
     * The step service instance.
     */
    protected StepService $stepService;

    /**
     * The conditional logic service instance.
     */
    protected ConditionalLogicService $conditionalLogicService;

    /**
     * Boot the component.
     */
    public function boot(
        FieldService $fieldService,
        StepService $stepService,
        ConditionalLogicService $conditionalLogicService,
    ): void {
        $this->fieldService            = $fieldService;
        $this->stepService             = $stepService;
        $this->conditionalLogicService = $conditionalLogicService;
    }

    /**
     * Mount the component.
     */
    public function mount( Form $form ): void
    {
        $this->form = $form->load( ['fields', 'steps.fields'] );

        // Set active step to first step for multi-step forms
        if ( $this->form->is_multi_step && $this->form->steps->isNotEmpty() ) {
            $this->activeStepId = $this->form->steps->first()->id;
        }
    }

    // =========================================
    // Computed Properties
    // =========================================

    /**
     * Get field type categories with their field types.
     *
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    #[Computed]
    public function fieldCategories(): array
    {
        $categories = [];

        foreach ( FieldTypes::getCategories() as $key => $category ) {
            $fields = [];
            foreach ( $category['fields'] as $fieldType ) {
                $config = FieldTypes::getTypeConfig( $fieldType );
                if ( $config ) {
                    $fields[ $fieldType ] = $config;
                }
            }
            $categories[ $key ] = [
                'label'  => $category['label'],
                'fields' => $fields,
            ];
        }

        return $categories;
    }

    /**
     * Get the fields for the current view (step or all).
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function fields(): Collection
    {
        $query = $this->form->fields()->orderBy( 'sort_order' );

        if ( $this->form->is_multi_step && null !== $this->activeStepId ) {
            $query->where( 'step_id', $this->activeStepId );
        } elseif ( ! $this->form->is_multi_step ) {
            $query->whereNull( 'step_id' );
        }

        return $query->get();
    }

    /**
     * Get the currently selected field.
     */
    #[Computed]
    public function selectedField(): ?FormField
    {
        if ( null === $this->selectedFieldId ) {
            return null;
        }

        return $this->form->fields()->where( 'uuid', $this->selectedFieldId )->first();
    }

    /**
     * Get all steps for multi-step forms.
     *
     * @return Collection<int, \ArtisanPackUI\Forms\Models\FormStep>
     */
    #[Computed]
    public function steps(): Collection
    {
        return $this->form->steps()->withCount( 'fields' )->orderBy( 'sort_order' )->get();
    }

    /**
     * Get available width options.
     *
     * @return array<string, array{label: string, class: string}>
     */
    #[Computed]
    public function widthOptions(): array
    {
        return FieldTypes::getWidths();
    }

    /**
     * Get fields that can be used as condition targets for the selected field.
     *
     * Excludes the selected field itself, layout fields, and (for multi-step forms)
     * fields from later steps.
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function availableConditionFields(): Collection
    {
        $selectedField = $this->selectedField;

        if ( ! $selectedField ) {
            return collect();
        }

        return $this->conditionalLogicService->getAvailableConditionTargets(
            $selectedField,
            $this->form->fields,
        );
    }

    /**
     * Get conditional logic action options.
     *
     * @return array<string, array{label: string, description: string}>
     */
    #[Computed]
    public function conditionActions(): array
    {
        return ConditionalLogic::getActions();
    }

    /**
     * Get conditional logic types (all/any).
     *
     * @return array<string, array{label: string, description: string}>
     */
    #[Computed]
    public function conditionLogicTypes(): array
    {
        return ConditionalLogic::getLogicTypes();
    }

    /**
     * Get conditional logic operators.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function conditionOperators(): array
    {
        return ConditionalLogic::getOperators();
    }

    // =========================================
    // Field Operations
    // =========================================

    /**
     * Add a new field to the form.
     */
    public function addField( string $type ): void
    {
        $stepId = $this->form->is_multi_step ? $this->activeStepId : null;

        $field = $this->fieldService->create( $this->form, $type, $stepId );

        $this->selectedFieldId = $field->uuid;
        $this->markDirty();

        // Refresh computed properties
        unset( $this->fields );

        $this->dispatch( 'field-added', uuid: $field->uuid );
    }

    /**
     * Select a field for editing.
     */
    public function selectField( string $uuid ): void
    {
        $this->selectedFieldId = $uuid;
        unset( $this->selectedField );
    }

    /**
     * Deselect the current field.
     */
    public function deselectField(): void
    {
        $this->selectedFieldId = null;
        unset( $this->selectedField );
    }

    /**
     * Update the selected field.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateField( array $data ): void
    {
        if ( null === $this->selectedFieldId ) {
            return;
        }

        $field = $this->fieldService->getByUuid( $this->form, $this->selectedFieldId );

        if ( $field ) {
            $this->fieldService->update( $field, $data );
            $this->markDirty();

            unset( $this->fields, $this->selectedField );
        }
    }

    /**
     * Update conditional logic for the selected field.
     *
     * @param  array<string, mixed>  $logic
     */
    public function updateConditionalLogic( array $logic ): void
    {
        if ( null === $this->selectedFieldId ) {
            return;
        }

        $field = $this->fieldService->getByUuid( $this->form, $this->selectedFieldId );

        if ( $field ) {
            // Validate the logic structure
            $validation = ConditionalLogic::validate( $logic );

            if ( ! $validation['valid'] ) {
                $this->dispatch( 'conditional-logic-error', errors: $validation['errors'] );

                return;
            }

            // Clean up any references to deleted fields
            $logic = $this->conditionalLogicService->cleanupDeletedFieldReferences(
                $logic,
                $this->form->fields,
            );

            $this->fieldService->update( $field, ['conditional_logic' => $logic] );
            $this->markDirty();

            unset( $this->fields, $this->selectedField );

            $this->dispatch( 'conditional-logic-updated' );
        }
    }

    /**
     * Clear conditional logic from the selected field.
     */
    public function clearConditionalLogic(): void
    {
        if ( null === $this->selectedFieldId ) {
            return;
        }

        $field = $this->fieldService->getByUuid( $this->form, $this->selectedFieldId );

        if ( $field ) {
            $this->fieldService->update( $field, ['conditional_logic' => null] );
            $this->markDirty();

            unset( $this->fields, $this->selectedField );

            $this->dispatch( 'conditional-logic-cleared' );
        }
    }

    /**
     * Delete a field.
     */
    public function deleteField( string $uuid ): void
    {
        $field = $this->fieldService->getByUuid( $this->form, $uuid );

        if ( $field ) {
            $this->fieldService->delete( $field );
            $this->markDirty();

            if ( $this->selectedFieldId === $uuid ) {
                $this->selectedFieldId = null;
                unset( $this->selectedField );
            }

            unset( $this->fields );

            $this->dispatch( 'field-deleted', uuid: $uuid );
        }
    }

    /**
     * Duplicate a field.
     */
    public function duplicateField( string $uuid ): void
    {
        $field = $this->fieldService->getByUuid( $this->form, $uuid );

        if ( $field ) {
            $newField              = $this->fieldService->duplicate( $field );
            $this->selectedFieldId = $newField->uuid;
            $this->markDirty();

            unset( $this->fields, $this->selectedField );

            $this->dispatch( 'field-duplicated', uuid: $newField->uuid );
        }
    }

    /**
     * Reorder fields based on drag-and-drop.
     *
     * @param  array<int, string>  $orderedUuids
     */
    #[On( 'fields-reordered' )]
    public function reorderFields( array $orderedUuids ): void
    {
        $stepId = $this->form->is_multi_step ? $this->activeStepId : null;

        $this->fieldService->reorder( $this->form, $orderedUuids, $stepId );
        $this->markDirty();

        unset( $this->fields );
    }

    // =========================================
    // Step Operations (Multi-Step Forms)
    // =========================================

    /**
     * Add a new step to the form.
     */
    public function addStep(): void
    {
        if ( ! $this->form->is_multi_step ) {
            return;
        }

        $step                  = $this->stepService->create( $this->form );
        $this->activeStepId    = $step->id;
        $this->selectedFieldId = null;
        $this->markDirty();

        unset( $this->steps, $this->fields, $this->selectedField );

        $this->dispatch( 'step-added', stepId: $step->id );
    }

    /**
     * Select a step.
     */
    public function selectStep( int $stepId ): void
    {
        if ( ! $this->form->is_multi_step ) {
            return;
        }

        $this->activeStepId    = $stepId;
        $this->selectedFieldId = null;

        unset( $this->fields, $this->selectedField );
    }

    /**
     * Update a step.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateStep( int $stepId, array $data ): void
    {
        $step = $this->stepService->getById( $this->form, $stepId );

        if ( $step ) {
            $this->stepService->update( $step, $data );
            $this->markDirty();

            unset( $this->steps );
        }
    }

    /**
     * Delete a step.
     */
    public function deleteStep( int $stepId ): void
    {
        $step = $this->stepService->getById( $this->form, $stepId );

        if ( $step && $this->form->steps()->count() > 1 ) {
            $this->stepService->delete( $step );
            $this->markDirty();

            // Refresh the form's steps relationship before getting the first step
            $this->form->unsetRelation( 'steps' );

            // Select the first remaining step
            $firstStep             = $this->stepService->getFirstStep( $this->form );
            $this->activeStepId    = $firstStep?->id;
            $this->selectedFieldId = null;

            unset( $this->steps, $this->fields, $this->selectedField );

            $this->dispatch( 'step-deleted', stepId: $stepId );
        }
    }

    /**
     * Reorder steps based on drag-and-drop.
     *
     * @param  array<int, int>  $orderedIds
     */
    #[On( 'steps-reordered' )]
    public function reorderSteps( array $orderedIds ): void
    {
        if ( ! $this->form->is_multi_step ) {
            return;
        }

        $this->stepService->reorder( $this->form, $orderedIds );
        $this->markDirty();

        unset( $this->steps );
    }

    /**
     * Enable multi-step mode for the form.
     *
     * Creates a default first step and moves all existing fields to it.
     */
    public function enableMultiStep(): void
    {
        if ( $this->form->is_multi_step ) {
            return;
        }

        // Convert the form to multi-step
        $step = $this->stepService->convertToMultiStep( $this->form );

        // Update the form's multi-step flag
        $this->form->update( ['is_multi_step' => true] );
        $this->form->refresh();

        // Set the active step
        $this->activeStepId    = $step->id;
        $this->selectedFieldId = null;
        $this->markDirty();

        unset( $this->steps, $this->fields, $this->selectedField );

        $this->dispatch( 'multi-step-enabled' );
    }

    /**
     * Disable multi-step mode for the form.
     *
     * Removes all steps and unassigns fields from steps.
     */
    public function disableMultiStep(): void
    {
        if ( ! $this->form->is_multi_step ) {
            return;
        }

        // Convert the form to single-step
        $this->stepService->convertToSingleStep( $this->form );

        // Update the form's multi-step flag
        $this->form->update( ['is_multi_step' => false] );
        $this->form->refresh();

        // Clear step-related state
        $this->activeStepId    = null;
        $this->selectedFieldId = null;
        $this->markDirty();

        unset( $this->steps, $this->fields, $this->selectedField );

        $this->dispatch( 'multi-step-disabled' );
    }

    // =========================================
    // Save Operations
    // =========================================

    /**
     * Save the form (auto-save trigger).
     */
    public function saveForm(): void
    {
        if ( ! $this->isDirty ) {
            return;
        }

        $this->isSaving = true;

        // The form itself is saved via FormService in the parent edit page
        // This component only manages fields and steps, which are saved immediately
        // when modified. This method just updates the UI state.

        $this->isDirty     = false;
        $this->lastSavedAt = now()->format( 'g:i A');

        // Dispatch events - Alpine will handle resetting isSaving asynchronously
        $this->dispatch( 'form-saved');
        $this->dispatch( 'save-complete');
    }

    // =========================================
    // Render
    // =========================================

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view( 'forms::livewire.form-builder');
    }

    /**
     * Mark the form as having unsaved changes.
     */
    protected function markDirty(): void
    {
        $this->isDirty = true;

        // Trigger auto-save after a delay
        $this->dispatch( 'auto-save-trigger');
    }
}
