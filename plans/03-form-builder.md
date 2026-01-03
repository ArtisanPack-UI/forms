# Form Builder

**Purpose:** Define the admin form builder interface, including the Livewire components, drag-and-drop functionality, and field editor UI.

---

## Overview

The form builder is the primary admin interface for creating and editing forms. It uses the `@artisanpack-ui/livewire-drag-and-drop` package for accessible, keyboard-navigable drag-and-drop functionality. It follows a visual, drag-and-drop approach similar to popular form builders like Gravity Forms, allowing users to:

- Add fields by dragging from a field type palette
- Reorder fields via drag-and-drop
- Configure field settings in a sidebar panel
- Preview forms in real-time
- Manage multi-step forms with step tabs
- Configure notifications
- Clone existing forms

---

## Component Architecture

```
FormBuilder (Main Livewire Component)
├── FormBuilderHeader
│   ├── Form Name Input
│   ├── Save Button
│   ├── Preview Button
│   └── Settings Dropdown
├── FormBuilderTabs (if multi-step)
│   ├── Step Tab (for each step)
│   └── Add Step Button
├── FormBuilderCanvas
│   ├── FieldWrapper (for each field)
│   │   ├── Field Preview
│   │   ├── Field Controls (edit, duplicate, delete)
│   │   └── Drag Handle
│   └── AddFieldPlaceholder
├── FormBuilderSidebar
│   ├── FieldTypePalette (when no field selected)
│   │   └── FieldTypeButton (for each type)
│   ├── FieldEditor (when field selected)
│   │   ├── General Tab
│   │   ├── Validation Tab
│   │   ├── Advanced Tab
│   │   └── Conditional Tab
│   └── StepEditor (when step selected)
└── FormBuilderFooter
    ├── Form Settings Link
    └── Notifications Link
```

---

## Main Component: FormBuilder

