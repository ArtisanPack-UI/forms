<?php

/**
 * Form builder Livewire component.
 *
 * Provides a drag-and-drop interface for building forms with:
 * - Field palette for adding new fields
 * - Canvas for arranging fields
 * - Field editor sidebar for configuring selected fields
 * - Multi-step form support
 * - Auto-save with dirty state tracking
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
 * Form builder Livewire component class.
 *
 * Provides a drag-and-drop interface for building forms with:
 * - Field palette for adding new fields
 * - Canvas for arranging fields
 * - Field editor sidebar for configuring selected fields
 * - Multi-step form support
 * - Auto-save with dirty state tracking
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormBuilder extends Component
{
    /**
     * The `field_config` settings updateFieldConfig() may change, by field type.
     *
     * @since 1.6.0
     *
     * @var array<string, list<string>>
     */
    protected const CONFIG_SETTINGS = [
        'textarea' => ['rows'],
        'heading'  => ['level'],
        'divider'  => ['style', 'spacing'],
        'time'     => ['step'],
        'checkbox' => ['checked_label', 'unchecked_label'],
        'toggle'   => ['checked_label', 'unchecked_label'],
    ];

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
     * Reads the filtered categories so a package that registered a custom type
     * through `ap.forms.fieldTypes` and filed it under a category through
     * `ap.forms.fieldCategories` has its button appear in the builder palette.
     * Reading the raw categories would silently drop every such type, since the
     * palette is driven by the category-to-types map rather than the type list.
     *
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    #[Computed]
    public function fieldCategories(): array
    {
        $categories = [];

        foreach ( FieldTypes::getCategoriesFiltered() as $key => $category ) {
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
     * Update a numeric validation rule (min, max or step) on the selected field.
     *
     * The raw input value is parsed here rather than in the `wire:change`
     * expression: Livewire 4 rewrites bare identifiers such as `parseInt` in
     * those expressions to `$wire` calls, which breaks the request.
     *
     * @since 1.6.0
     *
     * @param  string  $rule  The rule key: `min`, `max` or `step`.
     * @param  float|int|string|null  $value  The raw input value; blank clears the rule.
     */
    public function updateValidationRule( string $rule, string|int|float|null $value ): void
    {
        if ( ! in_array( $rule, ['min', 'max', 'step'], true ) ) {
            return;
        }

        $field = $this->getSelectedFieldModel();

        if ( null === $field ) {
            return;
        }

        $rules = $field->validation_rules ?? [];

        if ( is_numeric( $value ) ) {
            $rules[ $rule ] = 'step' === $rule ? (float) $value : (int) $value;
        } else {
            unset( $rules[ $rule ] );
        }

        $this->persistSelectedField( $field, ['validation_rules' => $rules] );
    }

    /**
     * Update a file field's max size or allowed types on the selected field.
     *
     * Saves to `field_config` and drops any pre-1.6.0 copy in
     * `validation_rules` so it no longer shadows the new value.
     *
     * @since 1.6.0
     *
     * @param  string  $setting  `max_size` (KB) or `allowed_types` (comma-separated extensions).
     * @param  int|string|null  $value  The raw input value; blank clears the setting.
     */
    public function updateFileSetting( string $setting, string|int|null $value ): void
    {
        if ( ! in_array( $setting, ['max_size', 'allowed_types'], true ) ) {
            return;
        }

        $field = $this->getSelectedFieldModel();

        if ( null === $field ) {
            return;
        }

        $config = $field->field_config ?? [];
        $rules  = $field->validation_rules ?? [];

        $parsed = 'max_size' === $setting
            ? ( is_numeric( $value ) && (int) $value > 0 ? (int) $value : null )
            : array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), fn ( string $type ): bool => '' !== $type ) );

        if ( null === $parsed || [] === $parsed ) {
            unset( $config[ $setting ] );
        } else {
            $config[ $setting ] = $parsed;
        }

        unset( $rules[ $setting ] );

        $this->persistSelectedField( $field, [
            'field_config'     => $config,
            'validation_rules' => $rules,
        ] );
    }

    /**
     * Update a type-specific display setting in the selected field's `field_config`.
     *
     * Covers textarea `rows`, heading `level`, divider `style` / `spacing`,
     * time `step` (seconds) and checkbox / toggle `checked_label` /
     * `unchecked_label`. Settings that do not apply to the field's type are
     * ignored; a blank or invalid value clears the setting so the renderer
     * falls back to its default.
     *
     * @since 1.6.0
     *
     * @param  string  $setting  The `field_config` key.
     * @param  int|string|null  $value  The raw input value.
     */
    public function updateFieldConfig( string $setting, string|int|null $value ): void
    {
        $field = $this->getSelectedFieldModel();

        if ( null === $field || ! in_array( $setting, self::CONFIG_SETTINGS[ $field->type ] ?? [], true ) ) {
            return;
        }

        $config = $field->field_config ?? [];
        $parsed = $this->parseFieldConfigSetting( $setting, $value );

        if ( null === $parsed ) {
            unset( $config[ $setting ] );
        } else {
            $config[ $setting ] = $parsed;
        }

        $this->persistSelectedField( $field, ['field_config' => $config] );
    }

    /**
     * Update the earliest or latest allowed time on the selected time field.
     *
     * @since 1.6.0
     *
     * @param  string  $rule  `min_time` or `max_time`.
     * @param  string|null  $value  An `H:i` or `H:i:s` time; blank or invalid clears the rule.
     */
    public function updateTimeLimit( string $rule, ?string $value ): void
    {
        if ( ! in_array( $rule, ['min_time', 'max_time'], true ) ) {
            return;
        }

        $field = $this->getSelectedFieldModel();

        if ( null === $field || 'time' !== $field->type ) {
            return;
        }

        $rules = $field->validation_rules ?? [];
        $value = trim( (string) $value );

        if ( 1 === preg_match( FormField::TIME_FORMAT_PATTERN, $value ) ) {
            $rules[ $rule ] = FormField::normalizeTimeValue( $value );
        } else {
            unset( $rules[ $rule ] );
        }

        $this->persistSelectedField( $field, ['validation_rules' => $rules] );
    }

    /**
     * Update the content of the selected heading, paragraph or HTML field.
     *
     * Applies the layout storage contract (heading → `label`, paragraph and
     * HTML → `field_config.content`) and clears the legacy `default_value` copy.
     *
     * @since 1.6.0
     *
     * @param  string|null  $content  The new content.
     */
    public function updateLayoutContent( ?string $content ): void
    {
        $field = $this->getSelectedFieldModel();

        if ( null === $field || ! in_array( $field->type, ['heading', 'paragraph', 'html'], true ) ) {
            return;
        }

        $content = $content ?? '';

        $data = 'heading' === $field->type
            ? ['label' => $content]
            : ['field_config' => array_merge( $field->field_config ?? [], ['content' => $content] )];

        $this->persistSelectedField( $field, $data + ['default_value' => null] );
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
        $this->lastSavedAt = now()->format( 'g:i A' );

        // Dispatch events - Alpine will handle resetting isSaving asynchronously
        $this->dispatch( 'form-saved' );
        $this->dispatch( 'save-complete' );
    }

    // =========================================
    // Render
    // =========================================

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view( 'forms::livewire.form-builder' );
    }

    /**
     * Mark the form as having unsaved changes.
     */
    protected function markDirty(): void
    {
        $this->isDirty = true;

        // Trigger auto-save after a delay
        $this->dispatch( 'auto-save-trigger' );
    }

    /**
     * Get the selected field model, if a field is selected.
     *
     * @since 1.6.0
     */
    protected function getSelectedFieldModel(): ?FormField
    {
        if ( null === $this->selectedFieldId ) {
            return null;
        }

        return $this->fieldService->getByUuid( $this->form, $this->selectedFieldId );
    }

    /**
     * Save changes to the selected field and refresh the editor state.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The selected field.
     * @param  array<string, mixed>  $data  The attributes to update.
     */
    protected function persistSelectedField( FormField $field, array $data ): void
    {
        $this->fieldService->update( $field, $data );
        $this->markDirty();

        unset( $this->fields, $this->selectedField );
    }

    /**
     * Parse a raw `field_config` setting value from the field editor.
     *
     * @since 1.6.0
     *
     * @param  string  $setting  The `field_config` key.
     * @param  int|string|null  $value  The raw input value.
     *
     * @return int|string|null The value to store, or null to clear the setting.
     */
    protected function parseFieldConfigSetting( string $setting, string|int|null $value ): int|string|null
    {
        $value = trim( (string) $value );

        return match ( $setting ) {
            'rows'    => ctype_digit( $value ) && (int) $value >= 1 && (int) $value <= 50 ? (int) $value : null,
            'step'    => ctype_digit( $value ) && (int) $value >= 1 ? (int) $value : null,
            'level'   => in_array( $value, FormField::HEADING_LEVELS, true ) ? $value : null,
            'style'   => in_array( $value, FormField::DIVIDER_STYLES, true ) ? $value : null,
            'spacing' => in_array( $value, FormField::DIVIDER_SPACINGS, true ) ? $value : null,
            default   => '' === $value ? null : mb_substr( $value, 0, 255 ),
        };
    }
}
