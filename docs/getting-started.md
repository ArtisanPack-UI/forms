---
title: Quick Start Guide
---

# Quick Start Guide

Get up and running with ArtisanPack UI Forms in minutes.

## Installation

Install the package via Composer:

```bash
composer require artisanpack-ui/forms
```

## Publish Assets

Publish the configuration file and migrations:

```bash
php artisan vendor:publish --provider="ArtisanPackUI\Forms\FormsServiceProvider"
```

## Run Migrations

```bash
php artisan migrate
```

## Create Your First Form

### Option 1: Using the Admin Interface

1. Navigate to `/admin/forms` (or your configured admin prefix)
2. Click "Create Form"
3. Add fields using the drag-and-drop form builder
4. Configure settings and notifications
5. Save and publish your form

### Option 2: Programmatically

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;

// Create a form
$form = Form::create([
    'name' => 'Contact Form',
    'slug' => 'contact',
    'description' => 'A simple contact form',
    'is_active' => true,
]);

// Add fields
FormField::create([
    'form_id' => $form->id,
    'type' => 'text',
    'name' => 'name',
    'label' => 'Your Name',
    'required' => true,
    'order' => 1,
]);

FormField::create([
    'form_id' => $form->id,
    'type' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'required' => true,
    'order' => 2,
]);

FormField::create([
    'form_id' => $form->id,
    'type' => 'textarea',
    'name' => 'message',
    'label' => 'Your Message',
    'required' => true,
    'order' => 3,
]);
```

## Display Your Form

Add the form to any Blade view:

```blade
<livewire:forms::form-renderer slug="contact" />
```

Or by form ID:

```blade
<livewire:forms::form-renderer :form-id="1" />
```

## Handle Submissions

Form submissions are automatically stored in the database. You can:

1. **View in Admin**: Navigate to `/admin/forms/{form}/submissions`
2. **Listen for Events**: Subscribe to the `FormSubmitted` event
3. **Configure Notifications**: Set up email notifications in the form settings

### Event Listener Example

```php
use ArtisanPackUI\Forms\Events\FormSubmitted;

class SendSlackNotification
{
    public function handle(FormSubmitted $event): void
    {
        $form = $event->form;
        $submission = $event->submission;

        // Send to Slack, log, or perform other actions
    }
}
```

## Next Steps

- [Installation Details](./installation/installation.md) - Complete installation guide
- [Form Builder](./usage/form-builder.md) - Learn about the form builder interface
- [Configuration](./installation/configuration.md) - Customize package behavior
- [Notifications](./usage/notifications.md) - Set up email notifications
