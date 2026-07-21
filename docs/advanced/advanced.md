---
title: Advanced Overview
---

# Advanced Overview

This section covers advanced features, integrations, and customization options.

## Topics

- [Webhooks](Webhooks) - Send form data to external services
- [Spam Protection](Spam-Protection) - Protect forms from spam and abuse
- [Customization](Customization) - Extend and customize the package
- [Artisan Commands](Artisan-Commands) - Command-line tools

## Advanced Features

### Webhooks

Send form submissions to external services:

```php
// config/artisanpack/forms.php
'webhooks' => [
    'enabled' => true,
    'url' => 'https://example.com/webhook',
    'secret' => 'your-secret-key',
],
```

### Filter Hooks

Customize behavior at runtime:

```php
use function addFilter;

// Modify validation rules
addFilter('ap.forms.validationRules', function ($rules, $form) {
    $rules['email'] = 'required|email|unique:users,email';
    return $rules;
});

// Modify webhook payload
addFilter('ap.forms.webhookPayload', function ($payload, $form, $submission) {
    $payload['custom_field'] = 'value';
    return $payload;
});
```

### Custom Field Types

Register custom field types:

```php
use function addFilter;

addFilter('ap.forms.fieldTypes', function ($types) {
    $types['color-picker'] = [
        'label' => 'Color Picker',
        'icon' => 'palette',
        'category' => 'advanced',
        'view' => 'my-package::fields.color-picker',
    ];
    return $types;
});
```

### Event-Driven Processing

Process submissions with events:

```php
use ArtisanPackUI\Forms\Events\FormSubmitted;

Event::listen(FormSubmitted::class, function ($event) {
    // Send to CRM
    CRM::createLead($event->submission->getFormData());

    // Add to mailing list
    Newsletter::subscribe($event->submission->getValue('email'));
});
```

### Authorization Customization

Override default policies:

```php
// app/Policies/CustomFormPolicy.php
class CustomFormPolicy extends FormPolicy
{
    public function update(User $user, Form $form): bool
    {
        return $user->hasPermission('edit-forms')
            || parent::update($user, $form);
    }
}
```

### Privacy Compliance

Configure for GDPR/privacy requirements:

```php
'privacy' => [
    'submission' => [
        'include_ip' => false,
        'anonymize_ip' => true,
        'include_user_agent' => false,
    ],
],
```

### Performance Optimization

- Queue notifications and webhooks
- Configure submission retention
- Use database indexes

```php
'notifications' => [
    'queue' => 'notifications',
],
'webhooks' => [
    'queue' => 'webhooks',
],
'submissions' => [
    'retention_days' => 365,
],
```

## Integration Examples

### Zapier Integration

```php
// Send to Zapier webhook
'webhooks' => [
    'url' => 'https://hooks.zapier.com/hooks/catch/xxxxx/xxxxx/',
],
```

### Slack Notifications

```php
Event::listen(FormSubmitted::class, function ($event) {
    Http::post(config('services.slack.webhook'), [
        'text' => "New form submission: {$event->form->name}",
        'attachments' => [[
            'fields' => collect($event->submission->getFormData())
                ->map(fn ($v, $k) => ['title' => $k, 'value' => $v])
                ->values()
                ->toArray(),
        ]],
    ]);
});
```

### CRM Integration

```php
Event::listen(FormSubmitted::class, function ($event) {
    if ($event->form->slug === 'contact') {
        HubSpot::contacts()->create([
            'email' => $event->submission->getValue('email'),
            'firstname' => $event->submission->getValue('first_name'),
            'lastname' => $event->submission->getValue('last_name'),
        ]);
    }
});
```

## Next Steps

- [Webhooks](Webhooks) - Detailed webhook documentation
- [Spam Protection](Spam-Protection) - Anti-spam configuration
- [Customization](Customization) - Customization options
- [Frontend Components](Frontend-Frontend) - React and Vue form renderers
- [REST API](Api-Rest-Api) - RESTful HTTP endpoints
