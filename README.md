# ArtisanPack UI Forms

ArtisanPack UI Forms is a comprehensive form builder and management package for Laravel applications. Built on Livewire 3, it provides a drag-and-drop form builder, submission management, email notifications, file uploads, multi-step forms, conditional logic, and webhook integrations.

## 🚀 Quick Start

### Installation

```bash
# Install the package
composer require artisanpack-ui/forms

# Publish configuration and assets
php artisan vendor:publish --provider="ArtisanPackUI\Forms\FormsServiceProvider"

# Run migrations
php artisan migrate
```

### Basic Usage

```php
// Load the form, e.g. in a controller
$form = app( \ArtisanPackUI\Forms\Services\FormService::class )->getBySlug( 'contact' );
```

```blade
<!-- Display it -->
<livewire:form-renderer :form="$form" />

<!-- Optionally start fields with values -->
<livewire:form-renderer :form="$form" :prefill="['plan' => 'pro']" />
```

The success message, redirect URL and other display options are form settings, edited in the admin (`/admin/forms/{form}/edit`, **Settings** tab).

## ✨ Key Features

- **🎨 Visual Form Builder**: Drag-and-drop interface for creating forms without code
- **📝 35+ Field Types**: Text, choices, toggles, file uploads, dates, ratings, sliders, name and address, lists, calculations, signatures, consent, CAPTCHA, and more
- **📊 Submission Management**: View, export, and manage form submissions with ease
- **📧 Email Notifications**: Admin notifications and autoresponders with template support
- **📁 Secure File Uploads**: Private file storage with MIME validation and size limits
- **📑 Multi-Step Forms**: Create wizard-style forms with step navigation
- **🔀 Conditional Logic**: Show/hide fields and steps based on user input
- **🔗 Webhook Integration**: Send form data to external services with HMAC signatures
- **🛡️ Spam Protection**: Built-in honeypot fields, rate limiting, and a CAPTCHA field (Turnstile, hCaptcha, reCAPTCHA)
- **🔐 Field Privacy**: Administrative fields, "Do not store", masking in the admin, and timed redaction
- **🔒 Authorization**: Policy-based access control with ownership support
- **📤 Export Options**: Export submissions to CSV format

## 🧩 Components

### Livewire Components

| Component | Description |
|-----------|-------------|
| `FormBuilder` | Visual drag-and-drop form builder interface |
| `FormRenderer` | Renders forms for user submission |
| `FormsList` | Lists and manages forms |
| `SubmissionsList` | Lists and manages submissions |
| `SubmissionDetail` | Displays submission details |
| `NotificationEditor` | Configure email notifications |

### Available Field Types

**Basic Fields**: Text, Email (with optional confirmation), Phone, Number, URL, Text Area (with optional rich text editor), Hidden

**Choice Fields**: Dropdown Select (optionally searchable), Radio Buttons, Single Checkbox, Toggle, Checkbox Group, Multi-Select, with image choices, an "Other" choice, predefined lists and dynamic choices

**Advanced Fields**: File Upload (single or multiple), Date, Time, Date & Time, Date Range, Rating, Slider, Color, Net Promoter Score, Likert / Matrix, Ranking, Name, Address, List, Calculation, Signature, Consent, CAPTCHA

**Layout Elements**: Section, Heading, Paragraph, Divider, Custom HTML

