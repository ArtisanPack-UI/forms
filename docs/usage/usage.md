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

```blade
{{-- By slug (recommended) --}}
<livewire:forms::form-renderer slug="contact-us" />

{{-- By form ID --}}
<livewire:forms::form-renderer :form-id="1" />

{{-- With custom success message --}}
<livewire:forms::form-renderer
    slug="contact"
    success-message="Thanks for reaching out!"
/>

{{-- With redirect after submission --}}
<livewire:forms::form-renderer
    slug="contact"
    redirect-url="/thank-you"
/>
```

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
addFilter('forms.notification_message', function ($message, $notification, $submission) {
    return $message . "\n\nProcessed at: " . now()->format('Y-m-d H:i:s');
});

// Add custom validation
addFilter('forms.validation_rules', function ($rules, $form) {
    // Add custom rules
    $rules['email'] = 'required|email|ends_with:@company.com';
    return $rules;
});
```

## Field Types

The package supports 20+ field types:

| Type | Description |
|------|-------------|
| `text` | Single-line text input |
| `email` | Email address input |
| `textarea` | Multi-line text area |
| `select` | Dropdown selection |
| `checkbox` | Single checkbox |
| `checkbox_group` | Multiple checkboxes |
| `radio` | Radio button group |
| `file` | File upload |
| `date` | Date picker |
| `time` | Time picker |
| `datetime` | Date and time picker |
| `number` | Numeric input |
| `phone` | Phone number input |
| `url` | URL input |
| `password` | Password input |
| `hidden` | Hidden field |
| `html` | Static HTML content |
| `heading` | Section heading |
| `divider` | Visual divider |

## Next Steps

- [Form Builder](./form-builder.md) - Create forms with the drag-and-drop builder
- [Form Renderer](./form-renderer.md) - Display forms on your site
- [Submissions](./submissions.md) - Manage form submissions
- [Notifications](./notifications.md) - Configure email notifications
