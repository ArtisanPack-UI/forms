# Multi-Step Forms

**Purpose:** Define the multi-step/wizard form architecture, step navigation, progress indicators, and validation per step.

---

## Overview

Multi-step forms break long forms into manageable steps, improving user experience and completion rates. Features include:

- Step-by-step navigation with progress indicators
- Per-step validation before advancing
- Optional step navigation (jump to any step)
- Persistent state across steps
- Step titles and descriptions
- Customizable button text per step

---

## Data Model

### Form Level Settings

```php
// Form model properties for multi-step
[
    'is_multi_step' => true,
    'show_progress_bar' => true,
    'allow_step_navigation' => false, // Allow jumping between steps
]
```

### FormStep Model

```php
// FormStep table structure
[
    'id' => 1,
    'form_id' => 1,
    'title' => 'Personal Information',
    'description' => 'Tell us about yourself',
    'sort_order' => 0,
    'next_button_text' => 'Continue',
    'prev_button_text' => 'Back',
]
```

### Field-Step Association

Fields belong to steps via `step_id`:

```php
// FormField
[
    'form_id' => 1,
    'step_id' => 1, // NULL for non-multi-step forms
    // ... other field properties
]
```

---

## Form Builder Integration

### Enabling Multi-Step Mode

```php
public function enableMultiStep(): void
{
    $this->formData['is_multi_step'] = true;

    if ($this->form->exists && $this->form->steps()->count() === 0) {
        // Create initial step
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
```

### Step Tab UI

```blade
{{-- Step tabs in form builder --}}
<div class="bg-base-100 border-b border-base-300 px-6">
    <div
        class="flex items-center gap-2 py-2"
        x-data
        x-init="Sortable.create($el, {
            animation: 150,
            handle: '.step-drag-handle',
            onEnd: (evt) => $wire.reorderSteps(
                Array.from($el.querySelectorAll('[data-step-id]')).map(el => el.dataset.stepId)
            )
        })"
    >
        @foreach($form->steps as $step)
            <div
                data-step-id="{{ $step->id }}"
                class="flex items-center"
            >
                <button
                    wire:click="selectStep({{ $step->id }})"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg transition
                           {{ $activeStepId === $step->id
                               ? 'bg-primary text-primary-content'
                               : 'bg-base-200 hover:bg-base-300' }}"
                >
                    <span class="step-drag-handle cursor-move">
                        <x-artisanpack-icon name="o-bars-2" class="w-3 h-3" />
                    </span>
                    <span>{{ $step->title ?: 'Step ' . $loop->iteration }}</span>
                    <span class="badge badge-sm">
                        {{ $step->fields->count() }}
                    </span>
                </button>
            </div>
        @endforeach

        <button wire:click="addStep" class="btn btn-ghost btn-sm">
            <x-artisanpack-icon name="o-plus" class="w-4 h-4" />
            Add Step
        </button>
    </div>
</div>
```

### Step Editor Sidebar

```blade
{{-- Step settings when step is selected --}}
<div class="p-4 space-y-4">
    <h3 class="font-semibold">Step Settings</h3>

    <x-artisanpack-input
        wire:model.blur="stepData.title"
        label="Step Title"
        placeholder="e.g., Personal Information"
    />

    <x-artisanpack-textarea
        wire:model.blur="stepData.description"
        label="Description"
        placeholder="Optional description shown to users"
        rows="2"
    />

    <x-artisanpack-input
        wire:model.blur="stepData.next_button_text"
        label="Next Button Text"
        placeholder="Next"
    />

    <x-artisanpack-input
        wire:model.blur="stepData.prev_button_text"
        label="Previous Button Text"
        placeholder="Back"
    />

    <div class="divider"></div>

    <button
        wire:click="deleteStep({{ $selectedStep->id }})"
        wire:confirm="Are you sure? Fields in this step will be moved to the previous step."
        class="btn btn-error btn-sm w-full"
    >
        Delete Step
    </button>
</div>
```

---

## Form Renderer Navigation