Every input field can be read-only, populated dynamically, given an admin label, made administrative (filled in by admins on the submission), or kept private with "Do not store", "Mask in admin" and "Redact after (days)". See the [Form Builder](https://github.com/ArtisanPack-UI/forms/wiki/Usage-Form-Builder) and [Field Privacy & Visibility](https://github.com/ArtisanPack-UI/forms/wiki/Usage-Field-Privacy) docs.

## 📖 Documentation

Comprehensive documentation is available in our [Documentation Wiki](https://github.com/ArtisanPack-UI/forms/wiki):

- **[Installation Guide](https://github.com/ArtisanPack-UI/forms/wiki/Installation-Installation)** - Detailed setup instructions
- **[Configuration](https://github.com/ArtisanPack-UI/forms/wiki/Installation-Configuration)** - All configuration options
- **[Form Builder](https://github.com/ArtisanPack-UI/forms/wiki/Usage-Form-Builder)** - Creating forms
- **[Form Renderer](https://github.com/ArtisanPack-UI/forms/wiki/Usage-Form-Renderer)** - Displaying forms
- **[API Reference](https://github.com/ArtisanPack-UI/forms/wiki/Api)** - Models, services, and events

## ⚙️ Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=forms-config
```

### Environment Variables

The package supports the following environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `FORMS_ADMIN_PREFIX` | URL prefix for admin routes | `admin/forms` |
| `FORMS_UPLOADS_DISK` | Storage disk for file uploads | `form-uploads` |
| `FORMS_UPLOADS_MAX_SIZE` | Maximum file size in KB | `10240` (10MB) |
| `FORMS_RETENTION_DAYS` | Days to keep submissions (null = forever) | `null` |
| `FORMS_CAPTCHA_DRIVER` | Default CAPTCHA provider (`turnstile`, `hcaptcha`, `recaptcha_v2`, `recaptcha_v3`) | `turnstile` |
| `FORMS_MEDIA_FOLDER_ID` / `FORMS_MEDIA_UPLOADED_BY` | Media library folder and uploader for file fields stored in the media library | `null` |
| `FORMS_CHOICE_IMAGES_DISK` | Disk for image-choice uploads without the media library | `public` |
| `FORMS_CONSENT_STORE_TEXT` | Store the consent text with each consent | `true` |
| `FORMS_WEBHOOKS_ENABLED` | Enable the global webhook | `false` |
| `FORMS_RESTRICT_BY_OWNER` | Restrict forms to their owners | `false` |
| `FORMS_ALLOW_ADMIN_BYPASS` | Allow admins to bypass ownership | `true` |
| `FORMS_USER_MODEL` | User model class | `App\Models\User` |

### Configuration Options

Key configuration options in `config/artisanpack/forms.php`:

```php
return [
    // Admin panel settings
    'admin' => [
        'prefix' => 'admin/forms',
        'middleware' => ['web', 'auth'],
    ],

    // File upload settings
    'uploads' => [
        'disk' => 'form-uploads',
        'max_size' => 10240, // 10MB in KB
        'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
    ],

    // Submission settings
    'submissions' => [
        'store_submissions' => true,
        'retention_days' => null, // null = keep forever
    ],

    // Spam protection
    'spam_protection' => [
        'honeypot' => ['enabled' => true],
        'rate_limit' => ['enabled' => true, 'attempts' => 5],
        'captcha' => ['driver' => 'turnstile'], // plus each provider's keys
    ],
];
```

## 🔧 Artisan Commands

```bash
# Prune old submissions based on retention settings
php artisan forms:prune-submissions

# Prune submissions older than specific days
php artisan forms:prune-submissions --days=90
```

## 📦 Requirements

- PHP 8.2 or higher
- Laravel 11, 12, or 13 (Laravel 13 requires PHP 8.3+)
- Livewire 3.6+

## 🤝 Dependencies

This package integrates with the ArtisanPack UI ecosystem:

- [artisanpack-ui/livewire-ui-components](https://github.com/ArtisanPack-UI/livewire-ui-components) - UI components
- [artisanpack-ui/security](https://github.com/ArtisanPack-UI/security) - Input sanitization and security
- [artisanpack-ui/accessibility](https://github.com/ArtisanPack-UI/accessibility) - Accessibility utilities
- [artisanpack-ui/hooks](https://github.com/ArtisanPack-UI/hooks) - WordPress-style hooks for extensibility

## 🎯 Events

The package dispatches events for key actions:

```php
use ArtisanPackUI\Forms\Events\FormCreated;
use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Events\SubmissionDeleted;

// Listen for form submissions
Event::listen(FormSubmitted::class, function ($event) {
    // $event->submission contains the submission
    // $event->form contains the form
});
```

## 🤖 AI features

The Forms package ships four opt-in AI agents that plug into the
[artisanpack-ui/ai](https://github.com/ArtisanPack-UI/ai) feature registry.
Install `artisanpack-ui/ai` (v1.0+) and configure credentials to enable them;
each feature no-ops when its toggle is off, so upgrades are non-breaking.

| Feature key                         | Agent                            | Default model      | What it does                                                                                     |
|-------------------------------------|----------------------------------|--------------------|--------------------------------------------------------------------------------------------------|
| `forms.spam_detection`              | `SpamDetectionAgent`             | `claude-haiku-4-5` | Score a single submission for spam. Returns `spam_score`, `verdict`, and `reasons`.              |
| `forms.submission_summary`          | `SubmissionSummaryAgent`         | `claude-sonnet-4-6`| Periodic digest of submission themes, notable entries, and suggested follow-ups.                 |
| `forms.response_classification`     | `ResponseClassificationAgent`    | `claude-haiku-4-5` | Categorize a submission against a caller-supplied set of labels; may propose a new one.           |
| `forms.smart_validation`            | `SmartFieldValidationAgent`      | `claude-haiku-4-5` | Opt-in per-field semantic plausibility check that complements format validation.                  |

Each agent is invoked the same way — construct it with `for()` and call `run()`:

```php
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;

$result = SpamDetectionAgent::for([
    'fields' => $submission->data_array,
    'meta'   => [
        'ip_country'          => $submission->ip_country,
        'submission_speed_ms' => $submission->submission_speed_ms,
    ],
])->run();
// [ 'spam_score' => int, 'verdict' => 'ham'|'suspicious'|'spam', 'reasons' => string[] ]
```

Each feature also ships a Livewire trigger component you can drop into the
admin surface. Components no-op when the feature toggle is off:

```blade
<livewire:forms::ai-spam-check
    :submission-id="$submission->id"
    :fields="$submission->data_array"
    :meta="[ 'ip_country' => $submission->ip_country ]"
/>

<livewire:forms::ai-submission-summary
    form-name="Contact us"
    window="weekly"
    :submissions="$submissions"
/>

<livewire:forms::ai-response-classifier
    :submission-id="$submission->id"
    :fields="$submission->data_array"
    :available-categories="[ 'support-request', 'sales-inquiry', 'feedback', 'bug-report' ]"
/>

<livewire:forms::ai-smart-field-validator
    field-name="address"
    field-label="Address"
    field-kind="address"
    :value="$address"
    :context="[ 'city' => $city, 'state' => $state ]"
/>
```

See the [AI RFC](https://github.com/ArtisanPack-UI/.github/discussions/8) for
the shared registry, toggle, and credential story across ArtisanPack UI
packages.

## 🔌 Extensibility

Add custom field types with a field type handler (since 1.6.0). The handler holds the type's definition, builder settings, validation, display, export and merge tags:

```php
use ArtisanPackUI\Forms\FieldTypes\AbstractFieldType;
use ArtisanPackUI\Forms\Models\FormField;
use function addFilter;

class IbanFieldType extends AbstractFieldType
{
    protected function defineType(): array
    {
        return [
            'label'                  => __( 'IBAN' ),
            'icon'                   => 'o-banknotes',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => false,
            'validation_options'     => [],
            'settings'               => [], // builder settings schema
        ];
    }

    protected function typeRules( FormField $field ): array
    {
        return ['string', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9 ]{11,40}$/i'];
    }
}

addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
    $types['iban'] = IbanFieldType::class;

    return $types;
} );
```

Array definitions registered through `ap.forms.fieldTypes` still work. See [Field Type Handlers](https://github.com/ArtisanPack-UI/forms/wiki/Advanced-Field-Type-Handlers).

### Hook naming

All package hooks use the `ap.forms.*` camelCase convention shared across the
ArtisanPack UI ecosystem. Hooks previously shipped under `forms.*` / snake_case
names remain registered as deprecation aliases (via `Support\HookAliases`), so
existing subscribers keep firing but emit an info-level deprecation log. The
aliases will be removed in the next major version — migrate to the new names
listed below.

| Old (deprecated)                 | New (canonical)                    |
|----------------------------------|------------------------------------|
| `forms.field_types`              | `ap.forms.fieldTypes`              |
| `forms.field_categories`         | `ap.forms.fieldCategories`         |
| `forms.validation_rules`         | `ap.forms.validationRules`         |
| `forms.form.created`             | `ap.forms.form.created`            |
| `forms.form.updated`             | `ap.forms.form.updated`            |
| `forms.form.deleted`             | `ap.forms.form.deleted`            |
| `forms.submission.created`       | `ap.forms.submission.created`      |
| `forms.submission.updated`       | `ap.forms.submission.updated`      |
| `forms.submission.deleted`       | `ap.forms.submission.deleted`      |
| `forms.webhook_payload`          | `ap.forms.webhookPayload`          |
| `forms.settings_tabs`            | `ap.forms.settingsTabs`            |
| `forms.submission_data`          | `ap.forms.submissionData`          |
| `forms.export_headers`           | `ap.forms.exportHeaders`           |
| `forms.export_data`              | `ap.forms.exportData`              |
| `forms.notification_recipients`  | `ap.forms.notificationRecipients`  |
| `forms.notification.before_send` | `ap.forms.notification.beforeSend` |
| `forms.notification.sent`        | `ap.forms.notification.sent`       |
| `forms.notification_message`     | `ap.forms.notificationMessage`     |

### Additional hooks

The following hooks have no legacy alias — they were introduced under the
canonical `ap.forms.*` convention.

| Hook                              | Type   | Payload                                            |
|-----------------------------------|--------|----------------------------------------------------|
| `ap.forms.beforeValidate`         | action | `(FormRequest $request)`                           |
| `ap.forms.validated`              | action | `(FormRequest $request, array $validated)`         |
| `ap.forms.fieldRender`            | filter | `(string $html, FormField $field)`                 |
| `ap.forms.fieldCategories`        | filter | `(array $categories)`                              |
| `ap.forms.fieldSettings`          | filter | `(string $html, FormField $field, Form $form)`     |
| `ap.forms.fieldCardPreview`       | filter | `(string $html, FormField $field)`                 |
| `ap.forms.integrationRegistered`  | action | `(string $slug, array $config)`                    |
| `ap.forms.notificationSubject`    | filter | `(string $subject, FormNotification $notification, FormSubmission $submission)` |
| `ap.forms.notificationBody`       | filter | `(string $body, FormNotification $notification, FormSubmission $submission)` |
| `ap.forms.readOnlyValue`          | filter | `(mixed $value, FormField $field)` — the value a read-only field is stored with (1.6.0) |
| `ap.forms.fieldValue.{parameter}` | filter | `(mixed $default, FormField $field, PopulationContext $context[, string $subInputKey])` — a dynamically populated field's starting value (1.6.0) |
| `ap.forms.choiceSources`          | filter | `(array $sources)` — register dynamic choice sources (1.6.0) |
| `ap.forms.predefinedChoices`      | filter | `(array $lists)` — add predefined choice lists (1.6.0) |
| `ap.forms.countries`              | filter | `(array $countries)` — the country list for address and phone fields (1.6.0) |
| `ap.forms.captchaDrivers`         | filter | `(array $drivers)` — register CAPTCHA drivers (1.6.0) |
| `ap.forms.canRevealMaskedValues`  | filter | `(bool $allowed, ?Authenticatable $user)` — who may see masked values (1.6.0) |
| `ap.forms.addressAutocomplete`    | filter | `(string $html, FormField $field)` — HTML printed after an address field (1.6.0) |

Examples:

```php
use ArtisanPackUI\Forms\Services\IntegrationService;
use function addAction;
use function addFilter;

// Wrap every rendered field in a diagnostic marker
addFilter('ap.forms.fieldRender', function (string $html, $field): string {
    return '<div data-field="' . $field->name . '">' . $html . '</div>';
});

// Observe integration registration from a third-party package
addAction('ap.forms.integrationRegistered', function (string $slug, array $config): void {
    logger()->info("forms integration registered: {$slug}", $config);
});

// Register your own integration from your service provider boot()
app(IntegrationService::class)->registerIntegration('mailchimp', [
    'label' => 'Mailchimp',
    'icon'  => 'o-envelope',
]);
```

## 🤝 Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Merge Request

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting merge requests.

## 📄 License

ArtisanPack UI Forms is open-sourced software licensed under the [MIT license](LICENSE).
