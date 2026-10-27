---
title: Customization
---

# Customization

Extend and customize ArtisanPack UI Forms to fit your needs.

## Publishing Assets

Publish package assets for customization:

```bash
# Publish everything
php artisan vendor:publish --provider="ArtisanPackUI\Forms\FormsServiceProvider"

# Publish specific assets
php artisan vendor:publish --tag=forms-config
php artisan vendor:publish --tag=forms-views
```

## Custom Views

### Publishing Views

```bash
php artisan vendor:publish --tag=forms-views
```

Views are published to `resources/views/vendor/forms/`.

### View Structure

```
resources/views/vendor/forms/
├── livewire/
│   ├── form-builder.blade.php
│   ├── form-renderer.blade.php
│   ├── forms-list.blade.php
│   ├── submission-detail.blade.php
│   ├── submissions-list.blade.php
│   └── notification-editor.blade.php
├── components/
│   ├── fields/
│   │   ├── text.blade.php
│   │   ├── email.blade.php
│   │   ├── textarea.blade.php
│   │   └── ...        (one view per field type)
│   ├── field-layout.blade.php
│   └── combobox.blade.php
└── emails/
    ├── notification.blade.php
    └── notification-text.blade.php
```

A published view shadows the package's copy, so it doesn't get the package's later fixes. Publish only the views you change, and after upgrading compare them with the package's new copies. 1.5 copies of `livewire/form-renderer.blade.php` and `components/fields/file.blade.php` don't work with 1.6; republish them with `php artisan vendor:publish --tag=forms-views --force` and apply your changes again.

### Custom Field Templates

Each field type is rendered by `forms::components.fields.{type}`. To change how a type looks, put a copy at `resources/views/vendor/forms/components/fields/{type}.blade.php`. Start from the package's own view, which handles label placement, descriptions, errors and the ARIA attributes. The view receives `$field`, `$value`, `$error` and `$subErrors`. To change a field's HTML without replacing the view, use the `ap.forms.fieldRender` filter.

## Custom Field Types

Since 1.6.0 the recommended way to add a field type is a **field type handler**: a class that extends `ArtisanPackUI\Forms\FieldTypes\AbstractFieldType` and holds the type's definition, settings, validation, display, export and merge tags. See [Field Type Handlers](Advanced-Field-Type-Handlers).

```php
// In a service provider
use App\Forms\IbanFieldType;
use function addFilter;

public function boot(): void
{
    addFilter('ap.forms.fieldTypes', function (array $types): array {
        $types['iban'] = IbanFieldType::class; // or new IbanFieldType()

        return $types;
    });
}
```

### Register an Array Definition

An array definition still works. It is wrapped in `ArrayFieldType`, so it gets the default handler behaviour:

```php
addFilter('ap.forms.fieldTypes', function (array $types): array {
    $types['hex_code'] = [
        'label'                  => __('Hex Code'),
        'icon'                   => 'o-swatch',
        'category'               => 'advanced',
        'has_options'            => false,
        'supports_placeholder'   => true,
        'supports_default_value' => true,
        'validation_options'     => ['pattern'],
        'type_validation'        => ['string', 'hex_color'], // replaces the default `string` rule
    ];

    return $types;
});
```

