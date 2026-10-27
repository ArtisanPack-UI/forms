---
title: Form Renderer
---

# Form Renderer

The Form Renderer component displays forms to your users and handles submission processing.

## Basic Usage

The component takes a `Form` model, so load the form first and pass it in.

### By Slug (Recommended)

```php
use ArtisanPackUI\Forms\Services\FormService;

$form = app(FormService::class)->getBySlug('contact-us');
```

```blade
<livewire:form-renderer :form="$form" />
```

### By Form ID

```php
$form = app(FormService::class)->getById(1);
```

```blade
<livewire:form-renderer :form="$form" />
```

Both lookups return `null` when no form matches, so check the result before rendering (see [Embedding in Controllers](#embedding-in-controllers)).

## Component Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `form` | Form | required | The form to display |
| `prefill` | array | `[]` | Starting values for fields with dynamic population on (since 1.6.0; see [Dynamic Population](Usage-Dynamic-Population)) |

The success message, redirect URL, description and submit button text are not component properties. They are form settings, edited in the **Settings** tab of the form's edit page (see [Form Settings](Usage-Form-Builder#form-settings)) or set on the model.

## Examples

### Custom Success Message

Set the form's success message:

```php
$form->update(['success_message' => "We'll be in touch within 24 hours!"]);
```

```blade
<livewire:form-renderer :form="$form" />
```

Without one, the renderer shows "Thank you! Your submission has been received."

### Redirect After Submission

Set the form's redirect URL:

```php
$form->update(['redirect_url' => '/welcome']);
```

After a saved submission the renderer redirects the visitor to that URL (since 1.6.0) and also dispatches a `redirect-to` event with it. Only `http`/`https` URLs and site paths such as `/welcome` are followed.

### Title and Description

The renderer shows the form's description above the fields when one is set, and never prints the form's name. Clear the description to hide it, and add your own heading if you want one:

```blade
<h2>{{ $form->name }}</h2>

<livewire:form-renderer :form="$form" />
```

### Prefilled Values

```blade
<livewire:form-renderer
    :form="$form"
    :prefill="['plan' => 'pro', 'email' => auth()->user()?->email]"
/>
```

### Custom Styling

Wrap the component in your own markup:

```blade
<div class="bg-white p-6 rounded-lg shadow">
    <livewire:form-renderer :form="$form" />
</div>
```

The component's root element has the `artisanpack-form-renderer` class for targeting from CSS.

## Form States

The renderer handles several form states:

### Loading State

The submit and step buttons show a loading state while a request is running. To add your own indicator, use `wire:loading` inside a published view.

### Success State

After a successful submission, displays the form's success message and dispatches `form-submitted` (and `redirect-to` when the form has a redirect URL).

### Error State

Validation errors are displayed inline next to each field. Form-level errors, such as too many submissions or a failed save, show in an alert above the fields.

## Customizing the Form View

Publish the views to customize:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/form-renderer.blade.php`.

### View Structure

The renderer is split across these views:

| View | Contents |
|------|----------|
| `livewire/form-renderer.blade.php` | Wrapper, success state, description, error alert, step progress and navigation, submit button |
| `livewire/form-renderer/fields.blade.php` | The loop over the current fields, including sections |
| `livewire/form-renderer/field.blade.php` | Each field's wrapper, shown or hidden by conditional logic |
| `components/fields/{type}.blade.php` | One view per field type |

Republish after upgrading. The package logs a warning when a published renderer view is too old to work with conditional logic.

## Custom Field Templates

Each field type renders `forms::components.fields.{type}`. To change how a type looks, publish the views and edit its file, for example `resources/views/vendor/forms/components/fields/text.blade.php`. Keep the `wire:model="formData.{name}"` binding and the error output, since the renderer reads values from `formData` and validates `formData.{name}`.

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
    <livewire:form-renderer :form="$form" />
@else
    <p>This form is currently unavailable.</p>
@endif
```

Since 1.6.0 the renderer itself shows "This form is not accepting submissions." for an inactive form and refuses its submissions, so this check is only needed if you want your own message.

## Handling Submission Events

Listen for form submission in your JavaScript:

```javascript
document.addEventListener('livewire:init', () => {
    Livewire.on('form-submitted', ({ formId, submissionId, isSpam }) => {
        // isSpam is true when the spam check caught a bot; nothing was saved
        if (isSpam) {
            return;
        }

        // Track conversion
        gtag('event', 'form_submission', {
            'form_id': formId,
        });
    });
});
```

The renderer dispatches these events:

| Event | Payload | When |
|-------|---------|------|
| `form-submitted` | `{ formId, submissionId }` or `{ formId, isSpam: true }` | After a submission is saved, or when a bot is caught |
| `redirect-to` | `{ url }` | After a saved submission, when the form has a redirect URL |
| `step-changed` | `{ step }` | Moving between steps (1-based) |
| `forms-validation-failed` | `{ formId, count }` | Validation failed on submit or a step change |
| `form-reset` | `{ formId }` | The form was reset for another response |

For server-side handling, listen for the `ArtisanPackUI\Forms\Events\FormSubmitted` Laravel event.

## AJAX Submission

Forms always submit through Livewire (AJAX). There is no full-page submission mode. For a classic POST, or a headless frontend, use the [REST API](Api-Rest-Api) instead.

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
