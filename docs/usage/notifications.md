---
title: Email Notifications
---

# Email Notifications

Configure email notifications to alert administrators and send autoresponders when forms are submitted.

## Notification Types

| Type | Description |
|------|-------------|
| **Admin** | Sent to administrators when form is submitted |
| **Autoresponder** | Sent to the person who submitted the form |

## Creating Notifications

### Via Admin Interface

1. Edit a form
2. Go to the "Notifications" tab
3. Click "Add Notification"
4. Configure settings
5. Save

### Programmatically

```php
use ArtisanPackUI\Forms\Models\FormNotification;

// Admin notification
FormNotification::create([
    'form_id' => $form->id,
    'type' => FormNotification::TYPE_ADMIN,
    'name' => 'Admin Alert',
    'recipient_type' => 'static',
    'recipients' => 'admin@example.com',
    'subject' => 'New submission: {form_name}',
    'message' => 'A new submission was received from {email}.',
    'is_active' => true,
]);

// Autoresponder
FormNotification::create([
    'form_id' => $form->id,
    'type' => FormNotification::TYPE_AUTORESPONDER,
    'name' => 'Thank You Email',
    'recipient_type' => 'field',
    'recipient_field' => 'email',
    'subject' => 'Thank you for contacting us',
    'message' => 'Hi {name}, we received your message.',
    'is_active' => true,
]);
```

## Notification Settings

### Recipients

| Setting | Description |
|---------|-------------|
| `recipient_type` | `static` (fixed emails) or `field` (from form field) |
| `recipients` | Comma-separated emails for static type |
| `recipient_field` | Field name for field type |
| `cc` | CC email addresses |
| `bcc` | BCC email addresses |

### From Address

| Setting | Description |
|---------|-------------|
| `from_email` | Sender email address |
| `from_name` | Sender name |
| `reply_to_type` | `static` or `field` |
| `reply_to_field` | Field for reply-to address |

### Content

| Setting | Description |
|---------|-------------|
| `subject` | Email subject (supports placeholders) |
| `message` | Email body (supports placeholders) |
| `include_submission_data` | Include all field values |

## Placeholders

Use placeholders in subject and message:

| Placeholder | Description |
|-------------|-------------|
| `{form_name}` | Form name |
| `{form_slug}` | Form slug |
| `{submission_number}` | Submission number |
| `{submission_date}` | Submission date |
| `{ip_address}` | Submitter's IP |
| `{field_name}` | Any field value (e.g., `{email}`, `{name}`) |

### Example

```php
FormNotification::create([
    'form_id' => $form->id,
    'type' => FormNotification::TYPE_ADMIN,
    'subject' => 'New {form_name} submission from {name}',
    'message' => <<<MESSAGE
        Hello,

        A new form submission was received:

        Name: {name}
        Email: {email}
        Message: {message}

        Submission ID: {submission_number}
        Date: {submission_date}
    MESSAGE,
]);
```

## Conditional Notifications

Send notifications based on field values:

```php
FormNotification::create([
    'form_id' => $form->id,
    'type' => FormNotification::TYPE_ADMIN,
    'name' => 'Sales Alert',
    'recipients' => 'sales@example.com',
    'conditions' => [
        [
            'field' => 'inquiry_type',
            'operator' => 'equals',
            'value' => 'sales',
        ],
    ],
]);
```

Available operators:

- `equals`
- `not_equals`
- `contains`
- `not_contains`
- `starts_with`
- `ends_with`
- `greater_than`
- `less_than`
- `is_empty`
- `is_not_empty`

## Email Templates

Customize the email template:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/emails/notification.blade.php`:

```blade
@component('mail::message')
# {{ $parsedSubject }}

{!! $parsedMessage !!}

@if ($notification->include_submission_data)
## Submission Data

{!! $submissionDataTable !!}
@endif

@if ($showIpAddress && $submission->ip_address)
---
*Submitted from: {{ $submission->ip_address }}*
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
```

## Queue Configuration

Notifications are queued by default for better performance:

```php
// config/artisanpack/forms.php
'notifications' => [
    'queue' => 'notifications', // Queue name
],
```

Ensure your queue worker processes this queue:

```bash
php artisan queue:work --queue=notifications
```

## Disabling Notifications

### Globally

```php
// config/artisanpack/forms.php
'notifications' => [
    'enabled' => false,
],
```

### Per Notification

```php
$notification->update(['is_active' => false]);
```

### Temporarily

```php
use ArtisanPackUI\Forms\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Skip notifications for this submission
$notificationService->withoutNotifications(function () use ($form, $data) {
    // Create submission without notifications
});
```

## Customizing Notifications

Use filter hooks to customize:

```php
use function addFilter;

// Modify the message
addFilter('ap.forms.notificationMessage', function ($message, $notification, $submission) {
    // Add tracking pixel
    return $message . '<img src="https://example.com/track/' . $submission->id . '">';
});

// Add custom headers
addFilter('forms.notification_headers', function ($headers, $notification) {
    $headers['X-Custom-Header'] = 'value';
    return $headers;
});
```

## Testing Notifications

Send a test notification:

```php
use ArtisanPackUI\Forms\Services\NotificationService;

$notificationService = app(NotificationService::class);
$notificationService->sendTest($notification, 'test@example.com');
```

## Next Steps

- [File Uploads](File-Uploads) - Handle uploaded files
- [Webhooks](Advanced-Webhooks) - Send data to external services
- [Submissions](Submissions) - Manage submissions
