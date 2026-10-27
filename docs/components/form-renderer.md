---
title: FormRenderer Component
---

# FormRenderer Component

The FormRenderer component displays forms to end users and handles submission processing.

## Basic Usage

The component takes a `Form` model. Load the form in your controller or route, then pass it in:

```php
use ArtisanPackUI\Forms\Services\FormService;

Route::get('/contact', function (FormService $forms) {
    $form = $forms->getBySlug('contact');

    abort_unless($form?->is_active, 404);

    return view('contact', ['form' => $form]);
});
```

```blade
<livewire:form-renderer :form="$form" />
```

`FormService` also has `getById()`. Both return `null` when no form matches. You can query the model directly too:

```php
$form = \ArtisanPackUI\Forms\Models\Form::where('slug', 'contact')->firstOrFail();
```

> For an inactive form the renderer shows "This form is not accepting submissions." instead of the fields and refuses submissions (since 1.6.0). Check `is_active` yourself only if you want to hide the component or show your own message.

## Properties

The component's `mount()` signature is `mount(Form $form, array $prefill = [])`:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `form` | Form | required | The form to render |
| `prefill` | array | `[]` | Starting values for fields with dynamic population on, keyed by parameter name (since 1.6.0; see [Dynamic Population](Usage-Dynamic-Population)) |

There are no props for the success message, redirect, title, description or CSS classes. Those come from the form itself (see below) or from your own markup around the component.

## Display Settings Come From the Form

These are set on the form, in the **Settings** tab of the form's edit page (`/admin/forms/{form}/edit`) or as model attributes. See [Form Settings](Usage-Form-Builder#form-settings).

| Form attribute | Effect in the renderer |
|----------------|------------------------|
| `success_message` | Shown after a successful submission. Falls back to "Thank you! Your submission has been received." |
| `redirect_url` | After a successful submission the component redirects to this URL and dispatches a `redirect-to` event with it (see [Redirecting After Submission](#redirecting-after-submission)) |
| `description` | Shown above the fields when set |
| `submit_button_text` | Label of the submit button |
| `is_multi_step`, `show_progress_bar`, `allow_step_navigation` | Multi-step behavior (see [Multi-Step Forms](Usage-Multi-Step-Forms)) |
| `settings.label_placement`, `settings.description_placement` | The form's default label and help text placement |

The renderer does not print the form's name as a heading. Add your own heading around the component if you want one.

## Examples

### Basic Form

```blade
<livewire:form-renderer :form="$form" />
```

### With Prefilled Values

```blade
<livewire:form-renderer
    :form="$form"
    :prefill="['plan' => 'pro', 'email' => auth()->user()?->email]"
/>
```

### Custom Wrapper and Heading

```blade
<section class="newsletter-form bg-white p-6 rounded-lg">
    <h2 class="text-xl font-semibold">{{ $form->name }}</h2>

    <livewire:form-renderer :form="$form" />
</section>
```

### Several Forms on One Page

Give each instance its own `key`:

```blade
<livewire:form-renderer :form="$contactForm" key="contact-form" />
<livewire:form-renderer :form="$newsletterForm" key="newsletter-form" />
```

Field IDs are prefixed per instance, so the same form can appear twice on a page without ID clashes.

## Component State

The main public properties:

```php
public Form $form;                   // The form being rendered
public array $formData = [];         // Field values, keyed by field name
public array $files = [];            // Uploaded files, keyed by field name
public bool $isSubmitted = false;    // True after a successful submission
public int $currentStepIndex = 0;    // Current step (0-based, multi-step forms)
public ?string $errorMessage = null; // Form-level error (rate limit, failed save)
public array $hiddenFields = [];     // Fields hidden by conditional logic
```

## Events Emitted

| Event | Payload | When |
|-------|---------|------|
| `form-submitted` | `{ formId, submissionId }` | A submission was saved |
| `form-submitted` | `{ formId, isSpam: true }` | The honeypot or timing check caught a bot. Nothing is saved and the visitor sees the success message |
| `redirect-to` | `{ url }` | After a saved submission, when the form has a `redirect_url` |
| `step-changed` | `{ step }` | The visitor moved to another step (1-based) |
| `forms-validation-failed` | `{ formId, count }` | Validation failed on submit or on a step change. The renderer uses it to announce the errors and focus the first invalid field |
| `form-reset` | `{ formId }` | `resetForm()` cleared the form for another response |
| `forms-captcha-reset` | `{ formId }` | A CAPTCHA token was spent and the widget needs a new one (used internally) |

### Redirecting After Submission

After a saved submission the renderer redirects to the form's redirect URL (since 1.6.0) and dispatches `redirect-to` with it, which you can listen for to track the conversion first. Only `http`/`https` URLs and site paths are followed.

### JavaScript Listeners

```javascript
document.addEventListener('livewire:init', () => {
    Livewire.on('form-submitted', ({ formId, submissionId, isSpam }) => {
        if (isSpam) {
            return;
        }

        // Track conversion
        gtag('event', 'conversion', {
            send_to: 'AW-XXXXX/XXXXX',
            form_id: formId,
        });
    });

    Livewire.on('step-changed', ({ step }) => {
        console.log('Now on step', step);
    });
});
```

The events are also dispatched on `window`, so Alpine can listen with `x-on:form-submitted.window="..."`.

For server-side work after a submission, listen for the `ArtisanPackUI\Forms\Events\FormSubmitted` Laravel event instead.

## Methods

| Method | Description |
|--------|-------------|
| `submit()` | Runs the spam, rate limit, validation and CAPTCHA checks, saves the submission and dispatches `form-submitted` |
| `nextStep()` | Validates the current step, then moves forward |
| `previousStep()` | Moves back one step |
| `goToStep(int $stepIndex)` | Jumps to a step (0-based). Only works when the form allows step navigation. Moving forward validates each step in between |
| `resetForm()` | Clears the form so the visitor can submit again |
| `removeFile(string $fieldName, string $key)` | Removes one upload from a multi-file field before submitting |

## Validation

Validation rules are built from each field's configuration (required, type and the field's validation settings). Only fields that are visible after conditional logic are validated and saved. Errors show inline next to each field, and form-level errors (rate limiting, a failed save) show in an alert above the fields.

## Customizing the View

Publish and edit:

```bash
php artisan vendor:publish --tag=forms-views
```

The renderer's views are then in:

- `resources/views/vendor/forms/livewire/form-renderer.blade.php` - the wrapper, success state, step progress and navigation
- `resources/views/vendor/forms/livewire/form-renderer/fields.blade.php` and `field.blade.php` - the field loop and each field's wrapper
- `resources/views/vendor/forms/components/fields/{type}.blade.php` - one view per field type

Only publish what you change, and republish after upgrading. The package logs a warning when a published renderer view is too old to work with conditional logic.

## Accessibility

The component includes accessibility features:

- Proper label associations
- ARIA attributes for errors
- Focus moves to the first invalid field when validation fails
- Step changes and errors are announced in a live region
- Keyboard navigation

## Extending the Component

Extend the class and register it under your own name:

```php
namespace App\Livewire;

use ArtisanPackUI\Forms\Livewire\FormRenderer;

class CustomFormRenderer extends FormRenderer
{
    public function submit(): void
    {
        parent::submit();

        if ($this->isSubmitted) {
            // Post-submission work
        }
    }
}
```

```php
Livewire::component('custom-form-renderer', \App\Livewire\CustomFormRenderer::class);
```

## Next Steps

- [Form Renderer Usage](Usage-Form-Renderer) - Usage guide
- [FormsList](Forms-List) - Forms listing component
- [Submissions](Usage-Submissions) - Submission handling
