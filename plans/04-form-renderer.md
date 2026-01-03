# Form Renderer

**Purpose:** Define the frontend form display component, submission handling, validation, and success/error states.

---

## Overview

The form renderer is a Livewire component that:

- Displays forms on the frontend using `livewire-ui-components`
- Handles form submission via AJAX
- Performs client-side and server-side validation
- Shows validation errors inline
- Handles multi-step navigation
- Displays success messages or redirects
- Implements spam protection (honeypot, rate limiting)
- Handles file uploads

---

## Component Architecture

```
FormRenderer (Main Livewire Component)
├── FormHeader (optional title/description)
├── ProgressBar (if multi-step)
├── StepContent (for each visible step)
│   └── FieldWrapper (for each field)
│       ├── FieldLabel
│       ├── FieldInput (artisanpack-* component)
│       ├── FieldHelpText
│       └── FieldError
├── NavigationButtons
│   ├── Previous Button (if not first step)
│   ├── Next Button (if not last step)
│   └── Submit Button (if last step or single-step)
├── HoneypotField (hidden)
└── SuccessMessage (after submission)
```

---

## Main Component: FormRenderer

```php
<?php

namespace ArtisanPackUI\Forms\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Services\ValidationService;
use ArtisanPackUI\Forms\Events\FormSubmitted;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class FormRenderer extends Component
{
    use WithFileUploads;

    // =========================================
    // Properties
    // =========================================

    #[Locked]
    public Form $form;

    public array $formData = [];
    public array $fileUploads = [];

    // Multi-step state
    public int $currentStepIndex = 0;

    // Submission state
    public bool $isSubmitting = false;
    public bool $isSubmitted = false;
    public ?FormSubmission $submission = null;

    // Honeypot
    public string $honeypot = '';

    // =========================================
    // Lifecycle
    // =========================================

    public function mount(Form $form): void
    {
        $this->form = $form->load(['fields', 'steps.fields']);

        // Initialize form data with default values
        foreach ($this->form->fields_ordered as $field) {
            $this->formData[$field->name] = $field->default_value ?? '';
        }
    }

    // =========================================
    // Computed Properties
    // =========================================

    #[Computed]
    public function steps(): Collection
    {
        if (!$this->form->is_multi_step) {
            return collect();
        }

        return $this->form->steps()->with(['fields' => fn($q) => $q->orderBy('sort_order')])->get();
    }

    #[Computed]
    public function currentStep(): ?FormStep
    {
        if (!$this->form->is_multi_step) {
            return null;
        }

        return $this->steps[$this->currentStepIndex] ?? null;
    }

    #[Computed]
    public function currentFields(): Collection
    {
        if ($this->form->is_multi_step && $this->currentStep) {
            return $this->currentStep->fields;
        }

        return $this->form->fields()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function visibleFields(): Collection
    {
        return $this->currentFields->filter(function (FormField $field) {
            return $this->evaluateConditionalLogic($field);
        });
    }

    #[Computed]
    public function isFirstStep(): bool
    {
        return $this->currentStepIndex === 0;
    }

    #[Computed]
    public function isLastStep(): bool
    {
        if (!$this->form->is_multi_step) {
            return true;
        }

        return $this->currentStepIndex === $this->steps->count() - 1;
    }

    #[Computed]
    public function progressPercentage(): int
    {
        if (!$this->form->is_multi_step || $this->steps->isEmpty()) {
            return 100;
        }

        return (int) (($this->currentStepIndex + 1) / $this->steps->count() * 100);
    }

    // =========================================
    // Navigation
    // =========================================

    public function nextStep(): void
    {
        // Validate current step fields
        $this->validateStep();

        if ($this->currentStepIndex < $this->steps->count() - 1) {
            $this->currentStepIndex++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStepIndex > 0) {
            $this->currentStepIndex--;
        }
    }

    public function goToStep(int $index): void
    {
        // Only allow if form allows step navigation
        if (!$this->form->allow_step_navigation) {
            return;
        }

        // Validate all steps up to the target
        for ($i = 0; $i < $index; $i++) {
            $this->currentStepIndex = $i;
            $this->validateStep();
        }

        $this->currentStepIndex = $index;
    }

    // =========================================
    // Validation
    // =========================================

    protected function validateStep(): void
    {
        $rules = $this->buildStepValidationRules();
        $this->validate($rules, $this->getCustomMessages());
    }

    protected function buildStepValidationRules(): array
    {
        $rules = [];

        foreach ($this->currentFields as $field) {
            // Skip if field is hidden by conditional logic
            if (!$this->evaluateConditionalLogic($field)) {
                continue;
            }

            $fieldRules = $field->buildValidationRules();
            $rules["formData.{$field->name}"] = $fieldRules;
        }

        return $rules;
    }

    protected function buildAllValidationRules(): array
    {
        $rules = [];

        foreach ($this->form->fields_ordered as $field) {
            // Skip if field is hidden by conditional logic
            if (!$this->evaluateConditionalLogic($field)) {
                continue;
            }

            $fieldRules = $field->buildValidationRules();
            $rules["formData.{$field->name}"] = $fieldRules;
        }

        return $rules;
    }

    protected function getCustomMessages(): array
    {
        $messages = [];

        foreach ($this->form->fields_ordered as $field) {
            $customMessage = $field->getValidationRule('custom_message');
            if ($customMessage) {
                $messages["formData.{$field->name}.required"] = $customMessage;
            }
        }

        return $messages;
    }

    // =========================================
    // Conditional Logic
    // =========================================

    protected function evaluateConditionalLogic(FormField $field): bool
    {
        if (!$field->has_conditional_logic) {
            return true;
        }

        $logic = $field->conditional_logic;
        $action = $logic['action'] ?? 'show';
        $logicType = $logic['logic'] ?? 'all';
        $rules = $logic['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = [];

        foreach ($rules as $rule) {
            $targetField = $this->form->fields->firstWhere('uuid', $rule['field_uuid']);
            if (!$targetField) {
                continue;
            }

            $fieldValue = $this->formData[$targetField->name] ?? '';
            $ruleValue = $rule['value'] ?? '';
            $operator = $rule['operator'] ?? 'equals';

            $results[] = $this->evaluateRule($fieldValue, $operator, $ruleValue);
        }

        $conditionMet = $logicType === 'all'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);

        return $action === 'show' ? $conditionMet : !$conditionMet;
    }

    protected function evaluateRule(mixed $fieldValue, string $operator, mixed $ruleValue): bool
    {
        return match ($operator) {
            'equals' => $fieldValue == $ruleValue,
            'not_equals' => $fieldValue != $ruleValue,
            'contains' => str_contains((string) $fieldValue, (string) $ruleValue),
            'not_contains' => !str_contains((string) $fieldValue, (string) $ruleValue),
            'starts_with' => str_starts_with((string) $fieldValue, (string) $ruleValue),
            'ends_with' => str_ends_with((string) $fieldValue, (string) $ruleValue),
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            'greater_than' => (float) $fieldValue > (float) $ruleValue,
            'less_than' => (float) $fieldValue < (float) $ruleValue,
            default => false,
        };
    }

    // =========================================
    // Submission
    // =========================================

    public function submit(): void
    {
        // Honeypot check
        if (!empty($this->honeypot)) {
            // Silently ignore bot submissions
            $this->isSubmitted = true;
            return;
        }

        // Rate limiting
        $key = 'form_submission:' . request()->ip() . ':' . $this->form->id;
        $maxAttempts = config('forms.spam_protection.rate_limit.max_attempts', 5);
        $decayMinutes = config('forms.spam_protection.rate_limit.decay_minutes', 1);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->addError('form', __('forms.rate_limit_exceeded'));
            return;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        // Validate all fields
        $this->validate(
            $this->buildAllValidationRules(),
            $this->getCustomMessages()
        );

        $this->isSubmitting = true;

        try {
            $submissionService = app(SubmissionService::class);

            $this->submission = $submissionService->create(
                form: $this->form,
                data: $this->formData,
                files: $this->fileUploads,
                metadata: [
                    'page_url' => url()->previous(),
                    'referrer_url' => request()->headers->get('referer'),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );

            $this->isSubmitted = true;

            // Dispatch event for integrations
            FormSubmitted::dispatch($this->submission);

            // Check for redirect
            if ($this->form->redirect_url) {
                $this->redirect($this->form->redirect_url);
            }

        } catch (\Exception $e) {
            $this->addError('form', __('forms.submission_error'));
            report($e);
        } finally {
            $this->isSubmitting = false;
        }
    }

    // =========================================
    // File Uploads
    // =========================================

    public function updatedFileUploads(): void
    {
        foreach ($this->fileUploads as $fieldName => $file) {
            $field = $this->form->fields->firstWhere('name', $fieldName);

            if (!$field) {
                continue;
            }

            // Validate file
            $maxSize = $field->getConfig('max_size', config('forms.uploads.max_size'));
            $allowedTypes = $field->getConfig('allowed_types', config('forms.uploads.allowed_types'));

            $this->validateOnly("fileUploads.{$fieldName}", [
                "fileUploads.{$fieldName}" => [
                    'file',
                    "max:{$maxSize}",
                    'mimes:' . implode(',', $allowedTypes),
                ],
            ]);
        }
    }

    // =========================================
    // Render
    // =========================================

    public function render()
    {
        return view('forms::components.form-renderer');
    }
}
```

