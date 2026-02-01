---
title: FAQ
---

# Frequently Asked Questions

Common questions about ArtisanPack UI Forms.

## General

### What is ArtisanPack UI Forms?

ArtisanPack UI Forms is a comprehensive form builder and management package for Laravel applications. It provides a drag-and-drop form builder, submission management, email notifications, file uploads, multi-step forms, conditional logic, and webhook integrations.

### What Laravel versions are supported?

The package supports Laravel 11 and Laravel 12.

### Does it work with Livewire 3?

Yes, the package is built specifically for Livewire 3 and takes full advantage of its features.

## Installation

### How do I install the package?

```bash
composer require artisanpack-ui/forms
php artisan vendor:publish --provider="ArtisanPackUI\Forms\FormsServiceProvider"
php artisan migrate
```

### Can I customize the admin route prefix?

Yes, update the configuration:

```php
// config/artisanpack/forms.php
'admin' => [
    'prefix' => 'my-custom-prefix',
],
```

Or use an environment variable:

```env
FORMS_ADMIN_PREFIX=my-custom-prefix
```

### How do I add authentication to the admin?

By default, the admin routes use `['web', 'auth']` middleware. Customize in config:

```php
'admin' => [
    'middleware' => ['web', 'auth', 'admin'],
],
```

## Forms

### How do I display a form?

```blade
<livewire:forms::form-renderer slug="contact" />
```

### Can I create forms programmatically?

Yes:

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;

$form = Form::create([
    'name' => 'Contact Form',
    'slug' => 'contact',
    'is_active' => true,
]);

FormField::create([
    'form_id' => $form->id,
    'type' => 'email',
    'name' => 'email',
    'label' => 'Email',
    'required' => true,
    'order' => 1,
]);
```

### How many field types are available?

The package includes 20+ field types including text, email, textarea, select, checkbox, radio, file upload, date, time, and more.

### Can I add custom field types?

Yes, use the filter hook:

```php
addFilter('forms.field_types', function ($types) {
    $types['my-field'] = [
        'label' => 'My Custom Field',
        'view' => 'my-package::fields.my-field',
    ];
    return $types;
});
```

## Submissions

### Where are submissions stored?

Submissions are stored in the `form_submissions` table with field values in `form_submission_values`.

### How do I export submissions?

Via admin interface or programmatically:

```php
$exportService = app(ExportService::class);
$csv = $exportService->toCsv($form);
```

### How long are submissions kept?

By default, forever. Configure retention:

```php
'submissions' => [
    'retention_days' => 365,
],
```

### Can I delete old submissions automatically?

Yes, schedule the prune command:

```php
Schedule::command('forms:prune-submissions')->daily();
```

## File Uploads

### Where are uploaded files stored?

In the `form-uploads` disk, which defaults to `storage/app/form-uploads/`.

### What file types are allowed?

Configure in `config/artisanpack/forms.php`:

```php
'uploads' => [
    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        // Add more...
    ],
],
```

### What's the maximum file size?

Default is 10MB. Configure:

```php
'uploads' => [
    'max_size' => 20480, // KB (20MB)
],
```

## Notifications

### How do I set up email notifications?

Via the admin interface or programmatically:

```php
FormNotification::create([
    'form_id' => $form->id,
    'type' => 'admin',
    'recipients' => 'admin@example.com',
    'subject' => 'New submission',
    'message' => 'You have a new form submission.',
]);
```

### Can I send autoresponders?

Yes, create an autoresponder notification:

```php
FormNotification::create([
    'form_id' => $form->id,
    'type' => 'autoresponder',
    'recipient_type' => 'field',
    'recipient_field' => 'email',
    'subject' => 'Thank you',
    'message' => 'Thanks for contacting us!',
]);
```

### Are notifications queued?

Yes, notifications are queued by default. Configure the queue:

```php
'notifications' => [
    'queue' => 'notifications',
],
```

## Multi-Step Forms

### How do I create a multi-step form?

Set `is_multi_step` to true and add steps:

```php
$form = Form::create([
    'name' => 'Registration',
    'is_multi_step' => true,
]);

FormStep::create([
    'form_id' => $form->id,
    'title' => 'Step 1',
    'order' => 1,
]);
```

### Can steps be conditional?

Yes, add conditions to steps:

```php
FormStep::create([
    'form_id' => $form->id,
    'title' => 'Business Info',
    'conditions' => [
        'rules' => [
            ['field' => 'account_type', 'operator' => 'equals', 'value' => 'business'],
        ],
    ],
]);
```

## Security

### How is spam prevented?

The package includes honeypot fields and rate limiting. You can also integrate reCAPTCHA.

### Is there authorization?

Yes, policies control access. Enable ownership enforcement:

```php
'authorization' => [
    'restrict_by_owner' => true,
],
```

### Are file uploads secure?

Yes, files are stored privately and validated for MIME type and size.

## Customization

### Can I customize the views?

Yes, publish and edit:

```bash
php artisan vendor:publish --tag=forms-views
```

### Can I extend the Livewire components?

Yes, extend and register your own:

```php
class CustomFormRenderer extends FormRenderer { }

Livewire::component('custom-form-renderer', CustomFormRenderer::class);
```

## Next Steps

- [Troubleshooting](Troubleshooting) - Common issues
- [Installation](Installation-Installation) - Getting started
- [Configuration](Installation-Configuration) - All settings
