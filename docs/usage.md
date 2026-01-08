---
title: Usage Overview
---

# Usage

Learn how to use ArtisanPack UI Forms to create, display, and manage forms in your Laravel application.

## Usage Topics

- [Usage Overview](./usage/usage.md) - Introduction to using the package
- [Form Builder](./usage/form-builder.md) - Create forms with the drag-and-drop builder
- [Form Renderer](./usage/form-renderer.md) - Display forms on your site
- [Submissions](./usage/submissions.md) - Manage form submissions
- [Email Notifications](./usage/notifications.md) - Configure email notifications
- [File Uploads](./usage/file-uploads.md) - Handle file attachments
- [Multi-Step Forms](./usage/multi-step-forms.md) - Create multi-step forms
- [Conditional Logic](./usage/conditional-logic.md) - Show/hide fields dynamically

## Quick Reference

### Display a Form

```blade
{{-- By slug --}}
<livewire:forms::form-renderer slug="contact" />

{{-- By ID --}}
<livewire:forms::form-renderer :form-id="1" />
```

### Access Admin Interface

Navigate to `/admin/forms` (or your configured prefix) to:

- Create and edit forms
- View and export submissions
- Configure notifications

For detailed usage guides, start with the [Usage Overview](./usage/usage.md).