---

## Form Renderer View

```blade
{{-- resources/views/components/form-renderer.blade.php --}}

<div>
    @if($isSubmitted)
        {{-- Success State --}}
        <div class="text-center py-8">
            <x-artisanpack-icon name="o-check-circle" class="w-16 h-16 mx-auto text-success mb-4" />
            <div class="prose max-w-none">
                {!! nl2br(e($form->success_message ?? __('forms.default_success'))) !!}
            </div>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            {{-- Form Header --}}
            @if($form->description)
                <div class="prose max-w-none mb-6">
                    {!! nl2br(e($form->description)) !!}
                </div>
            @endif

            {{-- Progress Bar (Multi-Step) --}}
            @if($form->is_multi_step && $form->show_progress_bar)
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-2">
                        @foreach($this->steps as $index => $step)
                            <div
                                class="flex items-center {{ $index < $this->steps->count() - 1 ? 'flex-1' : '' }}"
                            >
                                <button
                                    type="button"
                                    @if($form->allow_step_navigation && $index <= $currentStepIndex)
                                        wire:click="goToStep({{ $index }})"
                                    @endif
                                    class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium transition
                                           {{ $index <= $currentStepIndex
                                               ? 'bg-primary text-primary-content'
                                               : 'bg-base-300 text-base-content/50' }}
                                           {{ $form->allow_step_navigation && $index <= $currentStepIndex ? 'cursor-pointer' : 'cursor-default' }}"
                                >
                                    {{ $index + 1 }}
                                </button>
                                @if($index < $this->steps->count() - 1)
                                    <div class="flex-1 h-1 mx-2 {{ $index < $currentStepIndex ? 'bg-primary' : 'bg-base-300' }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($this->currentStep?->title)
                        <p class="text-center text-sm font-medium">{{ $this->currentStep->title }}</p>
                    @endif
                </div>
            @endif

            {{-- Step Description --}}
            @if($form->is_multi_step && $this->currentStep?->description)
                <div class="prose max-w-none mb-6">
                    {!! nl2br(e($this->currentStep->description)) !!}
                </div>
            @endif

            {{-- Error Summary --}}
            @if($errors->any())
                <x-artisanpack-alert color="error">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-artisanpack-alert>
            @endif

            {{-- Fields --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($this->visibleFields as $field)
                    <div class="{{ $field->width_class }}">
                        @include('forms::components.fields.' . $field->type, ['field' => $field])
                    </div>
                @endforeach
            </div>

            {{-- Honeypot --}}
            @if(config('forms.spam_protection.honeypot', true))
                <div class="hidden" aria-hidden="true">
                    <input
                        type="text"
                        name="honeypot"
                        wire:model="honeypot"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>
            @endif

            {{-- Navigation / Submit --}}
            <div class="flex items-center justify-between pt-4">
                <div>
                    @if($form->is_multi_step && !$this->isFirstStep)
                        <x-artisanpack-button
                            type="button"
                            wire:click="previousStep"
                            color="ghost"
                        >
                            <x-artisanpack-icon name="o-arrow-left" class="w-4 h-4" />
                            {{ $this->currentStep?->prev_button_text ?? __('forms.previous') }}
                        </x-artisanpack-button>
                    @endif
                </div>

                <div>
                    @if($form->is_multi_step && !$this->isLastStep)
                        <x-artisanpack-button
                            type="button"
                            wire:click="nextStep"
                            color="primary"
                        >
                            {{ $this->currentStep?->next_button_text ?? __('forms.next') }}
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
                                {{ __('forms.submitting') }}
                            </span>
                        </x-artisanpack-button>
                    @endif
                </div>
            </div>
        </form>
    @endif
</div>
```

