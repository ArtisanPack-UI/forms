---
title: Livewire Components Overview
---

# Livewire Components

Pre-built Livewire components for form management and display.

## Component Topics

- [Components Overview](./components/components.md) - Introduction to components
- [FormBuilder](./components/form-builder.md) - Drag-and-drop form builder
- [FormRenderer](./components/form-renderer.md) - Form display component
- [FormsList](./components/forms-list.md) - Form listing component
- [SubmissionsList](./components/submissions-list.md) - Submission listing
- [SubmissionDetail](./components/submission-detail.md) - Submission detail view
- [NotificationEditor](./components/notification-editor.md) - Notification configuration

## Quick Reference

### Admin Components

```blade
{{-- Form management --}}
<livewire:forms::forms-list />
<livewire:forms::form-builder :form="$form" />

{{-- Submission management --}}
<livewire:forms::submissions-list :form="$form" />
<livewire:forms::submission-detail :submission="$submission" />

{{-- Notification editor --}}
<livewire:forms::notification-editor :form="$form" />
```

### Public Components

```blade
{{-- Display form --}}
<livewire:forms::form-renderer slug="contact" />
```

For detailed component documentation, see [Components Overview](./components/components.md).
