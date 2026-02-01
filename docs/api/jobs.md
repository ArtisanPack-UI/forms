---
title: Jobs
---

# Jobs

Queue job classes for ArtisanPack UI Forms.

## SendFormNotification

Sends email notifications for form submissions.

### Usage

```php
use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;

// Dispatch the job
SendFormNotification::dispatch($notification, $submission);

// Dispatch to specific queue
SendFormNotification::dispatch($notification, $submission)
    ->onQueue('notifications');
```

### Job Properties

| Property | Type | Description |
|----------|------|-------------|
| `$notification` | FormNotification | Notification configuration |
| `$submission` | FormSubmission | Form submission data |

### Configuration

Configure the notification queue in `config/artisanpack/forms.php`:

```php
'notifications' => [
    'queue' => 'notifications',
],
```

### Handling Failures

The job implements retry logic:

```php
public int $tries = 3;

public int $backoff = 60; // seconds

public function failed(Throwable $exception): void
{
    Log::error('Failed to send form notification', [
        'notification_id' => $this->notification->id,
        'submission_id' => $this->submission->id,
        'error' => $exception->getMessage(),
    ]);
}
```

### Testing

```php
use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use Illuminate\Support\Facades\Queue;

test('notification job is dispatched', function () {
    Queue::fake();

    // Trigger form submission...

    Queue::assertPushed(SendFormNotification::class, function ($job) {
        return $job->notification->id === 1;
    });
});
```

---

## SendWebhook

Sends webhook payloads to external services.

### Usage

```php
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;

// Dispatch webhook
SendWebhook::dispatch($form, $submission, $webhookUrl, $secret);
```

### Job Properties

| Property | Type | Description |
|----------|------|-------------|
| `$form` | Form | Form model |
| `$submission` | FormSubmission | Submission data |
| `$url` | string | Webhook endpoint URL |
| `$secret` | string\|null | Secret for signing |

### Payload Structure

```json
{
    "event": "form.submitted",
    "timestamp": "2024-01-15T10:30:00Z",
    "form": {
        "id": 1,
        "name": "Contact Form",
        "slug": "contact"
    },
    "submission": {
        "id": 123,
        "submission_number": "FORM-2024-0001",
        "data": {
            "name": "John Doe",
            "email": "john@example.com",
            "message": "Hello!"
        },
        "submitted_at": "2024-01-15T10:30:00Z"
    }
}
```

### Request Signing

Webhooks are signed with HMAC-SHA256:

```php
$signature = hash_hmac('sha256', $payload, $secret);
// Sent as X-Signature header
```

Verify in your receiving endpoint:

```php
$expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
$receivedSignature = $request->header('X-Signature');

if (!hash_equals($expectedSignature, $receivedSignature)) {
    abort(401, 'Invalid signature');
}
```

### Configuration

```php
// config/artisanpack/forms.php
'webhooks' => [
    'enabled' => true,
    'url' => env('FORMS_WEBHOOK_URL'),
    'secret' => env('FORMS_WEBHOOK_SECRET'),
    'queue' => 'webhooks',
    'timeout' => 30,
    'retry_times' => 3,
    'retry_backoff' => [10, 60, 300], // seconds
],
```

### Retry Logic

The job implements exponential backoff:

```php
public int $tries = 3;

public function backoff(): array
{
    return config('artisanpack.forms.webhooks.retry_backoff', [10, 60, 300]);
}
```

### Customizing Payload

Use filters to modify the webhook payload:

```php
use function addFilter;

addFilter('forms.webhook_payload', function ($payload, $form, $submission) {
    // Add custom data
    $payload['custom'] = [
        'source' => 'website',
        'campaign' => $submission->getMetadata('utm_campaign'),
    ];

    // Remove sensitive data
    unset($payload['submission']['data']['password']);

    return $payload;
});
```

### Privacy Options

Configure what's included in webhooks:

```php
// config/artisanpack/forms.php
'privacy' => [
    'include_ip_address' => false, // Exclude IP from webhooks
    'include_user_agent' => false, // Exclude user agent
],
```

### Handling Failures

```php
public function failed(Throwable $exception): void
{
    Log::error('Webhook delivery failed', [
        'form_id' => $this->form->id,
        'submission_id' => $this->submission->id,
        'url' => $this->url,
        'error' => $exception->getMessage(),
    ]);

    // Optionally notify admin
    Notification::send(
        Admin::all(),
        new WebhookFailedNotification($this->form, $exception)
    );
}
```

### Testing

```php
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('webhook is dispatched on submission', function () {
    Queue::fake();

    // Configure webhook
    config(['artisanpack.forms.webhooks.enabled' => true]);
    config(['artisanpack.forms.webhooks.url' => 'https://example.com/webhook']);

    // Submit form...

    Queue::assertPushed(SendWebhook::class);
});

test('webhook sends correct payload', function () {
    Http::fake();

    $job = new SendWebhook($form, $submission, 'https://example.com/webhook', 'secret');
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/webhook'
            && $request->hasHeader('X-Signature')
            && $request['event'] === 'form.submitted';
    });
});
```

## Running Queue Workers

Ensure queue workers are running for jobs to process:

```bash
# Single worker
php artisan queue:work

# Specific queues
php artisan queue:work --queue=notifications,webhooks

# With Supervisor (production)
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
command=php /var/www/html/artisan queue:work --queue=notifications,webhooks
```

## Next Steps

- [Events](Events) - Event documentation
- [Webhooks](Advanced-Webhooks) - Webhook configuration
- [Notifications](Usage-Notifications) - Email notifications