```php
<?php

namespace ArtisanPackUI\Forms\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Support\Str;

class FormBuilder extends Component
{
    // =========================================
    // Properties
    // =========================================

    public Form $form;
    public ?FormField $selectedField = null;
    public ?FormStep $selectedStep = null;
    public ?int $activeStepId = null;
    public string $sidebarView = 'palette'; // palette, field-editor, step-editor

    public array $formData = [
        'name' => '',
        'slug' => '',
        'description' => '',
        'submit_button_text' => 'Submit',
        'success_message' => 'Thank you for your submission!',
        'redirect_url' => '',
        'is_multi_step' => false,
        'show_progress_bar' => true,
        'allow_step_navigation' => false,
    ];

    // =========================================
    // Lifecycle
    // =========================================

    public function mount(?Form $form = null): void
    {
        if ($form && $form->exists) {
            $this->form = $form->load(['fields', 'steps.fields', 'notifications']);
            $this->formData = $form->only([
                'name', 'slug', 'description', 'submit_button_text',
                'success_message', 'redirect_url', 'is_multi_step',
                'show_progress_bar', 'allow_step_navigation'
            ]);

            if ($form->is_multi_step && $form->steps->isNotEmpty()) {
                $this->activeStepId = $form->steps->first()->id;
            }
        } else {
            $this->form = new Form();
        }
    }

    // =========================================
    // Computed Properties
    // =========================================

    #[Computed]
    public function fields(): Collection
    {
        if ($this->form->is_multi_step && $this->activeStepId) {
            return $this->form->fields()
                ->where('step_id', $this->activeStepId)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->form->fields()
            ->whereNull('step_id')
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function fieldTypes(): array
    {
        return config('forms.field_types', []);
    }

    #[Computed]
    public function fieldTypeCategories(): array
    {
        return [
            'basic' => ['text', 'email', 'phone', 'textarea', 'number', 'url'],
            'choice' => ['select', 'radio', 'checkbox', 'checkbox_group'],
            'advanced' => ['date', 'time', 'file', 'hidden'],
            'layout' => ['heading', 'paragraph', 'divider', 'html'],
        ];
    }

    // =========================================
    // Field Operations
    // =========================================

    public function addField(string $type, ?int $afterFieldId = null): void
    {
        $fieldConfig = $this->fieldTypes[$type] ?? [];

        $maxOrder = $this->form->fields()
            ->when($this->activeStepId, fn($q) => $q->where('step_id', $this->activeStepId))
            ->max('sort_order') ?? 0;

        $field = $this->form->fields()->create([
            'step_id' => $this->activeStepId,
            'uuid' => Str::uuid()->toString(),
            'name' => $this->generateFieldName($type),
            'type' => $type,
            'label' => $fieldConfig['default_label'] ?? ucfirst(str_replace('_', ' ', $type)),
            'is_required' => false,
            'sort_order' => $maxOrder + 1,
            'field_config' => $fieldConfig['default_config'] ?? [],
        ]);

        // If inserting after a specific field, reorder
        if ($afterFieldId) {
            $this->insertFieldAfter($field, $afterFieldId);
        }

        $this->selectField($field);
        $this->form->refresh();

        $this->dispatch('field-added', fieldId: $field->id);
    }

    protected function generateFieldName(string $type): string
    {
        $baseName = Str::snake($type);
        $count = $this->form->fields()->where('name', 'like', "{$baseName}%")->count();

        return $count > 0 ? "{$baseName}_" . ($count + 1) : $baseName;
    }

    public function selectField(FormField $field): void
    {
        $this->selectedField = $field;
        $this->selectedStep = null;
        $this->sidebarView = 'field-editor';
    }

    public function deselectField(): void
    {
        $this->selectedField = null;
        $this->sidebarView = 'palette';
    }

    public function updateField(int $fieldId, array $data): void
    {
        $field = FormField::findOrFail($fieldId);
        $field->update($data);

        if ($this->selectedField?->id === $fieldId) {
            $this->selectedField->refresh();
        }

        $this->form->refresh();
    }

    public function duplicateField(int $fieldId): void
    {
        $original = FormField::findOrFail($fieldId);
        $clone = $original->replicate();
        $clone->uuid = Str::uuid()->toString();
        $clone->name = $this->generateFieldName($original->type);
        $clone->label = $original->label . ' (Copy)';
        $clone->sort_order = $original->sort_order + 1;
        $clone->save();

        // Reorder subsequent fields
        $this->form->fields()
            ->where('sort_order', '>=', $clone->sort_order)
            ->where('id', '!=', $clone->id)
            ->increment('sort_order');

        $this->selectField($clone);
        $this->form->refresh();
    }

    public function deleteField(int $fieldId): void
    {
        $field = FormField::findOrFail($fieldId);

        if ($this->selectedField?->id === $fieldId) {
            $this->deselectField();
        }

        $field->delete();
        $this->form->refresh();
    }

    public function reorderFields(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $fieldId) {
            FormField::where('id', $fieldId)->update(['sort_order' => $index]);
        }

        $this->form->refresh();
    }

    // =========================================
    // Step Operations
    // =========================================

    public function enableMultiStep(): void
    {
        $this->formData['is_multi_step'] = true;

        // Create first step if none exist
        if ($this->form->exists && $this->form->steps()->count() === 0) {
            $step = $this->form->steps()->create([
                'title' => 'Step 1',
                'sort_order' => 0,
            ]);

            // Move all existing fields to this step
            $this->form->fields()->update(['step_id' => $step->id]);

            $this->activeStepId = $step->id;
        }

        $this->form->refresh();
    }

    public function disableMultiStep(): void
    {
        $this->formData['is_multi_step'] = false;

        // Remove step associations from all fields
        $this->form->fields()->update(['step_id' => null]);

        // Delete all steps
        $this->form->steps()->delete();

        $this->activeStepId = null;
        $this->form->refresh();
    }

    public function addStep(): void
    {
        $maxOrder = $this->form->steps()->max('sort_order') ?? -1;
        $stepNumber = $this->form->steps()->count() + 1;

        $step = $this->form->steps()->create([
            'title' => "Step {$stepNumber}",
            'sort_order' => $maxOrder + 1,
        ]);

        $this->activeStepId = $step->id;
        $this->form->refresh();
    }

    public function selectStep(int $stepId): void
    {
        $this->activeStepId = $stepId;
        $this->selectedStep = FormStep::find($stepId);
        $this->selectedField = null;
        $this->sidebarView = 'step-editor';
    }

    public function updateStep(int $stepId, array $data): void
    {
        FormStep::where('id', $stepId)->update($data);

        if ($this->selectedStep?->id === $stepId) {
            $this->selectedStep->refresh();
        }

        $this->form->refresh();
    }

    public function deleteStep(int $stepId): void
    {
        $step = FormStep::findOrFail($stepId);

        // Move fields to previous step or make them step-less
        $previousStep = $this->form->steps()
            ->where('sort_order', '<', $step->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        $step->fields()->update([
            'step_id' => $previousStep?->id,
        ]);

        $step->delete();

        // Update active step
        if ($this->activeStepId === $stepId) {
            $this->activeStepId = $previousStep?->id
                ?? $this->form->steps()->first()?->id;
        }

        $this->form->refresh();

        // If no steps remain, disable multi-step
        if ($this->form->steps()->count() === 0) {
            $this->disableMultiStep();
        }
    }

    public function reorderSteps(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $stepId) {
            FormStep::where('id', $stepId)->update(['sort_order' => $index]);
        }

        $this->form->refresh();
    }

    // =========================================
    // Form Operations
    // =========================================

    public function save(): void
    {
        $this->validate([
            'formData.name' => 'required|string|max:255',
            'formData.slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('forms', 'slug')->ignore($this->form->id),
            ],
        ]);

        $this->form->fill($this->formData);
        $this->form->save();

        $this->dispatch('form-saved');
        $this->dispatch('notify', message: 'Form saved successfully');
    }

    public function duplicate(): void
    {
        $clone = $this->form->duplicate();

        $this->redirect(route('forms.edit', $clone));
    }

    public function updatedFormDataName(): void
    {
        if (empty($this->formData['slug']) || !$this->form->exists) {
            $this->formData['slug'] = Str::slug($this->formData['name']);
        }
    }

    // =========================================
    // Render
    // =========================================

    public function render()
    {
        return view('forms::admin.form-builder')
            ->layout($this->getLayout());
    }

    protected function getLayout(): string
    {
        // Check if cms-framework is installed
        if (class_exists(\ArtisanPackUI\CmsFramework\CmsFrameworkServiceProvider::class)) {
            return 'cms::layouts.admin';
        }

        return 'forms::layouts.admin';
    }
}
```

