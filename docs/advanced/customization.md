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
├── partials/
│   ├── fields/
│   │   ├── text.blade.php
│   │   ├── email.blade.php
│   │   ├── textarea.blade.php
│   │   └── ...
│   ├── progress.blade.php
│   └── navigation.blade.php
└── emails/
    ├── notification.blade.php
    └── notification-text.blade.php
```

### Custom Field Templates

Create custom field templates:

```blade
{{-- resources/views/vendor/forms/partials/fields/text.blade.php --}}
<div class="form-group mb-4">
    <label for="{{ $field->name }}" class="block font-medium mb-1">
        {{ $field->label }}
        @if ($field->required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    <input
        type="text"
        id="{{ $field->name }}"
        wire:model="formData.{{ $field->name }}"
        placeholder="{{ $field->placeholder }}"
        class="w-full border rounded px-3 py-2 @error('formData.' . $field->name) border-red-500 @enderror"
    >

    @if ($field->help_text)
        <p class="text-sm text-gray-500 mt-1">{{ $field->help_text }}</p>
    @endif

    @error('formData.' . $field->name)
        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
    @enderror
</div>
```

## Custom Field Types

### Register a Field Type

```php
// In a service provider
use function addFilter;

public function boot(): void
{
    addFilter('ap.forms.fieldTypes', function ($types) {
        $types['color-picker'] = [
            'label' => 'Color Picker',
            'icon' => 'palette',
            'category' => 'advanced',
            'view' => 'my-package::fields.color-picker',
            'validation' => ['hex_color'],
        ];

        return $types;
    });
}
```

### Field View

```blade
{{-- resources/views/vendor/forms/partials/fields/color-picker.blade.php --}}
<div class="form-group">
    <label for="{{ $field->name }}">{{ $field->label }}</label>

    <div class="flex items-center gap-2">
        <input
            type="color"
            id="{{ $field->name }}"
            wire:model="formData.{{ $field->name }}"
            class="w-12 h-12 cursor-pointer"
        >
        <input
            type="text"
            wire:model="formData.{{ $field->name }}"
            placeholder="#000000"
            class="border rounded px-3 py-2"
        >
    </div>

    @error('formData.' . $field->name)
        <span class="error">{{ $message }}</span>
    @enderror
</div>
```

### Make a Custom Field a First-Class Builder Citizen

Registering a field type via `ap.forms.fieldTypes` makes it available, but the
drag-and-drop builder offers three further seams so your field can carry its own
category, editor controls, and canvas preview:

```php
// In a service provider's boot()
use function addFilter;

// 1. File the type under a custom palette category.
addFilter('ap.forms.fieldCategories', function (array $categories): array {
    $categories['bookings'] = ['label' => 'Bookings', 'icon' => 'calendar'];

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

// 3. Render a live preview on the builder canvas instead of the bare card.
addFilter('ap.forms.fieldCardPreview', function (string $html, $field): string {
    if ('appointment' !== $field->type) {
        return $html;
    }

    return view('my-package::builder.appointment-preview', ['field' => $field])->render();
});
```

Each filter is passed an empty string as its first argument; return a non-empty
string to take over that slot, or return it unchanged to leave the default
rendering in place.

## Filter Hooks

### Available Filters

| Filter | Arguments | Description |
|--------|-----------|-------------|
| `ap.forms.fieldTypes` | `$types` | Modify available field types |
| `ap.forms.fieldCategories` | `$categories` | Add or modify the field-type categories shown in the builder palette |
| `ap.forms.fieldSettings` | `$html, $field, $form` | Inject custom controls into the field editor (the field and the rest of the form are in scope for mapping dropdowns) |
| `ap.forms.fieldCardPreview` | `$html, $field` | Render a live preview of the field on the builder canvas instead of the bare card |
| `ap.forms.validationRules` | `$rules, $form` | Modify validation rules |
| `ap.forms.fieldRender` | `$html, $field` | Modify the rendered HTML of a single field before it's echoed by the form renderer |
| `ap.forms.notificationMessage` | `$message, $notification, $submission` | Modify notification content (legacy; still runs before `notificationSubject` / `notificationBody`) |
| `ap.forms.notificationSubject` | `$subject, $notification, $submission` | Modify outgoing notification email subject |
| `ap.forms.notificationBody` | `$body, $notification, $submission` | Modify outgoing notification email body |
| `ap.forms.webhookPayload` | `$payload, $form, $submission` | Modify webhook data |
| `forms.spam_check` | `$isSpam, $form, $data` | Custom spam detection |
| `ap.forms.settingsTabs` | `$tabs, $form` | Add admin settings tabs |

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
