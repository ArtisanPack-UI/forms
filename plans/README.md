# ArtisanPack UI Forms Package - Development Plan

**Purpose:** Complete technical specification for the artisanpack-ui/forms package
**For:** Claude Code implementation reference
**Created:** January 3, 2026
**Status:** Planning Complete

---

## Overview

The forms package provides a complete form builder system for Laravel applications, enabling users to create, manage, and collect submissions from custom forms. It follows the ArtisanPack UI ecosystem patterns and integrates seamlessly with other packages.

### Key Features

- **Visual Form Builder**: Drag-and-drop interface for creating forms
- **12+ Field Types**: Text, email, phone, textarea, select, checkbox, radio, date, file, hidden, and more
- **Conditional Logic**: Show/hide fields based on other field values
- **Multi-Step Forms**: Wizard-style forms with progress indicators
- **Multiple Email Notifications**: Configurable notifications per form (admin alerts, auto-responders)
- **Hook-Based Integrations**: Extensible via artisanpack-ui/hooks for third-party services
- **Spam Protection**: Honeypot fields and rate limiting
- **Form Cloning**: Duplicate existing forms for quick creation
- **CSV Export**: Export submissions for external processing
- **Dual-Mode Admin**: Works standalone or integrates with cms-framework

---

## Design Decisions

### Admin UI Integration
The package operates in two modes:
1. **Standalone Mode**: Provides its own admin routes and layouts
2. **CMS Mode**: Automatically integrates with cms-framework when installed (uses its layout, navigation, and permissions)

### Field Components
Forms use the `livewire-ui-components` package for rendering fields, ensuring visual consistency with the rest of the application and reducing code duplication.

### File Uploads
Form file uploads use separate storage from the media-library package, stored in a dedicated `form-uploads` disk. This keeps form attachments organized and allows for different retention policies.

### Theming
Forms inherit styling from the application's Tailwind/daisyUI theme automatically. No per-form styling options to maintain simplicity and consistency.

### Third-Party Integrations
Rather than building in specific integrations (ConvertKit, Mailchimp, etc.), the package exposes hooks via `artisanpack-ui/hooks` that other packages can use to add their own integrations. This keeps the core package lean and allows for unlimited extensibility.

### Spam Protection
Simple but effective: honeypot fields and rate limiting. No CAPTCHA dependencies to maintain privacy and simplicity. Sites needing stronger protection can add their own middleware.

---

## Planning Documents

| Document | Description |
|----------|-------------|
| [01-database-schema.md](01-database-schema.md) | Database tables, columns, indexes, and relationships |
| [02-models-and-relationships.md](02-models-and-relationships.md) | Eloquent models, casts, scopes, and relationships |
| [03-form-builder.md](03-form-builder.md) | Admin form builder Livewire component |
| [04-form-renderer.md](04-form-renderer.md) | Frontend form display and submission handling |
| [05-field-types.md](05-field-types.md) | All field types, configuration options, and validation |
| [06-conditional-logic.md](06-conditional-logic.md) | Field visibility rules and Alpine.js integration |
| [07-multi-step-forms.md](07-multi-step-forms.md) | Multi-step/wizard form architecture |
| [08-notifications.md](08-notifications.md) | Email notification system and templates |
| [09-integrations.md](09-integrations.md) | Hook-based integration system and events |
| [10-submissions-management.md](10-submissions-management.md) | Viewing, searching, filtering, and exporting submissions |
| [11-testing-strategy.md](11-testing-strategy.md) | Unit, feature, and browser test coverage |
| [12-implementation-order.md](12-implementation-order.md) | Development sequence and dependencies |
| [13-security-considerations.md](13-security-considerations.md) | Security best practices and input handling |

---

## Package Dependencies

### Required
- `php` ^8.2
- `illuminate/support` ^11.0|^12.0
- `livewire/livewire` ^3.0
- `artisanpack-ui/livewire-ui-components` ^1.0
- `artisanpack-ui/hooks` ^1.0
- `@artisanpack-ui/livewire-drag-and-drop` ^2.0 (NPM) - For accessible drag-and-drop in form builder

### Optional (Enhanced Features)
- `artisanpack-ui/cms-framework` - For integrated admin UI
- `artisanpack-ui/security` - For enhanced input sanitization (recommended)

---

## Internationalization (i18n)

All user-facing strings must be translatable using Laravel's `__()` helper function.

### Simple Approach

Wrap static strings in `__()`. Laravel will look for translations in JSON files or return the string as-is:

```blade
{{-- Static strings get wrapped in __() --}}
<button>{{ __('Submit') }}</button>
<span>{{ __('Required') }}</span>
<label>{{ __('Add Field') }}</label>

{{-- Dynamic/user content does NOT need __() --}}
<span>{{ $field->label }}</span>
<p>{{ $form->description }}</p>
```

### Translation File

Create `resources/lang/{locale}.json` for translations:

```json
{
    "Submit": "Enviar",
    "Required": "Obligatorio",
    "Add Field": "Agregar Campo",
    "Previous": "Anterior",
    "Next": "Siguiente"
}
```

### What to Translate

**DO translate (static UI strings):**
- Button labels: `{{ __('Submit') }}`, `{{ __('Save') }}`
- Form labels: `{{ __('Label') }}`, `{{ __('Placeholder') }}`
- Messages: `{{ __('No fields yet') }}`
- Validation messages: `{{ __('This field is required.') }}`