---

## Form Builder View

```blade
{{-- resources/views/admin/form-builder.blade.php --}}

<div class="flex h-screen bg-base-200">
    {{-- Main Content Area --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Header --}}
        <header class="bg-base-100 border-b border-base-300 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('forms.index') }}" class="btn btn-ghost btn-sm">
                        <x-artisanpack-icon name="o-arrow-left" class="w-4 h-4" />
                    </a>
                    <x-artisanpack-input
                        wire:model.blur="formData.name"
                        placeholder="Form Name"
                        class="text-lg font-semibold border-0 bg-transparent focus:bg-base-200 rounded px-2"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <x-artisanpack-button
                        wire:click="save"
                        color="primary"
                    >
                        <x-artisanpack-icon name="o-check" class="w-4 h-4" />
                        Save
                    </x-artisanpack-button>
                    <div class="dropdown dropdown-end">
                        <x-artisanpack-button tabindex="0" color="ghost">
                            <x-artisanpack-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                        </x-artisanpack-button>
                        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box w-52 shadow-lg">
                            <li><a href="{{ route('forms.preview', $form) }}" target="_blank">Preview</a></li>
                            <li><a wire:click.prevent="duplicate">Duplicate</a></li>
                            <li><a href="{{ route('forms.settings', $form) }}">Settings</a></li>
                            <li><a href="{{ route('forms.notifications', $form) }}">Notifications</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        {{-- Multi-Step Tabs --}}
        @if($this->formData['is_multi_step'])
            <div class="bg-base-100 border-b border-base-300 px-6">
                {{-- Uses @artisanpack-ui/livewire-drag-and-drop for accessible reordering --}}
                <div
                    class="flex items-center gap-2 py-2"
                    x-drag-context
                    @drag:end="$wire.reorderSteps($event.detail.orderedIds)"
                    role="tablist"
                    aria-label="{{ __('Form Steps') }}"
                >
                    @foreach($form->steps as $step)
                        <div
                            x-drag-item="{{ json_encode(['id' => $step->id]) }}"
                            wire:key="step-{{ $step->id }}"
                            class="flex items-center gap-1"
                            role="tab"
                            aria-selected="{{ $activeStepId === $step->id ? 'true' : 'false' }}"
                        >
                            <button
                                wire:click="selectStep({{ $step->id }})"
                                class="tab {{ $activeStepId === $step->id ? 'tab-active' : '' }}"
                            >
                                <span class="step-drag-handle cursor-move mr-1" aria-hidden="true">
                                    <x-artisanpack-icon name="o-bars-2" class="w-3 h-3" />
                                </span>
                                {{ $step->title ?: __('Step') . ' ' . $loop->iteration }}
                            </button>
                        </div>
                    @endforeach
                    <button
                        wire:click="addStep"
                        class="btn btn-ghost btn-sm"
                    >
                        <x-artisanpack-icon name="o-plus" class="w-4 h-4" />
                        {{ __('Add Step') }}
                    </button>
                </div>
            </div>
        @endif

        {{-- Canvas --}}
        <div class="flex-1 overflow-auto p-6">
            <div class="max-w-2xl mx-auto">
                {{-- Fields List - Uses @artisanpack-ui/livewire-drag-and-drop --}}
                <div
                    class="space-y-3 min-h-[200px]"
                    x-drag-context
                    @drag:end="$wire.reorderFields($event.detail.orderedIds)"
                    role="list"
                    aria-label="{{ __('Form Fields') }}"
                >
                    @forelse($this->fields as $field)
                        <div
                            x-drag-item="{{ json_encode(['id' => $field->id, 'label' => $field->label]) }}"
                            wire:key="field-{{ $field->id }}"
                            class="group bg-base-100 rounded-lg border-2 transition-all cursor-pointer
                                   {{ $selectedField?->id === $field->id ? 'border-primary' : 'border-base-300 hover:border-base-content/20' }}"
                            wire:click="selectField({{ $field->id }})"
                            role="listitem"
                        >
                            <div class="flex items-start gap-3 p-4">
                                {{-- Drag Handle --}}
                                <div class="field-drag-handle cursor-move opacity-0 group-hover:opacity-100 transition pt-1" aria-hidden="true">
                                    <x-artisanpack-icon name="o-bars-3" class="w-4 h-4 text-base-content/50" />
                                </div>

                                {{-- Field Preview --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-medium">{{ $field->label }}</span>
                                        @if($field->is_required)
                                            <span class="text-error" aria-label="{{ __('Required') }}">*</span>
                                        @endif
                                        @if($field->has_conditional_logic)
                                            <x-artisanpack-icon name="o-funnel" class="w-3 h-3 text-warning" title="{{ __('Has conditions') }}" />
                                        @endif
                                    </div>
                                    <div class="text-xs text-base-content/60">
                                        {{ ucfirst(str_replace('_', ' ', $field->type)) }}
                                        @if($field->name)
                                            &middot; {{ $field->name }}
                                        @endif
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                    <button
                                        wire:click.stop="duplicateField({{ $field->id }})"
                                        class="btn btn-ghost btn-xs"
                                        title="{{ __('Duplicate') }}"
                                    >
                                        <x-artisanpack-icon name="o-document-duplicate" class="w-4 h-4" />
                                    </button>
                                    <button
                                        wire:click.stop="deleteField({{ $field->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this field?') }}"
                                        class="btn btn-ghost btn-xs text-error"
                                        title="{{ __('Delete') }}"
                                    >
                                        <x-artisanpack-icon name="o-trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            {{-- Screen reader instructions --}}
                            <div class="sr-only">
                                {{ __('Use arrow keys to reorder, Space or Enter to drop') }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-base-content/50">
                            <x-artisanpack-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3" />
                            <p>{{ __('No fields yet') }}</p>
                            <p class="text-sm">{{ __('Drag fields from the sidebar to get started') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <aside class="w-80 bg-base-100 border-l border-base-300 flex flex-col overflow-hidden">
        @if($sidebarView === 'palette')
            {{-- Field Type Palette --}}
            @include('forms::admin.partials.field-palette')
        @elseif($sidebarView === 'field-editor' && $selectedField)
            {{-- Field Editor --}}
            <livewire:forms::field-editor
                :field="$selectedField"
                :key="'field-editor-' . $selectedField->id"
            />
        @elseif($sidebarView === 'step-editor' && $selectedStep)
            {{-- Step Editor --}}
            @include('forms::admin.partials.step-editor')
        @endif
    </aside>
</div>
```

