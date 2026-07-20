---
title: Webhooks
---

# Webhooks

Send form submission data to external services automatically.

## Overview

Webhooks allow you to send form submission data to external services like:

- CRM systems (Salesforce, HubSpot)
- Marketing tools (Mailchimp, ActiveCampaign)
- Automation platforms (Zapier, Make)
- Custom APIs

## Configuration

### Global Webhook

Configure a global webhook for all form submissions:

```php
// config/artisanpack/forms.php
'webhooks' => [
    'enabled' => env('FORMS_WEBHOOKS_ENABLED', false),
    'url' => env('FORMS_WEBHOOK_URL'),
    'secret' => env('FORMS_WEBHOOK_SECRET'),
    'queue' => env('FORMS_WEBHOOK_QUEUE', 'default'),
    'timeout' => env('FORMS_WEBHOOK_TIMEOUT', 30),
    'retry_times' => 3,
    'retry_backoff' => [10, 60, 300],
],
```

Environment variables:

```env
FORMS_WEBHOOKS_ENABLED=true
FORMS_WEBHOOK_URL=https://example.com/webhook
FORMS_WEBHOOK_SECRET=your-secret-key-here
FORMS_WEBHOOK_QUEUE=webhooks
FORMS_WEBHOOK_TIMEOUT=30
```

### Per-Form Webhooks

Configure webhooks on individual forms:

```php
$form = Form::create([
    'name' => 'Contact Form',
    'slug' => 'contact',
    'settings' => [
        'webhook' => [
            'enabled' => true,
            'url' => 'https://api.example.com/forms/contact',
            'secret' => 'form-specific-secret',
        ],
    ],
]);
```

## Payload Structure

Webhooks send JSON payloads:

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
            "message": "Hello, I have a question..."
        },
        "metadata": {
            "source": "landing-page"
        },
        "submitted_at": "2024-01-15T10:30:00Z"
    }
}
```

### Customize Payload

Use filters to modify the payload:

```php
use function addFilter;

addFilter('ap.forms.webhookPayload', function ($payload, $form, $submission) {
    // Add custom data
    $payload['custom'] = [
        'campaign' => $submission->getMetadata('utm_campaign'),
        'source' => $submission->getMetadata('utm_source'),
    ];

    // Remove sensitive fields
    unset($payload['submission']['data']['password']);

    // Rename fields
    $payload['submission']['data']['full_name'] = $payload['submission']['data']['name'];
    unset($payload['submission']['data']['name']);

    return $payload;
});
```

## Request Signing

Webhooks are signed with HMAC-SHA256 for verification:

```
X-Signature: sha256=abc123...
X-Timestamp: 1705312200
```

### Verify Signature

In your receiving endpoint:

```php
public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('X-Signature');
    $timestamp = $request->header('X-Timestamp');

    // Verify timestamp (prevent replay attacks)
    if (abs(time() - $timestamp) > 300) {
        abort(401, 'Request too old');
    }

    // Verify signature
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    if (!hash_equals($expectedSignature, $signature)) {
        abort(401, 'Invalid signature');
    }

    // Process the webhook
    $data = json_decode($payload, true);
    // ...
}
```

## Retry Logic

Failed webhooks are retried with exponential backoff:

| Attempt | Delay |
|---------|-------|
| 1st retry | 10 seconds |
| 2nd retry | 60 seconds |
| 3rd retry | 300 seconds (5 min) |

Configure retries:

```php
'webhooks' => [
    'retry_times' => 3,
    'retry_backoff' => [10, 60, 300],
],
```

## Error Handling

Failed webhooks are logged:

```php
// In logs
[error] Webhook delivery failed: {
    "form_id": 1,
    "submission_id": 123,
    "url": "https://example.com/webhook",
    "status": 500,
    "response": "Internal Server Error"
}
```

Listen for failures:

```php
use ArtisanPackUI\Forms\Jobs\SendWebhook;

SendWebhook::class::failing(function ($event) {
    // Notify admin
    Notification::send(Admin::all(), new WebhookFailedNotification(
        $event->job->form,
        $event->exception
    ));
});
```

## Privacy Settings

Control what data is included:

```php
'privacy' => [
    'include_ip_address' => false,
    'include_user_agent' => false,
],
```

With these disabled, IP and user agent are excluded from webhook payloads.

## Queue Configuration

Webhooks are queued for reliability:

```php
'webhooks' => [
    'queue' => 'webhooks',
],
```

Run a dedicated worker:

```bash
php artisan queue:work --queue=webhooks
```

## Testing Webhooks

### Local Testing

Use tools like [webhook.site](https://webhook.site) or ngrok:

```env
FORMS_WEBHOOK_URL=https://webhook.site/your-uuid
```

### Unit Tests

```php
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use Illuminate\Support\Facades\Http;

test('webhook sends correct payload', function () {
    Http::fake();

    $form = Form::factory()->create();
    $submission = FormSubmission::factory()->create(['form_id' => $form->id]);

    $job = new SendWebhook($form, $submission, 'https://example.com/webhook', 'secret');
    $job->handle();

    Http::assertSent(function ($request) use ($form, $submission) {
        $data = $request->data();

        return $request->url() === 'https://example.com/webhook'
            && $data['form']['id'] === $form->id
            && $data['submission']['id'] === $submission->id;
    });
});
```

## Integration Examples

### Zapier

```env
FORMS_WEBHOOK_URL=https://hooks.zapier.com/hooks/catch/xxxxx/xxxxx/
```

### Make (Integromat)

```env
FORMS_WEBHOOK_URL=https://hook.us1.make.com/xxxxx
```

### Custom API

```php
addFilter('ap.forms.webhookPayload', function ($payload, $form, $submission) {
    // Transform to API-specific format
    return [
        'api_key' => config('services.my_api.key'),
        'contact' => [
            'email' => $payload['submission']['data']['email'],
            'name' => $payload['submission']['data']['name'],
        ],
    ];
});
```

## Next Steps

- [Spam Protection](Spam-Protection) - Protect forms from abuse
- [Events](Api-Events) - Event-driven integrations
- [Configuration](Installation-Configuration) - All configuration options
