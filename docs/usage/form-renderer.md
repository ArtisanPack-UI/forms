---
title: Form Renderer
---

# Form Renderer

The Form Renderer component displays forms to your users and handles submission processing.

## Basic Usage

### By Slug (Recommended)

```blade
<livewire:forms::form-renderer slug="contact-us" />
```

### By Form ID

```blade
<livewire:forms::form-renderer :form-id="1" />
```

## Component Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `slug` | string | null | Form slug to display |
| `form-id` | int | null | Form ID to display |
| `success-message` | string | null | Override default success message |
| `redirect-url` | string | null | URL to redirect after submission |
| `show-title` | bool | true | Show form title |
| `show-description` | bool | true | Show form description |
| `css-class` | string | '' | Additional CSS classes |

## Examples

### Custom Success Message

```blade
<livewire:forms::form-renderer
    slug="contact"
    success-message="We'll be in touch within 24 hours!"
/>
```

### Redirect After Submission

```blade
<livewire:forms::form-renderer
    slug="signup"
    redirect-url="/welcome"
/>
```

### Hide Title and Description

```blade
<livewire:forms::form-renderer
    slug="newsletter"
    :show-title="false"
    :show-description="false"
/>
```

### Custom Styling

```blade
<livewire:forms::form-renderer
    slug="contact"
    css-class="bg-white p-6 rounded-lg shadow"
/>
```

## Form States

The renderer handles several form states:

### Loading State

While the form is loading:

```blade
<div wire:loading>
    Loading form...
</div>
```

### Success State

After successful submission, displays the success message or redirects.

### Error State

Validation errors are displayed inline next to each field.

## Customizing the Form View

Publish the views to customize:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/form-renderer.blade.php`.

### View Structure

```blade
{{-- resources/views/vendor/forms/livewire/form-renderer.blade.php --}}
<div class="form-wrapper {{ $cssClass }}">
    @if ($showTitle && $form->name)
        <h2 class="form-title">{{ $form->name }}</h2>
    @endif

    @if ($showDescription && $form->description)
        <p class="form-description">{{ $form->description }}</p>
    @endif

    @if ($submitted)
        <div class="form-success">
            {{ $successMessage ?? $form->success_message }}
        </div>
    @else
        <form wire:submit="submit">
            @foreach ($form->fields as $field)
                @include("forms::partials.fields.{$field->type}", ['field' => $field])
            @endforeach

            <button type="submit">
                {{ $form->submit_button_text ?? 'Submit' }}
            </button>
        </form>
    @endif
</div>
```

## Custom Field Templates

Create custom field templates in `resources/views/vendor/forms/partials/fields/`:

```blade
{{-- resources/views/vendor/forms/partials/fields/text.blade.php --}}
<div class="form-group">
    <label for="{{ $field->name }}">
        {{ $field->label }}
        @if ($field->required)
            <span class="required">*</span>
        @endif
    </label>

    <input
        type="text"
        id="{{ $field->name }}"
        wire:model="formData.{{ $field->name }}"
        placeholder="{{ $field->placeholder }}"
        class="@error($field->name) is-invalid @enderror"
    >

    @if ($field->help_text)
        <small class="help-text">{{ $field->help_text }}</small>
    @endif

    @error($field->name)
        <span class="error">{{ $message }}</span>
    @enderror
</div>
```

## Embedding in Controllers

Access form data in your own controllers:

```php
use ArtisanPackUI\Forms\Services\FormService;

class PageController extends Controller
{
    public function show(FormService $formService, string $slug)
    {
        $form = $formService->getBySlug($slug);

        return view('page', [
            'form' => $form,
        ]);
    }
}
```

Then in your view:

```blade
@if ($form && $form->is_active)
    <livewire:forms::form-renderer :form-id="$form->id" />
@else
    <p>This form is currently unavailable.</p>
@endif
```

## Handling Submission Events

Listen for form submission in your JavaScript:

```javascript
document.addEventListener('livewire:initialized', () => {
    Livewire.on('form-submitted', (event) => {
        const { formId, submissionId } = event;

        // Track conversion
        gtag('event', 'form_submission', {
            'form_id': formId,
        });
    });
});
```

## AJAX Submission

Forms submit via Livewire by default (AJAX). For traditional form submission:

```blade
<livewire:forms::form-renderer
    slug="contact"
    :ajax="false"
/>
```

## Accessibility

The form renderer follows WCAG guidelines:

- Proper label associations
- ARIA attributes for errors
- Focus management
- Keyboard navigation
- Screen reader announcements

## Next Steps

- [Submissions](Submissions) - View and manage submissions
- [Notifications](Notifications) - Configure email notifications
- [Multi-Step Forms](Multi-Step-Forms) - Multi-step form display
