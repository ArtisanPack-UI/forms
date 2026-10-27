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
use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\FieldService;
use ArtisanPackUI\Forms\Services\StepService;
use ArtisanPackUI\Forms\Support\ChoiceImages;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\PredefinedChoices;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

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
 * Since 1.6.0, mounting the builder and every action that changes the form
 * require the `update` ability on the form (see FormPolicy), so a user who
 * reaches the builder for a form they may not edit gets a 403 rather than
 * relying on the route's middleware alone.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormBuilder extends Component
{
    use WithFileUploads;

    /**
     * The attributes updateField() may change.
     *
     * The field's type, form, step, order, validation rules and conditional
     * logic are changed through their own actions, which check what they are
     * given.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const EDITABLE_FIELD_ATTRIBUTES = [
        'label',
        'name',
        'placeholder',
        'help_text',
        'is_required',
        'default_value',
        'width',
        'css_classes',
        'field_config',
    ];

    /**
     * The attributes updateStep() may change.
     *
     * A step's form and order are changed only by the step actions.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const EDITABLE_STEP_ATTRIBUTES = [
        'title',
        'description',
        'prev_button_text',
        'next_button_text',
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
     *
     * Locked (since 1.6.0): it is changed only through selectStep() and
     * the step actions, which check the step belongs to the form.
     */
    #[Locked]
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
     * The image being uploaded for an image choice (since 1.6.0; see storeChoiceImage()).
     */
    public ?TemporaryUploadedFile $choiceImage = null;

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
     *
     * @throws AuthorizationException When the user may not update the form (since 1.6.0).
     */
    public function mount( Form $form ): void
    {
        $this->authorize( 'update', $form );

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function addField( string $type ): void
    {
        $this->authorizeUpdate();

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
     * Since 1.6.0, only the attributes in EDITABLE_FIELD_ATTRIBUTES are
     * changed (any others are ignored) and they are validated first.
     *
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException When the user may not update the form.
     * @throws ValidationException When an attribute is not valid.
     */
    public function updateField( array $data ): void
    {
        $this->authorizeUpdate();

        if ( null === $this->selectedFieldId ) {
            return;
        }

        $data = array_intersect_key( $data, array_flip( self::EDITABLE_FIELD_ATTRIBUTES ) );

        if ( [] === $data ) {
            return;
        }

        $data = Validator::make( $data, [
            'label'         => ['nullable', 'string', 'max:255'],
            'name'          => ['sometimes', 'required', 'string', 'max:100'],
            'placeholder'   => ['nullable', 'string', 'max:255'],
            'help_text'     => ['nullable', 'string', 'max:1000'],
            'is_required'   => ['sometimes', 'boolean'],
            'default_value' => ['nullable', 'string', 'max:1000'],
            'width'         => ['sometimes', 'string', Rule::in( array_keys( FieldTypes::WIDTH_CLASSES ) )],
            'css_classes'   => ['nullable', 'string', 'max:500'],
            'field_config'  => ['nullable', 'array'],
        ] )->validate();

        $field = $this->fieldService->getByUuid( $this->form, $this->selectedFieldId );

        if ( $field ) {
            $this->fieldService->update( $field, $data );
            $this->markDirty();

            unset( $this->fields, $this->selectedField );
        }
    }

    /**
     * Update one of the selected field's type-specific settings.
     *
     * The setting must be in the field type's settings schema (see
     * FieldSettings); its schema entry decides how the raw input is parsed and
     * where it is stored. A blank or invalid value clears the setting so the
     * renderer falls back to its default. The value is parsed here rather than
     * in the `wire:change` expression: Livewire 4 rewrites bare identifiers
     * such as `parseInt` in those expressions to `$wire` calls.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The raw input value.
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function updateSetting( string $key, mixed $value ): void
    {
        $this->authorizeUpdate();

        $field = $this->getSelectedFieldModel();

        if ( null === $field ) {
            return;
        }

        $setting = FieldSettings::find( $field, $key );

        if ( null === $setting ) {
            return;
        }

        $this->persistSelectedField( $field, FieldSettings::update( $field, $setting, $value ) );
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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function updateLayoutContent( ?string $content ): void
    {
        $this->authorizeUpdate();

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
     * Stores the image uploaded for an image choice and gets its URL.
     *
     * The options editor uploads the image to `choiceImage` first, then puts
     * the URL this returns in the option's `image` (since 1.6.0; see
     * ChoiceImages).
     *
     * @since 1.6.0
     *
     * @throws AuthorizationException When the user may not update the form.
     *
     * @return string|null The image's URL, or null when it was not valid or could not be stored.
     */
    public function storeChoiceImage(): ?string
    {
        $this->authorizeUpdate();

        $this->validate( ['choiceImage' => ChoiceImages::uploadRules()] );

        $url = $this->choiceImage instanceof TemporaryUploadedFile
            ? app( ChoiceImages::class )->store( $this->choiceImage, $this->form )
            : null;

        $this->reset( 'choiceImage' );

        return $url;
    }

    /**
     * Replace or extend the selected choice field's options from bulk text.
     *
     * The text holds one `value|label` (or `label`) per line, as the bulk
     * editor and its predefined lists write it (see PredefinedChoices).
     *
     * @since 1.6.0
     *
     * @param  string  $text  The bulk text.
     * @param  string  $mode  `replace` the options, or `append` to them.
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function applyBulkChoices( string $text, string $mode = 'replace' ): void
    {
        $this->authorizeUpdate();

        $field = $this->getSelectedFieldModel();

        if ( null === $field || ! FieldTypes::hasOptions( $field->type ) ) {
            return;
        }

        $options = PredefinedChoices::apply(
            $field->getConfig( 'options', [] ),
            PredefinedChoices::parseBulk( $text ),
            'append' === $mode ? 'append' : 'replace',
        );

        $this->persistSelectedField( $field, ['field_config' => array_merge( $field->field_config ?? [], ['options' => $options] )] );
    }

    /**
     * Change one setting of one of the selected composite field's sub-inputs.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The sub-input key.
     * @param  string  $setting  One of CompositeFieldType::SUB_INPUT_SETTINGS.
     * @param  mixed  $value  The raw input value.
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function updateSubInput( string $key, string $setting, mixed $value ): void
    {
        $this->authorizeUpdate();

        $field = $this->getSelectedFieldModel();

        if ( null === $field ) {
            return;
        }

        $config = CompositeFieldType::updateSubInputConfig( $field, $key, $setting, $value );

        if ( null === $config ) {
            unset( $this->selectedField );

            return;
        }

        $this->persistSelectedField( $field, ['field_config' => $config] );
    }

    /**
     * Update conditional logic for the selected field.
     *
     * @param  array<string, mixed>  $logic
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function updateConditionalLogic( array $logic ): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function clearConditionalLogic(): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function deleteField( string $uuid ): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function duplicateField( string $uuid ): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    #[On( 'fields-reordered' )]
    public function reorderFields( array $orderedUuids ): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function addStep(): void
    {
        $this->authorizeUpdate();

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
     *
     * Since 1.6.0, a step of another form is ignored.
     */
    public function selectStep( int $stepId ): void
    {
        if ( ! $this->form->is_multi_step || null === $this->stepService->getById( $this->form, $stepId ) ) {
            return;
        }

        $this->activeStepId    = $stepId;
        $this->selectedFieldId = null;

        unset( $this->fields, $this->selectedField );
    }

    /**
     * Update a step.
     *
     * Since 1.6.0, only the attributes in EDITABLE_STEP_ATTRIBUTES are
     * changed (any others are ignored), validated like the step API.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException When the user may not update the form.
     * @throws ValidationException When an attribute is not valid.
     */
    public function updateStep( int $stepId, array $data ): void
    {
        $this->authorizeUpdate();

        $data = array_intersect_key( $data, array_flip( self::EDITABLE_STEP_ATTRIBUTES ) );

        if ( [] === $data ) {
            return;
        }

        $data = Validator::make( $data, [
            'title'            => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'prev_button_text' => ['nullable', 'string', 'max:100'],
            'next_button_text' => ['nullable', 'string', 'max:100'],
        ] )->validate();

        $step = $this->stepService->getById( $this->form, $stepId );

        if ( $step ) {
            $this->stepService->update( $step, $data );
            $this->markDirty();

            unset( $this->steps );
        }
    }

    /**
     * Delete a step.
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function deleteStep( int $stepId ): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    #[On( 'steps-reordered' )]
    public function reorderSteps( array $orderedIds ): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function enableMultiStep(): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function disableMultiStep(): void
    {
        $this->authorizeUpdate();

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
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    public function saveForm(): void
    {
        $this->authorizeUpdate();

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
     * Checks that the current user may update the form being built.
     *
     * Every action that changes the form calls this, since a Livewire action
     * is a request of its own that the route's middleware never sees.
     *
     * @since 1.6.0
     *
     * @throws AuthorizationException When the user may not update the form.
     */
    protected function authorizeUpdate(): void
    {
        $this->authorize( 'update', $this->form );
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
}
