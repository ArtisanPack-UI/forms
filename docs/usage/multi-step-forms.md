---
title: Multi-Step Forms
---

# Multi-Step Forms

Break long forms into manageable steps with progress indicators and step-by-step navigation.

## Creating Multi-Step Forms

### Via Form Builder

1. Create a new form
2. Go to the "Steps" tab
3. Add steps with titles and descriptions
4. Assign fields to each step
5. Save the form

### Programmatically

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormField;

// Create form
$form = Form::create([
    'name' => 'Registration Form',
    'slug' => 'registration',
    'is_active' => true,
    'is_multi_step' => true,
]);

// Create steps
$step1 = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Personal Information',
    'description' => 'Tell us about yourself',
    'order' => 1,
]);

$step2 = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Contact Details',
    'description' => 'How can we reach you?',
    'order' => 2,
]);

$step3 = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Preferences',
    'description' => 'Customize your experience',
    'order' => 3,
]);

// Add fields to steps
FormField::create([
    'form_id' => $form->id,
    'step_id' => $step1->id,
    'type' => 'text',
    'name' => 'first_name',
    'label' => 'First Name',
    'required' => true,
    'order' => 1,
]);

FormField::create([
    'form_id' => $form->id,
    'step_id' => $step2->id,
    'type' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'required' => true,
    'order' => 1,
]);
```

## Step Configuration

### Step Properties

| Property | Description |
|----------|-------------|
| `title` | Step title displayed to user |
| `description` | Optional step description |
| `order` | Step order (1, 2, 3...) |
| `validation_rules` | Per-step validation rules |
| `conditions` | Conditional step visibility |

### Step-Level Validation

```php
$step = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Payment',
    'order' => 3,
    'validation_rules' => [
        'card_number' => 'required|digits:16',
        'expiry' => 'required|date_format:m/Y',
    ],
]);
```

## Displaying Multi-Step Forms

The Form Renderer automatically handles multi-step forms:

```blade
<livewire:forms::form-renderer slug="registration" />
```

### Progress Indicator

The default view includes a progress indicator:

```blade
{{-- Included in form-renderer view --}}
@if ($form->is_multi_step)
    <div class="step-progress">
        @foreach ($form->steps as $step)
            <div class="step {{ $currentStep >= $step->order ? 'active' : '' }}">
                <span class="step-number">{{ $step->order }}</span>
                <span class="step-title">{{ $step->title }}</span>
            </div>
        @endforeach
    </div>
@endif
```

## Navigation

### Next/Previous Buttons

```blade
<div class="step-navigation">
    @if ($currentStep > 1)
        <button wire:click="previousStep" type="button">
            Previous
        </button>
    @endif

    @if ($currentStep < $totalSteps)
        <button wire:click="nextStep" type="button">
            Next
        </button>
    @else
        <button type="submit">
            Submit
        </button>
    @endif
</div>
```

### Direct Step Navigation

Allow users to jump to specific steps:

```blade
@foreach ($form->steps as $step)
    <button
        wire:click="goToStep({{ $step->order }})"
        @if ($step->order > $maxReachedStep) disabled @endif
    >
        {{ $step->title }}
    </button>
@endforeach
```

## Validation

### Per-Step Validation

Each step validates its fields before proceeding:

```php
public function nextStep(): void
{
    // Validate current step fields
    $this->validate($this->getCurrentStepRules());

    $this->currentStep++;
}
```

### Step Validation Rules

```php
protected function getCurrentStepRules(): array
{
    $step = $this->form->steps->firstWhere('order', $this->currentStep);
    $rules = [];

    foreach ($step->fields as $field) {
        $rules[$field->name] = $field->getValidationRules();
    }

    return $rules;
}
```

## Conditional Steps

Show or skip steps based on previous answers:

```php
FormStep::create([
    'form_id' => $form->id,
    'title' => 'Business Information',
    'order' => 3,
    'conditions' => [
        [
            'field' => 'account_type',
            'operator' => 'equals',
            'value' => 'business',
        ],
    ],
]);
```

When `account_type` is not "business", step 3 is skipped.

## Data Persistence

Form data is preserved between steps:

### Using Livewire

```php
// Data persists in component state
public array $formData = [];

public function nextStep(): void
{
    // formData is already saved, just move to next step
    $this->currentStep++;
}
```

### Using Sessions

For longer forms, persist to session:

```php
public function saveProgress(): void
{
    session()->put("form_progress_{$this->form->id}", [
        'step' => $this->currentStep,
        'data' => $this->formData,
    ]);
}

public function mount(): void
{
    $progress = session("form_progress_{$this->form->id}");

    if ($progress) {
        $this->currentStep = $progress['step'];
        $this->formData = $progress['data'];
    }
}
```

## Customizing Step Display

Publish views to customize:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/partials/multi-step/`:

- `progress.blade.php` - Progress indicator
- `navigation.blade.php` - Next/Previous buttons
- `step.blade.php` - Individual step wrapper

## Example: Wizard Form

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormField;

$form = Form::create([
    'name' => 'Job Application',
    'slug' => 'job-application',
    'is_multi_step' => true,
    'settings' => [
        'show_step_numbers' => true,
        'show_progress_bar' => true,
        'allow_step_navigation' => false, // Must complete in order
    ],
]);

// Step 1: Personal Info
$step1 = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Personal Information',
    'order' => 1,
]);

FormField::create(['form_id' => $form->id, 'step_id' => $step1->id, 'type' => 'text', 'name' => 'full_name', 'label' => 'Full Name', 'required' => true, 'order' => 1]);
FormField::create(['form_id' => $form->id, 'step_id' => $step1->id, 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'order' => 2]);
FormField::create(['form_id' => $form->id, 'step_id' => $step1->id, 'type' => 'phone', 'name' => 'phone', 'label' => 'Phone', 'required' => true, 'order' => 3]);

// Step 2: Experience
$step2 = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Experience',
    'order' => 2,
]);

FormField::create(['form_id' => $form->id, 'step_id' => $step2->id, 'type' => 'textarea', 'name' => 'experience', 'label' => 'Work Experience', 'order' => 1]);
FormField::create(['form_id' => $form->id, 'step_id' => $step2->id, 'type' => 'file', 'name' => 'resume', 'label' => 'Resume', 'settings' => ['accept' => '.pdf,.doc,.docx'], 'order' => 2]);

// Step 3: Review
$step3 = FormStep::create([
    'form_id' => $form->id,
    'title' => 'Review & Submit',
    'order' => 3,
]);

FormField::create(['form_id' => $form->id, 'step_id' => $step3->id, 'type' => 'html', 'name' => 'review_text', 'settings' => ['content' => '<p>Please review your information before submitting.</p>'], 'order' => 1]);
FormField::create(['form_id' => $form->id, 'step_id' => $step3->id, 'type' => 'checkbox', 'name' => 'agree_terms', 'label' => 'I agree to the terms and conditions', 'required' => true, 'order' => 2]);
```

## Next Steps

- [Conditional Logic](./conditional-logic.md) - Dynamic field visibility
- [Form Renderer](./form-renderer.md) - Displaying forms
- [Submissions](./submissions.md) - Managing submissions