### State Management

```php
class FormRenderer extends Component
{
    public int $currentStepIndex = 0;

    #[Computed]
    public function steps(): Collection
    {
        if (!$this->form->is_multi_step) {
            return collect();
        }

        return $this->form->steps()
            ->with(['fields' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function currentStep(): ?FormStep
    {
        return $this->steps[$this->currentStepIndex] ?? null;
    }

    #[Computed]
    public function totalSteps(): int
    {
        return $this->steps->count();
    }

    #[Computed]
    public function isFirstStep(): bool
    {
        return $this->currentStepIndex === 0;
    }

    #[Computed]
    public function isLastStep(): bool
    {
        return $this->currentStepIndex === $this->totalSteps - 1;
    }
}
```

### Navigation Methods

```php
public function nextStep(): void
{
    // Validate current step before proceeding
    $this->validateStep();

    if (!$this->isLastStep) {
        $this->currentStepIndex++;
        $this->dispatch('step-changed', step: $this->currentStepIndex);
    }
}

public function previousStep(): void
{
    if (!$this->isFirstStep) {
        $this->currentStepIndex--;
        $this->dispatch('step-changed', step: $this->currentStepIndex);
    }
}

public function goToStep(int $index): void
{
    // Only allowed if form allows step navigation
    if (!$this->form->allow_step_navigation) {
        return;
    }

    // Can only go to completed steps or current step
    if ($index > $this->currentStepIndex) {
        // Validate all steps up to target
        for ($i = $this->currentStepIndex; $i < $index; $i++) {
            $this->currentStepIndex = $i;
            try {
                $this->validateStep();
            } catch (ValidationException $e) {
                return; // Stop at first invalid step
            }
        }
    }

    $this->currentStepIndex = $index;
}

protected function validateStep(): void
{
    $stepFields = $this->currentStep->fields;

    $rules = [];
    foreach ($stepFields as $field) {
        // Skip hidden fields
        if (!$this->evaluateConditionalLogic($field)) {
            continue;
        }

        $rules["formData.{$field->name}"] = $field->buildValidationRules();
    }

    $this->validate($rules, $this->getCustomMessages());
}
```

---

## Progress Indicator Variants

### Numbered Steps

```blade
<div class="flex justify-between items-center mb-8">
    @foreach($this->steps as $index => $step)
        <div class="flex items-center {{ $index < $this->totalSteps - 1 ? 'flex-1' : '' }}">
            <button
                type="button"
                @if($form->allow_step_navigation && $index <= $currentStepIndex)
                    wire:click="goToStep({{ $index }})"
                @endif
                class="flex items-center justify-center w-10 h-10 rounded-full font-semibold transition
                       {{ $index < $currentStepIndex
                           ? 'bg-success text-success-content'
                           : ($index === $currentStepIndex
                               ? 'bg-primary text-primary-content ring-4 ring-primary/30'
                               : 'bg-base-300 text-base-content/50') }}
                       {{ $form->allow_step_navigation && $index <= $currentStepIndex ? 'cursor-pointer hover:scale-105' : 'cursor-default' }}"
            >
                @if($index < $currentStepIndex)
                    <x-artisanpack-icon name="o-check" class="w-5 h-5" />
                @else
                    {{ $index + 1 }}
                @endif
            </button>

            {{-- Connector line --}}
            @if($index < $this->totalSteps - 1)
                <div class="flex-1 h-1 mx-3 rounded-full {{ $index < $currentStepIndex ? 'bg-success' : 'bg-base-300' }}"></div>
            @endif
        </div>
    @endforeach
</div>

{{-- Step title --}}
<div class="text-center mb-6">
    <h2 class="text-xl font-semibold">{{ $this->currentStep->title }}</h2>
    @if($this->currentStep->description)
        <p class="text-base-content/60 mt-1">{{ $this->currentStep->description }}</p>
    @endif
</div>
```

### Progress Bar