---

## Field Type Palette

```blade
{{-- resources/views/admin/partials/field-palette.blade.php --}}

<div class="p-4 flex-1 overflow-auto">
    <h3 class="font-semibold mb-4">{{ __('Add Field') }}</h3>

    @foreach($this->fieldTypeCategories as $category => $types)
        <div class="mb-6">
            <h4 class="text-xs uppercase tracking-wider text-base-content/50 mb-2">
                {{ __(ucfirst($category)) }}
            </h4>
            <div class="grid grid-cols-2 gap-2">
                @foreach($types as $type)
                    @if(isset($this->fieldTypes[$type]))
                        @php $config = $this->fieldTypes[$type]; @endphp
                        <button
                            wire:click="addField('{{ $type }}')"
                            class="flex flex-col items-center gap-1 p-3 rounded-lg border border-base-300
                                   hover:border-primary hover:bg-primary/5 transition text-center"
                        >
                            <x-artisanpack-icon :name="$config['icon'] ?? 'o-square-3-stack-3d'" class="w-5 h-5" />
                            <span class="text-xs">{{ $config['label'] }}</span>
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Multi-Step Toggle --}}
    <div class="border-t border-base-300 pt-4 mt-4">
        <label class="flex items-center justify-between cursor-pointer">
            <span class="text-sm">{{ __('Multi-Step Form') }}</span>
            <input
                type="checkbox"
                class="toggle toggle-primary toggle-sm"
                wire:model.live="formData.is_multi_step"
                @change="$wire.{{ $formData['is_multi_step'] ? 'disableMultiStep' : 'enableMultiStep' }}()"
            />
        </label>
    </div>
</div>
```

