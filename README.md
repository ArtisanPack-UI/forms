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

```blade
<!-- Display a form by slug -->
<livewire:forms::form-renderer slug="contact" />

<!-- Or by form ID -->
<livewire:forms::form-renderer :form-id="1" />
```

## ✨ Key Features

- **🎨 Visual Form Builder**: Drag-and-drop interface for creating forms without code
- **📝 20+ Field Types**: Text, email, textarea, select, checkbox, radio, file upload, date, time, and more
- **📊 Submission Management**: View, export, and manage form submissions with ease
- **📧 Email Notifications**: Admin notifications and autoresponders with template support
- **📁 Secure File Uploads**: Private file storage with MIME validation and size limits
- **📑 Multi-Step Forms**: Create wizard-style forms with step navigation
- **🔀 Conditional Logic**: Show/hide fields and steps based on user input
- **🔗 Webhook Integration**: Send form data to external services with HMAC signatures
- **🛡️ Spam Protection**: Built-in honeypot fields and rate limiting
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

**Basic Fields**: Text, Email, URL, Phone, Number, Password, Hidden

**Text Fields**: Textarea, Rich Text Editor

**Selection Fields**: Select, Multi-Select, Checkbox, Radio, Toggle

**Date/Time Fields**: Date, Time, DateTime

**File Fields**: File Upload, Multiple Files

**Layout Fields**: Heading, Paragraph, Divider

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
| `FORMS_HONEYPOT_ENABLED` | Enable honeypot spam protection | `true` |
| `FORMS_RATE_LIMIT_ENABLED` | Enable rate limiting | `true` |
| `FORMS_RATE_LIMIT_MAX` | Maximum submissions per minute | `5` |
| `FORMS_WEBHOOKS_ENABLED` | Enable webhook integrations | `true` |
| `FORMS_RESTRICT_BY_OWNER` | Restrict forms to their owners | `false` |
| `FORMS_ADMIN_BYPASS` | Allow admins to bypass ownership | `true` |
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
        'rate_limit' => ['enabled' => true, 'max_attempts' => 5],
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
- Laravel 11 or 12
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

## 🔌 Extensibility

Add custom field types using filter hooks:

```php
use function addFilter;

addFilter('forms.field_types', function (array $types) {
    $types['my-custom-field'] = [
        'label' => 'My Custom Field',
        'view' => 'my-package::fields.custom',
        'settings' => ['option1', 'option2'],
    ];
    return $types;
});
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

ArtisanPack UI Forms is open-sourced software licensed under the [GPL-3.0-or-later license](LICENSE).