```blade
<div class="mb-8">
    <div class="flex justify-between text-sm mb-2">
        <span>Step {{ $currentStepIndex + 1 }} of {{ $this->totalSteps }}</span>
        <span>{{ $this->progressPercentage }}% complete</span>
    </div>
    <progress
        class="progress progress-primary w-full"
        value="{{ $this->progressPercentage }}"
        max="100"
    ></progress>
    @if($this->currentStep->title)
        <p class="text-center font-medium mt-2">{{ $this->currentStep->title }}</p>
    @endif
</div>
```

### Breadcrumb Style

```blade
<div class="breadcrumbs text-sm mb-8">
    <ul>
        @foreach($this->steps as $index => $step)
            <li class="{{ $index <= $currentStepIndex ? 'text-primary' : 'text-base-content/50' }}">
                @if($form->allow_step_navigation && $index < $currentStepIndex)
                    <a wire:click="goToStep({{ $index }})" class="cursor-pointer hover:underline">
                        {{ $step->title ?: 'Step ' . ($index + 1) }}
                    </a>
                @else
                    <span>{{ $step->title ?: 'Step ' . ($index + 1) }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
```

---

## Navigation Buttons

```blade
<div class="flex items-center justify-between mt-8 pt-6 border-t border-base-300">
    {{-- Previous Button --}}
    <div>
        @if(!$this->isFirstStep)
            <x-artisanpack-button
                type="button"
                wire:click="previousStep"
                color="ghost"
            >
                <x-artisanpack-icon name="o-arrow-left" class="w-4 h-4" />
                {{ $this->currentStep->prev_button_text ?? __('forms.previous') }}
            </x-artisanpack-button>
        @endif
    </div>

    {{-- Step Indicator (mobile) --}}
    <div class="md:hidden text-sm text-base-content/60">
        {{ $currentStepIndex + 1 }} / {{ $this->totalSteps }}
    </div>

    {{-- Next/Submit Button --}}
    <div>
        @if(!$this->isLastStep)
            <x-artisanpack-button
                type="button"
                wire:click="nextStep"
                color="primary"
            >
                {{ $this->currentStep->next_button_text ?? __('forms.next') }}
                <x-artisanpack-icon name="o-arrow-right" class="w-4 h-4" />
            </x-artisanpack-button>
        @else
            <x-artisanpack-button
                type="submit"
                color="primary"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>
                    {{ $form->submit_button_text ?? __('forms.submit') }}
                </span>
                <span wire:loading>
                    <x-artisanpack-loading size="sm" />
                </span>
            </x-artisanpack-button>
        @endif
    </div>
</div>
```

---

## Validation Strategy

### Per-Step Validation

Validation runs when advancing to the next step:

```php
public function nextStep(): void
{
    $this->validateStep(); // Throws ValidationException if invalid
    $this->currentStepIndex++;
}
```

### Full Form Validation on Submit

All visible fields are validated on final submission:

```php
public function submit(): void
{
    // Build rules for ALL fields (not just current step)
    $rules = $this->buildAllValidationRules();
    $this->validate($rules);

    // Process submission...
}
```

### Error Display

Errors show only for the current step's fields:

```blade
@if($errors->any())
    <x-artisanpack-alert color="error" class="mb-6">
        <strong>Please fix the following errors:</strong>
        <ul class="list-disc list-inside mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-artisanpack-alert>
@endif
```

---

## Keyboard Navigation

```blade
<div
    x-data
    @keydown.enter.prevent="
        if (!$event.target.matches('textarea')) {
            $wire.{{ $this->isLastStep ? 'submit' : 'nextStep' }}();
        }
    "
    @keydown.escape="$wire.previousStep()"
>
    {{-- Form content --}}
</div>
```

---

## Conversion to Single-Step

When disabling multi-step mode:

```php
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
```

---

## Related Documents

- [03-form-builder.md](03-form-builder.md) - Step management in builder
- [04-form-renderer.md](04-form-renderer.md) - Step rendering
- [06-conditional-logic.md](06-conditional-logic.md) - Conditions work per-step