---

## Field Templates

Each field type has a corresponding Blade partial:

### Text Field

```blade
{{-- resources/views/components/fields/text.blade.php --}}

@props(['field'])

<x-artisanpack-input
    type="text"
    wire:model.blur="formData.{{ $field->name }}"
    :label="$field->label"
    :placeholder="$field->placeholder"
    :required="$field->is_required"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :id="'field-' . $field->uuid"
/>
```

### Email Field

```blade
{{-- resources/views/components/fields/email.blade.php --}}

@props(['field'])

<x-artisanpack-input
    type="email"
    wire:model.blur="formData.{{ $field->name }}"
    :label="$field->label"
    :placeholder="$field->placeholder"
    :required="$field->is_required"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :id="'field-' . $field->uuid"
/>
```

### Textarea Field

```blade
{{-- resources/views/components/fields/textarea.blade.php --}}

@props(['field'])

<x-artisanpack-textarea
    wire:model.blur="formData.{{ $field->name }}"
    :label="$field->label"
    :placeholder="$field->placeholder"
    :required="$field->is_required"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :id="'field-' . $field->uuid"
    :rows="$field->getConfig('rows', 4)"
/>
```

### Select Field

```blade
{{-- resources/views/components/fields/select.blade.php --}}

@props(['field'])

<x-artisanpack-select
    wire:model.live="formData.{{ $field->name }}"
    :label="$field->label"
    :placeholder="$field->placeholder ?: __('forms.select_option')"
    :required="$field->is_required"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :id="'field-' . $field->uuid"
    :options="collect($field->options)->mapWithKeys(fn($opt) => [$opt['value'] => $opt['label']])->toArray()"
/>
```