**DON'T translate (dynamic content):**
- User-provided field labels: `{{ $field->label }}`
- Form names/descriptions: `{{ $form->name }}`
- Database values: `{{ $submission->getValue('name') }}`

### Publishing Translations

Users can publish and customize translations:

```bash
php artisan vendor:publish --tag=forms-lang
```

---

## Configuration

The package publishes a `config/forms.php` configuration file:

```php
return [
    // Admin UI mode: 'standalone' or 'cms' (auto-detected)
    'admin_mode' => env('FORMS_ADMIN_MODE', 'auto'),

    // Field types configuration
    'field_types' => [
        // Extensible via hooks
    ],

    // File upload settings
    'uploads' => [
        'disk' => 'form-uploads',
        'max_size' => 10240, // KB (10MB)
        'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'],
    ],

    // Spam protection
    'spam_protection' => [
        'honeypot' => true,
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
    ],

    // Submission retention
    'submissions' => [
        'retention_days' => null, // null = keep forever
    ],
];
```

---

## Directory Structure

```
forms/
├── config/
│   └── forms.php
├── database/
│   └── migrations/
│       ├── create_forms_table.php
│       ├── create_form_fields_table.php
│       ├── create_form_steps_table.php
│       ├── create_form_submissions_table.php
│       ├── create_form_submission_values_table.php
│       ├── create_form_notifications_table.php
│       └── create_form_uploads_table.php
├── resources/
│   ├── views/
│   │   ├── admin/
│   │   │   ├── forms/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   └── submissions/
│   │   │       ├── index.blade.php
│   │   │       └── show.blade.php
│   │   ├── components/
│   │   │   ├── form-renderer.blade.php
│   │   │   └── fields/
│   │   │       ├── text.blade.php
│   │   │       ├── email.blade.php
│   │   │       └── ... (all field types)
│   │   └── emails/
│   │       └── submission-notification.blade.php
│   └── lang/
│       └── en/
│           └── forms.php
├── routes/
│   ├── web.php
│   └── api.php
├── src/
│   ├── Commands/
│   │   └── PruneSubmissionsCommand.php
│   ├── Contracts/
│   │   ├── FieldTypeInterface.php
│   │   └── FormRendererInterface.php
│   ├── Events/
│   │   ├── FormSubmitted.php
│   │   ├── FormCreated.php
│   │   └── FormUpdated.php
│   ├── Exceptions/
│   │   ├── FormNotFoundException.php
│   │   └── SubmissionFailedException.php
│   ├── Facades/
│   │   └── Forms.php
│   ├── FieldTypes/
│   │   ├── BaseFieldType.php
│   │   ├── TextField.php
│   │   ├── EmailField.php
│   │   └── ... (all field types)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── FormController.php
│   │   │   ├── SubmissionController.php
│   │   │   └── Api/
│   │   │       └── FormApiController.php
│   │   ├── Livewire/
│   │   │   ├── FormBuilder.php
│   │   │   ├── FormRenderer.php
│   │   │   ├── FieldEditor.php
│   │   │   ├── NotificationEditor.php
│   │   │   ├── SubmissionsList.php
│   │   │   └── SubmissionDetail.php
│   │   ├── Middleware/
│   │   │   └── FormRateLimiter.php
│   │   └── Requests/
│   │       ├── StoreFormRequest.php
│   │       └── UpdateFormRequest.php
│   ├── Jobs/
│   │   └── SendFormNotification.php
│   ├── Mail/
│   │   └── FormSubmissionNotification.php
│   ├── Models/
│   │   ├── Form.php
│   │   ├── FormField.php
│   │   ├── FormStep.php
│   │   ├── FormSubmission.php
│   │   ├── FormSubmissionValue.php
│   │   ├── FormNotification.php
│   │   └── FormUpload.php
│   ├── Services/
│   │   ├── FormService.php
│   │   ├── SubmissionService.php
│   │   ├── ValidationService.php
│   │   ├── NotificationService.php
│   │   └── ExportService.php
│   ├── Traits/
│   │   └── HasForms.php
│   ├── Forms.php
│   ├── FormsServiceProvider.php
│   └── helpers.php
├── tests/
│   ├── Feature/
│   │   ├── FormBuilderTest.php
│   │   ├── FormRendererTest.php
│   │   ├── SubmissionTest.php
│   │   ├── NotificationTest.php
│   │   └── ConditionalLogicTest.php
│   ├── Unit/
│   │   ├── FieldTypeTest.php
│   │   ├── ValidationServiceTest.php
│   │   └── ExportServiceTest.php
│   ├── Pest.php
│   └── TestCase.php
├── plans/                          # This directory
│   ├── README.md
│   ├── 01-database-schema.md
│   └── ...
├── .gitattributes
├── .gitignore
├── CHANGELOG.md
├── composer.json
├── LICENSE
├── phpunit.xml
└── README.md
```

---

## Related Documents

- Main ecosystem: `artisanpack-ui-dev/plans/README.md`
- Package specifications: `artisanpack-ui-dev/plans/04-package-specifications.md`
- UX principles: `artisanpack-ui-dev/plans/05-ux-principles.md`
- Visual editor (for form block): `artisanpack-ui-dev/plans/03-visual-editor-spec.md`

---

## Quick Reference

### Namespace
`ArtisanPackUI\Forms`

### Composer Package
`artisanpack-ui/forms`

### Service Provider
`ArtisanPackUI\Forms\FormsServiceProvider`

### Facade
`ArtisanPackUI\Forms\Facades\Forms`

### Config File
`config/forms.php`

---

*Last Updated: January 3, 2026*