---

## Field Editor Component

```php
<?php

namespace ArtisanPackUI\Forms\Http\Livewire;

use Livewire\Component;
use ArtisanPackUI\Forms\Models\FormField;

class FieldEditor extends Component
{
    public FormField $field;
    public string $activeTab = 'general';

    public array $fieldData = [];

    protected $listeners = [
        'refreshField' => '$refresh',
    ];

    public function mount(FormField $field): void
    {
        $this->field = $field;
        $this->fieldData = $field->toArray();
    }

    public function updatedFieldData(): void
    {
        $this->field->update($this->fieldData);
        $this->dispatch('fieldUpdated', fieldId: $this->field->id);
    }

    public function addOption(): void
    {
        $options = $this->fieldData['field_config']['options'] ?? [];
        $options[] = [
            'value' => 'option_' . (count($options) + 1),
            'label' => 'Option ' . (count($options) + 1),
        ];

        $this->fieldData['field_config']['options'] = $options;
        $this->updatedFieldData();
    }

    public function removeOption(int $index): void
    {
        $options = $this->fieldData['field_config']['options'] ?? [];
        unset($options[$index]);
        $this->fieldData['field_config']['options'] = array_values($options);
        $this->updatedFieldData();
    }

    public function render()
    {
        return view('forms::admin.field-editor');
    }
}
```

---

## Field Editor View

