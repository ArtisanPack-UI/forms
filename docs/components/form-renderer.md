---
title: FormRenderer Component
---

# FormRenderer Component

The FormRenderer component displays forms to end users and handles submission processing.

## Basic Usage

```blade
{{-- By slug (recommended) --}}
<livewire:forms::form-renderer slug="contact" />

{{-- By form ID --}}
<livewire:forms::form-renderer :form-id="1" />

{{-- With form model --}}
<livewire:forms::form-renderer :form="$form" />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `slug` | string\|null | null | Form slug |
| `formId` | int\|null | null | Form ID |
| `form` | Form\|null | null | Form model |
| `successMessage` | string\|null | null | Override success message |
| `redirectUrl` | string\|null | null | Redirect after submit |
| `showTitle` | bool | true | Show form title |
| `showDescription` | bool | true | Show form description |
| `cssClass` | string | '' | Additional CSS classes |

## Examples

### Basic Form

```blade
<livewire:forms::form-renderer slug="contact" />
```

### With Custom Success Message

```blade
<livewire:forms::form-renderer
    slug="contact"
    success-message="Thanks! We'll respond within 24 hours."
/>
```

### With Redirect

```blade
<livewire:forms::form-renderer
    slug="signup"
    redirect-url="/welcome"
/>
```

### Minimal Display

```blade
<livewire:forms::form-renderer
    slug="newsletter"
    :show-title="false"
    :show-description="false"
    css-class="newsletter-form"
/>
```

## Component State

The component manages several states:

```php
public array $formData = [];     // Field values
public array $errors = [];       // Validation errors
public bool $submitted = false;  // Submission state
public int $currentStep = 1;     // Current step (multi-step)
```

## Events Emitted

| Event | Payload | Description |
|-------|---------|-------------|
| `form-submitted` | `{ formId, submissionId }` | Form submitted |
| `step-changed` | `{ step }` | Step navigation |
| `validation-failed` | `{ errors }` | Validation errors |

### JavaScript Listeners

```javascript
document.addEventListener('livewire:initialized', () => {
    Livewire.on('form-submitted', (event) => {
        // Track conversion
        gtag('event', 'conversion', {
            send_to: 'AW-XXXXX/XXXXX',
            form_id: event.formId,
        });
    });

    Livewire.on('validation-failed', (event) => {
        // Scroll to first error
        const firstError = document.querySelector('.field-error');
        firstError?.scrollIntoView({ behavior: 'smooth' });
    });
});
```

## Methods

### submit

Validates and submits the form:

```php
public function submit(): void
{
    $this->validate($this->getValidationRules());

    $submission = $this->submissionService->create(
        $this->form,
        $this->formData
    );

    if ($this->redirectUrl) {
        $this->redirect($this->redirectUrl);
    } else {
        $this->submitted = true;
    }

    $this->dispatch('form-submitted', [
        'formId' => $this->form->id,
        'submissionId' => $submission->id,
    ]);
}
```

### nextStep / previousStep

Navigate multi-step forms:

```php
public function nextStep(): void
{
    $this->validateCurrentStep();
    $this->currentStep++;
    $this->dispatch('step-changed', step: $this->currentStep);
}

