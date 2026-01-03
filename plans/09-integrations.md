# Integrations

**Purpose:** Define the hook-based integration system that allows external packages to extend form functionality.

---

## Overview

Instead of building specific integrations (ConvertKit, Mailchimp, etc.) into the forms package, we provide a hook-based system using `artisanpack-ui/hooks`. This approach:

- Keeps the core package lean
- Allows unlimited third-party integrations
- Enables custom integrations per-project
- Follows WordPress-style extensibility

---

## Available Hooks

### Actions (Events)

Actions allow packages to perform side effects when events occur.

| Action | When Fired | Parameters |
|--------|------------|------------|
| `forms.submission.created` | After a submission is saved | `FormSubmission $submission` |
| `forms.submission.updated` | After a submission is updated | `FormSubmission $submission` |
| `forms.submission.deleted` | After a submission is deleted | `FormSubmission $submission` |
| `forms.form.created` | After a form is created | `Form $form` |
| `forms.form.updated` | After a form is updated | `Form $form` |
| `forms.form.deleted` | After a form is deleted | `Form $form` |
| `forms.notification.before_send` | Before email is sent | `FormNotification $notification, FormSubmission $submission` |
| `forms.notification.sent` | After email is sent | `FormNotification $notification, FormSubmission $submission` |

### Filters (Modifiers)

Filters allow packages to modify data before it's used.

| Filter | Purpose | Parameters | Returns |
|--------|---------|------------|---------|
| `forms.field_types` | Register custom field types | `array $fieldTypes` | `array` |
| `forms.validation_rules` | Modify validation rules | `array $rules, FormField $field` | `array` |
| `forms.submission_data` | Modify data before saving | `array $data, Form $form` | `array` |
| `forms.notification_recipients` | Modify email recipients | `array $recipients, FormNotification $notification` | `array` |
| `forms.notification_message` | Modify email message | `string $message, FormNotification $notification, FormSubmission $submission` | `string` |
| `forms.export_data` | Modify export data | `array $data, FormSubmission $submission` | `array` |
| `forms.export_headers` | Modify CSV headers | `array $headers, Form $form` | `array` |

---

## Implementation

### Dispatching Actions

```php
// In SubmissionService.php

use function ArtisanPackUI\Hooks\doAction;

class SubmissionService
{
    public function create(Form $form, array $data, array $files, array $metadata): FormSubmission
    {
        // Apply filters to submission data
        $data = applyFilters('forms.submission_data', $data, $form);

        $submission = $form->submissions()->create([
            'submission_number' => $this->generateSubmissionNumber($form),
            'page_url' => $metadata['page_url'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'user_agent' => $metadata['user_agent'] ?? null,
        ]);

        // Create submission values...

        // Fire action hook - integrations can listen for this
        doAction('forms.submission.created', $submission);

        return $submission;
    }
}
```

### Applying Filters

```php
// In FormField.php

use function ArtisanPackUI\Hooks\applyFilters;

class FormField extends Model
{
    public function buildValidationRules(): array
    {
        $rules = $this->getBaseRules();

        // Allow packages to modify validation rules
        return applyFilters('forms.validation_rules', $rules, $this);
    }
}
```

---

## Integration Package Example

Here's how a ConvertKit integration package would work:

### Package: artisanpack-ui/forms-convertkit

```php
<?php

namespace ArtisanPackUI\FormsConvertKit;

use Illuminate\Support\ServiceProvider;
use ArtisanPackUI\Forms\Models\FormSubmission;
use function ArtisanPackUI\Hooks\addAction;

class FormsConvertKitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Listen for form submissions
        addAction('forms.submission.created', function (FormSubmission $submission) {
            $this->syncToConvertKit($submission);
        });
    }

    protected function syncToConvertKit(FormSubmission $submission): void
    {
        // Check if this form has ConvertKit integration enabled
        $formSettings = $submission->form->settings;
        $convertKitFormId = $formSettings['convertkit_form_id'] ?? null;

        if (!$convertKitFormId) {
            return;
        }

        // Get email from submission
        $email = $submission->getEmailValue();

        if (!$email) {
            return;
        }

        // Build custom fields from submission data
        $fields = [];
        foreach ($submission->values as $value) {
            if ($value->field_type !== 'email') {
                $fields[$value->field_name] = $value->display_value;
            }
        }

        // Subscribe to ConvertKit
        ConvertKitService::subscribe($convertKitFormId, $email, $fields);
    }
}
```