`validation_options` gives the type the matching builder controls (`min`, `max`, `pattern`, `step`, `max_size`, `allowed_types`, `min_date`, `max_date`), and a `settings` list adds your own (see [Settings Schema](Advanced-Field-Type-Handlers#settings-schema)). The type also needs a palette category before it shows in the builder (see below).

### Field View

The Livewire renderer renders a field with the `forms::components.fields.{type}` view, here `forms::components.fields.hex_code`. Put it in your app at `resources/views/vendor/forms/components/fields/hex_code.blade.php`, override the handler's `view()`, or supply the HTML through the `ap.forms.fieldRender` filter. The view receives `$field`, `$value`, `$error` and `$subErrors`:

```blade
{{-- resources/views/vendor/forms/components/fields/hex_code.blade.php --}}
<div>
    <label for="{{ $field->name }}">{{ $field->label }}</label>

    <input
        type="text"
        id="{{ $field->name }}"
        wire:model.blur="formData.{{ $field->name }}"
        placeholder="#000000"
        @if ($error) aria-invalid="true" aria-describedby="error-{{ $field->name }}" @endif
    >

    @if ($error)
        <p id="error-{{ $field->name }}" class="text-error">{{ $error }}</p>
    @endif
</div>
```

The React and Vue renderers need a component for the type; see [React Components](Frontend-React#custom-field-types).

### Make a Custom Field a First-Class Builder Citizen

Registering a field type via `ap.forms.fieldTypes` makes it available, but the
drag-and-drop builder offers three further seams so your field can carry its own
category, editor controls, and canvas preview:

```php
// In a service provider's boot()
use function addFilter;

// 1. File the type under a custom palette category. Each category is a
//    ['label' => string, 'fields' => string[]] entry; every slug in `fields`
//    must be a type registered through `ap.forms.fieldTypes` (here, `appointment`).
addFilter('ap.forms.fieldCategories', function (array $categories): array {
    $categories['bookings'] = [
        'label'  => 'Bookings',
        'fields' => ['appointment'],
    ];

    return $categories;
});

// 2. Inject custom controls into the field editor. The field being edited and
//    the form (its other fields) are in scope — handy for mapping dropdowns.
addFilter('ap.forms.fieldSettings', function (string $html, $field, $form): string {
    if ('appointment' !== $field->type) {
        return $html;
    }

    return $html . view('my-package::builder.appointment-settings', [
        'field' => $field,
        'form'  => $form,
    ])->render();
});

// 3. Render a live preview of the field inside its card on the builder canvas.
addFilter('ap.forms.fieldCardPreview', function (string $html, $field): string {
    if ('appointment' !== $field->type) {
        return $html;
    }

    return $html . view('my-package::builder.appointment-preview', ['field' => $field])->render();
});
```

`ap.forms.fieldCategories` receives and returns the palette's category array.
`ap.forms.fieldSettings` and `ap.forms.fieldCardPreview` are each passed an
existing HTML string (empty by default) as their first argument — append your
markup to it, or return it unchanged to leave the default rendering in place. The
card preview is inserted inside the field's existing card on the canvas, not in
place of it.

## Filter Hooks

### Available Filters

| Filter | Arguments | Description |
|--------|-----------|-------------|
| `ap.forms.fieldTypes` | `$types` | Modify available field types |
| `ap.forms.fieldCategories` | `$categories` | Add or modify the field-type categories shown in the builder palette |
| `ap.forms.fieldSettings` | `$html, $field, $form` | Inject custom controls into the field editor (the field and the rest of the form are in scope for mapping dropdowns) |
| `ap.forms.fieldCardPreview` | `$html, $field` | Render a live preview of the field inside its card on the builder canvas |
| `ap.forms.validationRules` | `$rules, $form` | Modify validation rules |
| `ap.forms.fieldRender` | `$html, $field` | Modify the rendered HTML of a single field before it's echoed by the form renderer |
| `ap.forms.notificationMessage` | `$message, $notification, $submission` | Modify notification content (legacy; still runs before `notificationSubject` / `notificationBody`) |
| `ap.forms.notificationSubject` | `$subject, $notification, $submission` | Modify outgoing notification email subject |
| `ap.forms.notificationBody` | `$body, $notification, $submission` | Modify outgoing notification email body |
| `ap.forms.webhookPayload` | `$payload, $form, $submission` | Modify webhook data |
| `forms.spam_check` | `$isSpam, $form, $data` | Custom spam detection |
| `ap.forms.submission.should_send_notifications` | `$shouldSend, $submission` | Decide whether the notification pipeline runs for a submission; defaults to `false` for quarantined submissions (since 1.5.0) |
| `ap.forms.settingsTabs` | `$tabs, $form` | Add admin settings tabs |
| `ap.forms.readOnlyValue` | `$value, $field` | The value a read-only field is stored with (since 1.6.0). Use this rather than `ap.forms.submissionData`, which runs before read-only values are applied |
| `ap.forms.fieldValue.{parameter}` | `$default, $field, $context` (plus `$subInputKey` for a composite input) | The starting value of a dynamically populated field, after the prefill and query string (since 1.6.0; see [Dynamic Population](Usage-Dynamic-Population)) |
| `ap.forms.choiceSources` | `$sources` | Register sources a choice field can load its options from, as `key => ['label' => …, 'resolver' => callable]` (since 1.6.0) |
| `ap.forms.predefinedChoices` | `$lists` | Add or change the builders' predefined choice lists, as `key => ['label' => …, 'choices' => […]]` (since 1.6.0) |
| `ap.forms.countries` | `$countries` | The country list (ISO 3166-1 alpha-2 code => name) used by address fields, international phone fields and the Countries predefined list (since 1.6.0) |
| `ap.forms.captchaDrivers` | `$drivers` | Register or replace CAPTCHA drivers, keyed by name, as a `CaptchaDriver` class name or instance (since 1.6.0; see [Spam Protection](Advanced-Spam-Protection#custom-drivers)) |
| `ap.forms.canRevealMaskedValues` | `$allowed, $user` | Decide who may see values of fields with **Mask in admin** on. Return `true` to allow (since 1.6.0; see [Field Privacy & Visibility](Usage-Field-Privacy)) |
| `ap.forms.addressAutocomplete` | `$html, $field` | HTML the Livewire renderer prints after an address field, for address autocomplete providers (since 1.6.0) |

### Available Actions

| Action | Arguments | Description |
|--------|-----------|-------------|
| `ap.forms.beforeValidate` | `$request` | Fires from a package `FormRequest::prepareForValidation()` just before Laravel validates the request |
| `ap.forms.validated` | `$request, $validated` | Fires from `FormRequest::passedValidation()` after validation succeeds |
| `ap.forms.integrationRegistered` | `$slug, $config` | Fires when `IntegrationService::registerIntegration()` registers an integration so ecosystem packages can observe registrations |

### Examples

```php
use function addFilter;

// Add custom validation
addFilter('ap.forms.validationRules', function ($rules, $form) {
    if ($form->slug === 'job-application') {
        $rules['formData.resume'] = 'required|mimes:pdf,doc,docx|max:5120';
    }
    return $rules;
});

// Modify notification
addFilter('ap.forms.notificationMessage', function ($message, $notification, $submission) {
    return $message . "\n\n---\nProcessed by MyApp v" . config('app.version');
});

// Custom spam check
addFilter('forms.spam_check', function ($isSpam, $form, $data) {
    // Block disposable email addresses
    $email = $data['email'] ?? '';
    $disposableDomains = ['tempmail.com', 'throwaway.com'];

    $domain = substr(strrchr($email, '@'), 1);
    if (in_array($domain, $disposableDomains)) {
        return true;
    }

    return $isSpam;
});
```

## Custom Policies

### Override Form Policy

```php
// app/Policies/CustomFormPolicy.php
namespace App\Policies;

use App\Models\User;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Policies\FormPolicy;

class CustomFormPolicy extends FormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-forms');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create-forms');
    }

    public function update(User $user, Form $form): bool
    {
        return $user->hasPermission('edit-forms')
            || $form->user_id === $user->id;
    }
}
```

### Register Policy

```php
// app/Providers/AuthServiceProvider.php
use ArtisanPackUI\Forms\Models\Form;
use App\Policies\CustomFormPolicy;

protected $policies = [
    Form::class => CustomFormPolicy::class,
];
```

## Custom Components

### Extend Livewire Components

```php
// app/Livewire/CustomFormRenderer.php
namespace App\Livewire;

use ArtisanPackUI\Forms\Livewire\FormRenderer;

class CustomFormRenderer extends FormRenderer
{
    public function submit(): void
    {
        // Pre-processing
        $this->formData['submitted_by'] = auth()->id();

        parent::submit();

        // Post-processing
        $this->trackConversion();
    }

    protected function trackConversion(): void
    {
        // Analytics tracking
    }
}
```

### Register Component

```php
// app/Providers/AppServiceProvider.php
use Livewire\Livewire;
use App\Livewire\CustomFormRenderer;

public function boot(): void
{
    Livewire::component('custom-form-renderer', CustomFormRenderer::class);
}
```

## Custom Services

### Extend Services

```php
// app/Services/CustomSubmissionService.php
namespace App\Services;

use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;

class CustomSubmissionService extends SubmissionService
{
    public function create(Form $form, array $data, array $metadata = []): FormSubmission
    {
        // Add custom metadata
        $metadata['processed_at'] = now();
        $metadata['processor'] = 'custom';

        return parent::create($form, $data, $metadata);
    }
}
```

### Bind in Container

```php
// app/Providers/AppServiceProvider.php
use ArtisanPackUI\Forms\Services\SubmissionService;
use App\Services\CustomSubmissionService;

public function register(): void
{
    $this->app->bind(SubmissionService::class, CustomSubmissionService::class);
}
```

## Custom Admin Routes

### Add Admin Pages

```php
// routes/web.php
use App\Http\Controllers\FormReportsController;

Route::middleware(['web', 'auth'])
    ->prefix('admin/forms')
    ->name('forms.')
    ->group(function () {
        Route::get('/reports', [FormReportsController::class, 'index'])
            ->name('reports');
        Route::get('/analytics', [FormReportsController::class, 'analytics'])
            ->name('analytics');
    });
```

## CSS Customization

### Override Styles

```css
/* resources/css/forms.css */

.form-renderer {
    --form-bg: #ffffff;
    --form-border: #e5e7eb;
    --form-error: #ef4444;
    --form-success: #10b981;
}

.form-renderer .form-group {
    margin-bottom: 1.5rem;
}

.form-renderer input,
.form-renderer textarea,
.form-renderer select {
    border: 1px solid var(--form-border);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
}

.form-renderer .error-message {
    color: var(--form-error);
    font-size: 0.875rem;
}
```

## Next Steps

- [API Reference](Api-Api) - Detailed class documentation
- [Filter Hooks](Api-Events) - Event handling
- [Configuration](Installation-Configuration) - All settings