public function previousStep(): void
{
    $this->currentStep--;
    $this->dispatch('step-changed', step: $this->currentStep);
}
```

## Validation

Validation rules are generated from field configuration:

```php
protected function getValidationRules(): array
{
    $rules = [];

    foreach ($this->getVisibleFields() as $field) {
        $fieldRules = [];

        if ($field->required) {
            $fieldRules[] = 'required';
        }

        if ($field->validation['min'] ?? null) {
            $fieldRules[] = 'min:' . $field->validation['min'];
        }

        if ($field->validation['max'] ?? null) {
            $fieldRules[] = 'max:' . $field->validation['max'];
        }

        // Add type-specific rules
        if ($field->type === 'email') {
            $fieldRules[] = 'email';
        }

        $rules["formData.{$field->name}"] = $fieldRules;
    }

    return $rules;
}
```

## Customizing the View

Publish and edit:

```bash
php artisan vendor:publish --tag=forms-views
```

### View Structure

```blade
{{-- resources/views/vendor/forms/livewire/form-renderer.blade.php --}}
<div class="form-renderer {{ $cssClass }}">
    @if ($submitted)
        {{-- Success State --}}
        <div class="form-success">
            {{ $successMessage ?? $form->success_message ?? 'Thank you!' }}
        </div>
    @else
        {{-- Form --}}
        <form wire:submit="submit">
            @if ($showTitle && $form->name)
                <h2 class="form-title">{{ $form->name }}</h2>
            @endif

            @if ($showDescription && $form->description)
                <p class="form-description">{{ $form->description }}</p>
            @endif

            {{-- Progress (multi-step) --}}
            @if ($form->is_multi_step)
                @include('forms::partials.progress')
            @endif

            {{-- Fields --}}
            @foreach ($currentFields as $field)
                @include("forms::partials.fields.{$field->type}", ['field' => $field])
            @endforeach

            {{-- Navigation --}}
            @include('forms::partials.navigation')
        </form>
    @endif
</div>
```

### Custom Field Templates

Create custom field templates in `resources/views/vendor/forms/partials/fields/`:

```blade
{{-- custom-field.blade.php --}}
<div class="form-group {{ $field->settings['wrapper_class'] ?? '' }}">
    <label for="{{ $field->name }}">
        {{ $field->label }}
        @if ($field->required)
            <span class="required">*</span>
        @endif
    </label>

    <input
        type="text"
        id="{{ $field->name }}"
        wire:model="formData.{{ $field->name }}"
        class="form-input @error('formData.' . $field->name) is-invalid @enderror"
        placeholder="{{ $field->placeholder }}"
    >

    @if ($field->help_text)
        <small class="help-text">{{ $field->help_text }}</small>
    @endif

    @error('formData.' . $field->name)
        <span class="error-message">{{ $message }}</span>
    @enderror
</div>
```

## File Uploads

Handle file uploads with Livewire:

```blade
{{-- file.blade.php --}}
<div class="form-group">
    <label>{{ $field->label }}</label>

    <input
        type="file"
        wire:model="formData.{{ $field->name }}"
        accept="{{ $field->settings['accept'] ?? '*' }}"
        @if ($field->settings['multiple'] ?? false) multiple @endif
    >

    <div wire:loading wire:target="formData.{{ $field->name }}">
        Uploading...
    </div>
</div>
```

## Accessibility

The component includes accessibility features:

- Proper label associations
- ARIA attributes for errors
- Focus management
- Screen reader announcements

```blade
<input
    type="text"
    id="{{ $field->name }}"
    aria-describedby="{{ $field->name }}-help {{ $field->name }}-error"
    aria-invalid="{{ $errors->has('formData.' . $field->name) ? 'true' : 'false' }}"
>

@if ($field->help_text)
    <small id="{{ $field->name }}-help">{{ $field->help_text }}</small>
@endif

@error('formData.' . $field->name)
    <span id="{{ $field->name }}-error" role="alert">{{ $message }}</span>
@enderror
```

## Extending the Component

```php
namespace App\Livewire;

use ArtisanPackUI\Forms\Livewire\FormRenderer;

class CustomFormRenderer extends FormRenderer
{
    public function submit(): void
    {
        // Pre-submission hook
        $this->preSubmit();

        parent::submit();

        // Post-submission hook
        $this->postSubmit();
    }

    protected function preSubmit(): void
    {
        // Add custom data
        $this->formData['submitted_from'] = request()->url();
    }
}
```

## Next Steps

- [Form Renderer Usage](Usage-Form-Renderer) - Usage guide
- [FormsList](Forms-List) - Forms listing component
- [Submissions](Usage-Submissions) - Submission handling
