---
title: Components Overview
---

# Components Overview

ArtisanPack UI Forms includes pre-built Livewire components for common form operations.

## Available Components

| Component | Description | Context |
|-----------|-------------|---------|
| `FormBuilder` | Drag-and-drop form builder | Admin |
| `FormRenderer` | Displays forms to end users | Public |
| `FormsList` | Lists all forms with actions | Admin |
| `SubmissionsList` | Lists form submissions | Admin |
| `SubmissionDetail` | Shows submission details | Admin |
| `NotificationEditor` | Configures email notifications | Admin |
| `ai-spam-check` (v1.2.0+) | AI trigger UI for `SpamDetectionAgent` | Admin |
| `ai-submission-summary` (v1.2.0+) | AI trigger UI for `SubmissionSummaryAgent` | Admin |
| `ai-response-classifier` (v1.2.0+) | AI trigger UI for `ResponseClassificationAgent` | Admin |
| `ai-smart-field-validator` (v1.2.0+) | AI trigger UI for `SmartFieldValidationAgent` | Public / Admin |

See the [AI Features](Ai-Ai) section for detailed usage of each `ai-*` component.

## Component Names

The main components are registered without a namespace prefix: `form-renderer`, `forms-list`, `form-builder`, `submissions-list`, `submission-detail` and `notification-editor`. Only the AI components use the `forms::` prefix (for example `forms::ai-spam-check`).

```blade
<livewire:form-renderer :form="$form" />
<livewire:forms-list />
<livewire:form-builder :form="$form" />
```

## Component Categories

### Admin Components

These components are used in the admin interface for form management:

```blade
{{-- List all forms --}}
<livewire:forms-list />

{{-- Build/edit a form --}}
<livewire:form-builder :form="$form" />

{{-- List submissions for a form (omit form-id for all forms) --}}
<livewire:submissions-list :form-id="$form->id" />

{{-- View submission details --}}
<livewire:submission-detail :submission="$submission" />

{{-- Configure notifications --}}
<livewire:notification-editor :form="$form" />
```

### Public Components

These components are for end-user facing pages:

```blade
{{-- Display a form ($form is a Form model) --}}
<livewire:form-renderer :form="$form" />
```

## Publishing Views

Customize component views by publishing:

```bash
php artisan vendor:publish --tag=forms-views
```

Views are published to `resources/views/vendor/forms/livewire/`.

## Component Properties

Each component takes the model or ID it works on:

| Component | Mount parameters |
|-----------|------------------|
| `form-renderer` | `form` (Form, required), `prefill` (array, optional) |
| `forms-list` | None |
| `form-builder` | `form` (Form, required) |
| `submissions-list` | `form-id` (int, optional; all forms when omitted) |
| `submission-detail` | `submission` (FormSubmission, required) |
| `notification-editor` | `form` (Form, required) |

```blade
<livewire:form-renderer :form="$form" :prefill="['plan' => 'pro']" />
```

Display options such as the success message, redirect URL and submit button text are form settings, not component props (see [Form Settings](Usage-Form-Builder#form-settings)).

## Events

Components emit Livewire events you can listen to:

```javascript
document.addEventListener('livewire:init', () => {
    Livewire.on('form-submitted', ({ formId, submissionId }) => {
        console.log('Form submitted:', formId, submissionId);
    });

    Livewire.on('step-changed', ({ step }) => {
        console.log('Now on step', step);
    });
});
```

See [FormRenderer](Components-Form-Renderer#events-emitted) for the full list. The builder and notification editor dispatch their own events (such as `form-saved` and `notifications-saved`). The submission components do not dispatch Livewire events; for server-side hooks use the Laravel events (`FormSubmitted`, `SubmissionUpdated`, `SubmissionDeleted`).

## Styling

Components are built with ArtisanPack UI components and daisyUI classes, and can be customized via:

1. **Wrapper Markup** - Wrap a component in your own element and style that
2. **Published Views** - Edit the Blade templates
3. **Theme** - Components follow your daisyUI / ArtisanPack UI theme

```blade
<div class="my-custom-form bg-white p-6 rounded-lg">
    <livewire:form-renderer :form="$form" />
</div>
```

## Component Architecture

Each component follows this structure:

```
src/Livewire/
├── FormBuilder.php      # Component class
└── ...

resources/views/livewire/
├── form-builder.blade.php  # Component view
└── ...
```

## Next Steps

- [FormBuilder](Form-Builder) - Form builder component
- [FormRenderer](Form-Renderer) - Form display component
- [FormsList](Forms-List) - Form listing component