---

## Custom Field Type via Hook

Third-party packages can add custom field types:

```php
<?php

// In a custom package's ServiceProvider

use function ArtisanPackUI\Hooks\addFilter;

public function boot(): void
{
    addFilter('forms.field_types', function (array $fieldTypes) {
        $fieldTypes['star_rating'] = [
            'label' => 'Star Rating',
            'icon' => 'o-star',
            'category' => 'advanced',
            'component' => 'my-package::fields.star-rating',
            'default_label' => 'Rating',
            'default_config' => [
                'max_stars' => 5,
                'allow_half' => false,
            ],
            'validations' => ['required', 'min', 'max'],
            'settings' => [
                'max_stars' => ['type' => 'number', 'min' => 1, 'max' => 10],
                'allow_half' => ['type' => 'toggle'],
            ],
        ];

        return $fieldTypes;
    });
}
```

---

## Webhook Integration

For generic webhook support, the core package includes a webhook action:

```php
<?php

// In config/forms.php

'integrations' => [
    'webhooks' => [
        'enabled' => env('FORMS_WEBHOOKS_ENABLED', false),
    ],
],
```

```php
<?php

// In FormsServiceProvider.php

if (config('forms.integrations.webhooks.enabled')) {
    addAction('forms.submission.created', function (FormSubmission $submission) {
        $webhookUrl = $submission->form->settings['webhook_url'] ?? null;

        if ($webhookUrl) {
            dispatch(new SendWebhook($webhookUrl, [
                'event' => 'submission.created',
                'form_id' => $submission->form_id,
                'form_name' => $submission->form->name,
                'submission_id' => $submission->id,
                'submission_number' => $submission->submission_number,
                'data' => $submission->data_array,
                'submitted_at' => $submission->created_at->toIso8601String(),
            ]));
        }
    });
}
```

---

## Form Settings for Integrations

Forms can have integration-specific settings stored in the `settings` JSON column:

```json
{
    "spam_protection": { "honeypot": true },
    "integrations": {
        "convertkit": {
            "enabled": true,
            "form_id": "12345"
        },
        "mailchimp": {
            "enabled": true,
            "list_id": "abc123",
            "tags": ["website-contact"]
        },
        "webhook": {
            "enabled": true,
            "url": "https://hooks.zapier.com/...",
            "secret": "..."
        }
    }
}
```

### Integration Settings UI

Integration packages can add their own settings panels via hooks:

```php
// Register an integration settings component
addFilter('forms.settings_tabs', function (array $tabs) {
    $tabs['convertkit'] = [
        'label' => 'ConvertKit',
        'icon' => 'convertkit-icon',
        'component' => 'forms-convertkit::settings-panel',
    ];

    return $tabs;
});
```

---

## Events for Laravel Native Listeners

In addition to hooks, the package dispatches Laravel events:

```php
<?php

namespace ArtisanPackUI\Forms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use ArtisanPackUI\Forms\Models\FormSubmission;

class FormSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FormSubmission $submission
    ) {}
}
```

Apps can listen using standard Laravel listeners:

```php
// In EventServiceProvider.php

protected $listen = [
    \ArtisanPackUI\Forms\Events\FormSubmitted::class => [
        \App\Listeners\ProcessFormSubmission::class,
    ],
];
```

---

## Related Documents

- [04-form-renderer.md](04-form-renderer.md) - Triggers submission event
- [05-field-types.md](05-field-types.md) - Registering custom field types
- [08-notifications.md](08-notifications.md) - Notification hooks
