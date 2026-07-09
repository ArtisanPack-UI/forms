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

## Component Namespacing

All components are namespaced under `forms::`:

```blade
<livewire:forms::form-renderer />
<livewire:forms::forms-list />
<livewire:forms::form-builder />
```

## Component Categories

### Admin Components

These components are used in the admin interface for form management:

```blade
{{-- List all forms --}}
<livewire:forms::forms-list />

{{-- Build/edit a form --}}
<livewire:forms::form-builder :form="$form" />

{{-- List submissions for a form --}}
<livewire:forms::submissions-list :form="$form" />

{{-- View submission details --}}
<livewire:forms::submission-detail :submission="$submission" />

{{-- Configure notifications --}}
<livewire:forms::notification-editor :form="$form" />
```

### Public Components

These components are for end-user facing pages:

```blade
{{-- Display a form --}}
<livewire:forms::form-renderer slug="contact" />
```

## Publishing Views

Customize component views by publishing:

```bash
php artisan vendor:publish --tag=forms-views
```

Views are published to `resources/views/vendor/forms/livewire/`.

## Component Properties

Most components accept properties for customization:

```blade
<livewire:forms::form-renderer
    slug="contact"
    success-message="Thank you!"
    :show-title="false"
/>
```

## Events

Components emit Livewire events you can listen to:

```javascript
Livewire.on('form-submitted', (event) => {
    console.log('Form submitted:', event.formId);
});

Livewire.on('submission-deleted', (event) => {
    console.log('Submission deleted:', event.submissionId);
});
```

## Styling

Components use minimal default styling and can be customized via:

1. **CSS Classes** - Pass custom classes to components
2. **Published Views** - Edit the Blade templates
3. **CSS Variables** - Override CSS custom properties

```blade
<livewire:forms::form-renderer
    slug="contact"
    css-class="my-custom-form bg-white p-6 rounded-lg"
/>
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
