---
title: Usage Overview
---

# Usage

Learn how to use ArtisanPack UI Forms to create, display, and manage forms in your Laravel application.

## Usage Topics

- [Usage Overview](Usage-Usage) - Introduction to using the package
- [Form Builder](Usage-Form-Builder) - Create forms with the drag-and-drop builder
- [Form Renderer](Usage-Form-Renderer) - Display forms on your site
- [Submissions](Usage-Submissions) - Manage form submissions
- [Email Notifications](Usage-Notifications) - Configure email notifications
- [File Uploads](Usage-File-Uploads) - Handle file attachments
- [Multi-Step Forms](Usage-Multi-Step-Forms) - Create multi-step forms
- [Conditional Logic](Usage-Conditional-Logic) - Show/hide fields dynamically

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

For detailed usage guides, start with the [Usage Overview](Usage-Usage).
