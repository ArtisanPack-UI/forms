---
title: Livewire Components Overview
---

# Livewire Components

Pre-built Livewire components for form management and display.

## Component Topics

- [Components Overview](Components-Components) - Introduction to components
- [FormBuilder](Components-Form-Builder) - Drag-and-drop form builder
- [FormRenderer](Components-Form-Renderer) - Form display component
- [FormsList](Components-Forms-List) - Form listing component
- [SubmissionsList](Components-Submissions-List) - Submission listing
- [SubmissionDetail](Components-Submission-Detail) - Submission detail view
- [NotificationEditor](Components-Notification-Editor) - Notification configuration

## Quick Reference

### Admin Components

```blade
{{-- Form management --}}
<livewire:forms-list />
<livewire:form-builder :form="$form" />

{{-- Submission management --}}
<livewire:submissions-list :form-id="$form->id" />
<livewire:submission-detail :submission="$submission" />

{{-- Notification editor --}}
<livewire:notification-editor :form="$form" />
```

### Public Components

```blade
{{-- Display form ($form is a Form model) --}}
<livewire:form-renderer :form="$form" />
```

For detailed component documentation, see [Components Overview](Components-Components).