```blade
{{-- resources/views/admin/field-editor.blade.php --}}

<div class="flex flex-col h-full">
    {{-- Header --}}
    <div class="p-4 border-b border-base-300 flex items-center justify-between">
        <h3 class="font-semibold">{{ ucfirst(str_replace('_', ' ', $field->type)) }}</h3>
        <button
            wire:click="$parent.deselectField"
            class="btn btn-ghost btn-sm btn-square"
            aria-label="{{ __('Close') }}"
        >
            <x-artisanpack-icon name="o-x-mark" class="w-4 h-4" />
        </button>
    </div>

    {{-- Tabs --}}
    <div class="tabs tabs-bordered px-4" role="tablist">
        <button
            wire:click="$set('activeTab', 'general')"
            class="tab {{ $activeTab === 'general' ? 'tab-active' : '' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'general' ? 'true' : 'false' }}"
        >
            {{ __('General') }}
        </button>
        <button
            wire:click="$set('activeTab', 'validation')"
            class="tab {{ $activeTab === 'validation' ? 'tab-active' : '' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'validation' ? 'true' : 'false' }}"
        >
            {{ __('Validation') }}
        </button>
        <button
            wire:click="$set('activeTab', 'advanced')"
            class="tab {{ $activeTab === 'advanced' ? 'tab-active' : '' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'advanced' ? 'true' : 'false' }}"
        >
            {{ __('Advanced') }}
        </button>
        <button
            wire:click="$set('activeTab', 'conditional')"
            class="tab {{ $activeTab === 'conditional' ? 'tab-active' : '' }}"
            role="tab"
            aria-selected="{{ $activeTab === 'conditional' ? 'true' : 'false' }}"
        >
            {{ __('Conditions') }}
        </button>
    </div>

    {{-- Tab Content --}}
    <div class="flex-1 overflow-auto p-4">
        @if($activeTab === 'general')
            @include('forms::admin.partials.field-editor-general')
        @elseif($activeTab === 'validation')
            @include('forms::admin.partials.field-editor-validation')
        @elseif($activeTab === 'advanced')
            @include('forms::admin.partials.field-editor-advanced')
        @elseif($activeTab === 'conditional')
            @include('forms::admin.partials.field-editor-conditional')
        @endif
    </div>
</div>
```

---

## Field Editor - General Tab

```blade
{{-- resources/views/admin/partials/field-editor-general.blade.php --}}

<div class="space-y-4">
    {{-- Label --}}
    <x-artisanpack-input
        wire:model.blur="fieldData.label"
        label="{{ __('Label') }}"
        placeholder="{{ __('Enter field label') }}"
    />

    {{-- Name (Field ID) --}}
    <x-artisanpack-input
        wire:model.blur="fieldData.name"
        label="{{ __('Field Name') }}"
        hint="{{ __('Used in form data and placeholders') }}"
    />

    {{-- Placeholder --}}
    @if(in_array($field->type, ['text', 'email', 'phone', 'textarea', 'url', 'number']))
        <x-artisanpack-input
            wire:model.blur="fieldData.placeholder"
            label="{{ __('Placeholder') }}"
        />
    @endif

    {{-- Help Text --}}
    <x-artisanpack-textarea
        wire:model.blur="fieldData.help_text"
        label="{{ __('Help Text') }}"
        rows="2"
    />

    {{-- Options (for select, radio, checkbox_group) --}}
    @if(in_array($field->type, ['select', 'radio', 'checkbox_group']))
        <div>
            <label class="label">
                <span class="label-text">{{ __('Options') }}</span>
            </label>
            <div class="space-y-2">
                @foreach($fieldData['field_config']['options'] ?? [] as $index => $option)
                    <div class="flex items-center gap-2">
                        <x-artisanpack-input
                            wire:model.blur="fieldData.field_config.options.{{ $index }}.label"
                            placeholder="{{ __('Label') }}"
                            class="flex-1"
                        />
                        <x-artisanpack-input
                            wire:model.blur="fieldData.field_config.options.{{ $index }}.value"
                            placeholder="{{ __('Value') }}"
                            class="flex-1"
                        />
                        <button
                            wire:click="removeOption({{ $index }})"
                            class="btn btn-ghost btn-sm btn-square text-error"
                            aria-label="{{ __('Remove option') }}"
                        >
                            <x-artisanpack-icon name="o-minus" class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
            <button wire:click="addOption" class="btn btn-ghost btn-sm mt-2">
                <x-artisanpack-icon name="o-plus" class="w-4 h-4" />
                {{ __('Add Option') }}
            </button>
        </div>
    @endif

    {{-- Default Value --}}
    <x-artisanpack-input
        wire:model.blur="fieldData.default_value"
        label="{{ __('Default Value') }}"
    />

    {{-- Required Toggle --}}
    <label class="flex items-center justify-between cursor-pointer">
        <span class="label-text">{{ __('Required') }}</span>
        <input
            type="checkbox"
            class="toggle toggle-primary toggle-sm"
            wire:model.live="fieldData.is_required"
        />
    </label>
</div>
```

---

## Related Documents

- [04-form-renderer.md](04-form-renderer.md) - Frontend form display
- [05-field-types.md](05-field-types.md) - Available field types
- [06-conditional-logic.md](06-conditional-logic.md) - Conditional field logic
- [07-multi-step-forms.md](07-multi-step-forms.md) - Multi-step form handling