### Checkbox Field

```blade
{{-- resources/views/components/fields/checkbox.blade.php --}}

@props(['field'])

<x-artisanpack-checkbox
    wire:model.live="formData.{{ $field->name }}"
    :label="$field->label"
    :required="$field->is_required"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :id="'field-' . $field->uuid"
/>
```

### Radio Field

```blade
{{-- resources/views/components/fields/radio.blade.php --}}

@props(['field'])

<x-artisanpack-radio-group
    wire:model.live="formData.{{ $field->name }}"
    :label="$field->label"
    :required="$field->is_required"
    :options="$field->options"
    option-value="value"
    option-label="label"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :horizontal="$field->getConfig('inline', false)"
/>
```

### Checkbox Group Field

```blade
{{-- resources/views/components/fields/checkbox_group.blade.php --}}

@props(['field'])

<x-artisanpack-checkbox-group
    wire:model.live="formData.{{ $field->name }}"
    :label="$field->label"
    :required="$field->is_required"
    :options="$field->options"
    option-value="value"
    option-label="label"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
    :horizontal="$field->getConfig('inline', false)"
/>
```

### File Field

```blade
{{-- resources/views/components/fields/file.blade.php --}}

@props(['field'])

<div>
    <label class="label" for="field-{{ $field->uuid }}">
        <span class="label-text">
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error" aria-label="{{ __('Required') }}">*</span>
            @endif
        </span>
    </label>

    <input
        type="file"
        wire:model="fileUploads.{{ $field->name }}"
        id="field-{{ $field->uuid }}"
        class="file-input file-input-bordered w-full {{ $errors->has('fileUploads.' . $field->name) ? 'file-input-error' : '' }}"
        @if($field->getConfig('max_files', 1) > 1) multiple @endif
        accept="{{ collect($field->getConfig('allowed_types', []))->map(fn($t) => '.' . $t)->implode(',') }}"
        aria-describedby="field-{{ $field->uuid }}-help field-{{ $field->uuid }}-error"
    />

    @if($field->help_text)
        <label class="label" id="field-{{ $field->uuid }}-help">
            <span class="label-text-alt">{{ $field->help_text }}</span>
        </label>
    @endif

    @error('fileUploads.' . $field->name)
        <label class="label" id="field-{{ $field->uuid }}-error" role="alert">
            <span class="label-text-alt text-error">{{ $message }}</span>
        </label>
    @enderror

    {{-- Upload Progress --}}
    <div wire:loading wire:target="fileUploads.{{ $field->name }}" role="status" aria-live="polite">
        <progress class="progress progress-primary w-full mt-2" aria-label="{{ __('Uploading...') }}"></progress>
        <span class="sr-only">{{ __('Uploading...') }}</span>
    </div>
</div>
```

---

## Blade Component Usage

Forms can be embedded in any page using the Blade component:

```blade
{{-- Simple usage --}}
<livewire:forms::form-renderer :form="$form" />

{{-- Or by slug --}}
<x-forms-embed slug="contact-us" />
```

### Embed Component

```php
<?php

namespace ArtisanPackUI\Forms\View\Components;

use Illuminate\View\Component;
use ArtisanPackUI\Forms\Models\Form;

class FormsEmbed extends Component
{
    public Form $form;

    public function __construct(
        public ?string $slug = null,
        public ?int $id = null
    ) {
        if ($slug) {
            $this->form = Form::where('slug', $slug)->active()->firstOrFail();
        } elseif ($id) {
            $this->form = Form::where('id', $id)->active()->firstOrFail();
        } else {
            throw new \InvalidArgumentException('Either slug or id must be provided');
        }
    }

    public function render()
    {
        return view('forms::components.embed');
    }
}
```

```blade
{{-- resources/views/components/embed.blade.php --}}

<livewire:forms::form-renderer :form="$form" />
```

---

## Related Documents

- [03-form-builder.md](03-form-builder.md) - Admin form building
- [05-field-types.md](05-field-types.md) - All field type details
- [06-conditional-logic.md](06-conditional-logic.md) - Field visibility rules
- [07-multi-step-forms.md](07-multi-step-forms.md) - Multi-step handling
