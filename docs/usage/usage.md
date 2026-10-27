---
title: Usage Overview
---

# Usage Overview

ArtisanPack UI Forms provides two main interfaces for working with forms:

1. **Admin Interface** - A web-based UI for creating and managing forms
2. **Programmatic API** - PHP classes and services for custom integrations

## Admin Interface

The admin interface is available at `/admin/forms` (configurable) and provides:

- **Form Builder** - Drag-and-drop interface for creating forms
- **Form List** - View and manage all forms
- **Submissions** - View, search, and export form submissions
- **Notifications** - Configure email notifications

### Accessing the Admin

By default, the admin routes use the `web` and `auth` middleware. Ensure users are authenticated to access the admin interface.

```php
// config/artisanpack/forms.php
'admin' => [
    'prefix' => 'admin/forms',
    'middleware' => ['web', 'auth'],
],
```

## Displaying Forms

Use the `FormRenderer` Livewire component to display forms:

```php
use ArtisanPackUI\Forms\Services\FormService;

// By slug (recommended)
$form = app(FormService::class)->getBySlug('contact-us');

// By form ID
$form = app(FormService::class)->getById(1);
```

```blade
<livewire:form-renderer :form="$form" />

{{-- With starting values for dynamically populated fields --}}
<livewire:form-renderer :form="$form" :prefill="['plan' => 'pro']" />
```

The success message and redirect URL are form settings, not component props. Set them in the form's **Settings** tab, or on the model:

```php
$form->update([
    'success_message' => 'Thanks for reaching out!',
    'redirect_url' => '/thank-you',
]);
```

A redirect URL makes the renderer dispatch a `redirect-to` event, which your page handles (see [Form Renderer](Usage-Form-Renderer#redirect-after-submission)).

## Creating Forms Programmatically

For custom integrations, create forms using the models directly:

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;

// Create a form
$form = Form::create([
    'name' => 'Newsletter Signup',
    'slug' => 'newsletter',
    'description' => 'Subscribe to our newsletter',
    'is_active' => true,
    'success_message' => 'Thank you for subscribing!',
]);

// Add fields
FormField::create([
    'form_id' => $form->id,
    'type' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'placeholder' => 'you@example.com',
    'required' => true,
    'order' => 1,
]);
```

## Using Services

The package provides service classes for complex operations:

```php
use ArtisanPackUI\Forms\Services\FormService;
use ArtisanPackUI\Forms\Services\SubmissionService;

// Inject services
public function __construct(
    private FormService $formService,
    private SubmissionService $submissionService,
) {}

// Get form by slug
$form = $this->formService->getBySlug('contact');

// Create submission
$submission = $this->submissionService->create($form, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'message' => 'Hello!',
]);
```

## Event Handling

Subscribe to events for custom processing:

```php
use ArtisanPackUI\Forms\Events\FormSubmitted;
use Illuminate\Support\Facades\Event;

// In a service provider
Event::listen(FormSubmitted::class, function ($event) {
    $form = $event->form;
    $submission = $event->submission;

    // Custom processing
    Log::info("New submission for form: {$form->name}");
});
```

Available events:

- `FormCreated` - Fired when a form is created
- `FormUpdated` - Fired when a form is updated
- `FormDeleted` - Fired when a form is deleted
- `FormSubmitted` - Fired when a form is submitted
- `SubmissionUpdated` - Fired when a submission is updated
- `SubmissionDeleted` - Fired when a submission is deleted

## Filter Hooks

Use filter hooks to customize behavior:

```php
use function addFilter;

// Customize notification message
addFilter('ap.forms.notificationMessage', function ($message, $notification, $submission) {
    return $message . "\n\nProcessed at: " . now()->format('Y-m-d H:i:s');
});

// Add custom validation
addFilter('ap.forms.validationRules', function ($rules, $form) {
    // Add custom rules
    $rules['email'] = 'required|email|ends_with:@company.com';
    return $rules;
});
```

## Field Types

The package has 36 built-in field types:

| Type | Description |
|------|-------------|
| `text` | Single-line text input |
| `email` | Email address input, with optional confirmation |
| `phone` | Phone number input |
| `number` | Numeric input |
| `url` | URL input |
| `textarea` | Multi-line text area, optionally a rich text editor |
| `hidden` | Hidden field |
| `select` | Dropdown selection, optionally searchable |
| `select_multiple` | Multi-select |
| `radio` | Radio button group |
| `checkbox` | Single checkbox |
| `toggle` | On/off switch |
| `checkbox_group` | Multiple checkboxes |
| `file` | File upload, single or multiple |
| `date` | Date picker |
| `time` | Time picker |
| `datetime` | Date and time picker |
| `date_range` | Start and end dates |
| `rating` | Star rating |
| `slider` | Range slider |
| `color` | Color picker |
| `nps` | Net Promoter Score (0–10) |
| `matrix` | Likert scale / matrix |
| `ranking` | Order choices by preference |
| `name` | Name, in parts |
| `address` | Postal address |
| `list` | Repeatable rows |
| `calculation` | Read-only number worked out from other fields |
| `signature` | Drawn or typed signature |
| `consent` | Consent checkbox that records what was agreed to |
| `captcha` | CAPTCHA challenge |
| `section` | Groups the fields after it |
| `heading` | Section heading |
| `paragraph` | Formatted text |
| `divider` | Visual divider |
| `html` | Static HTML content |

See [Form Builder](Usage-Form-Builder#field-types) for each type's settings, and [Field Type Handlers](Advanced-Field-Type-Handlers) to add your own.

## Next Steps

- [Form Builder](Form-Builder) - Create forms with the drag-and-drop builder
- [Form Renderer](Form-Renderer) - Display forms on your site
- [Submissions](Submissions) - Manage form submissions
- [Notifications](Notifications) - Configure email notifications
