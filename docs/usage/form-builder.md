---
title: Form Builder
---

# Form Builder

The Form Builder provides a drag-and-drop interface for creating and editing forms without writing code.

## Accessing the Form Builder

Navigate to `/admin/forms/create` to create a new form, or `/admin/forms/{form}/edit` to edit an existing form.

## Form Settings

### Basic Settings

| Setting | Description |
|---------|-------------|
| **Name** | Internal name for the form |
| **Slug** | URL-friendly identifier (auto-generated from name) |
| **Description** | Optional description for admin reference |
| **Active** | Whether the form accepts submissions |

### Submission Settings

| Setting | Description |
|---------|-------------|
| **Success Message** | Message shown after successful submission |
| **Redirect URL** | Optional URL to redirect after submission |
| **Store Submissions** | Whether to save submissions to database |

### Display Settings

| Setting | Description |
|---------|-------------|
| **Label Position** | Above, beside, or hidden labels |
| **Show Required Indicator** | Show asterisk for required fields |
| **Submit Button Text** | Text for the submit button |

## Adding Fields

1. Click "Add Field" or drag a field type from the toolbar
2. Configure field settings in the sidebar
3. Drag fields to reorder
4. Click "Save" to save the form

## Field Configuration

Each field has common and type-specific settings:

### Common Settings

| Setting | Description |
|---------|-------------|
| **Label** | Field label displayed to users |
| **Name** | Field identifier (used in submissions) |
| **Placeholder** | Placeholder text |
| **Help Text** | Helper text below the field |
| **Required** | Whether the field is required |
| **CSS Classes** | Custom CSS classes |

### Validation Settings

| Setting | Description |
|---------|-------------|
| **Min Length** | Minimum character length |
| **Max Length** | Maximum character length |
| **Pattern** | Regular expression pattern |
| **Custom Error** | Custom validation error message |

## Field Types

### Text Inputs

```php
// Text field configuration
[
    'type' => 'text',
    'name' => 'full_name',
    'label' => 'Full Name',
    'required' => true,
    'validation' => [
        'min' => 2,
        'max' => 100,
    ],
]

// Email field
[
    'type' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'required' => true,
]

// Textarea
[
    'type' => 'textarea',
    'name' => 'message',
    'label' => 'Your Message',
    'rows' => 5,
]
```

### Selection Fields

```php
// Select dropdown
[
    'type' => 'select',
    'name' => 'country',
    'label' => 'Country',
    'options' => [
        ['value' => 'us', 'label' => 'United States'],
        ['value' => 'ca', 'label' => 'Canada'],
        ['value' => 'uk', 'label' => 'United Kingdom'],
    ],
]

// Radio buttons
[
    'type' => 'radio',
    'name' => 'contact_method',
    'label' => 'Preferred Contact Method',
    'options' => [
        ['value' => 'email', 'label' => 'Email'],
        ['value' => 'phone', 'label' => 'Phone'],
    ],
]

// Checkbox group
[
    'type' => 'checkbox_group',
    'name' => 'interests',
    'label' => 'Interests',
    'options' => [
        ['value' => 'news', 'label' => 'Newsletter'],
        ['value' => 'updates', 'label' => 'Product Updates'],
        ['value' => 'offers', 'label' => 'Special Offers'],
    ],
]
```

### Date and Time

```php
// Date picker
[
    'type' => 'date',
    'name' => 'birth_date',
    'label' => 'Date of Birth',
    'min_date' => '1900-01-01',
    'max_date' => 'today',
]

// Time picker
[
    'type' => 'time',
    'name' => 'preferred_time',
    'label' => 'Preferred Time',
]

// Date and time
[
    'type' => 'datetime',
    'name' => 'appointment',
    'label' => 'Appointment Date & Time',
]
```

### File Upload

```php
[
    'type' => 'file',
    'name' => 'resume',
    'label' => 'Upload Resume',
    'accept' => '.pdf,.doc,.docx',
    'max_size' => 5120, // KB
    'multiple' => false,
]
```

### Layout Elements

```php
// Section heading
[
    'type' => 'heading',
    'content' => 'Personal Information',
    'level' => 'h3',
]

// HTML content
[
    'type' => 'html',
    'content' => '<p>Please read our <a href="/terms">terms</a>.</p>',
]

// Divider
[
    'type' => 'divider',
]
```

## Creating Forms Programmatically

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;

$form = Form::create([
    'name' => 'Contact Form',
    'slug' => 'contact',
    'is_active' => true,
    'success_message' => 'Thank you for contacting us!',
    'settings' => [
        'label_position' => 'above',
        'submit_button_text' => 'Send Message',
    ],
]);

// Add fields
$fields = [
    ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'required' => true],
    ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
    ['type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => true],
];

foreach ($fields as $index => $field) {
    FormField::create([
        'form_id' => $form->id,
        'order' => $index + 1,
        ...$field,
    ]);
}
```

## Duplicating Forms

To duplicate a form with all its fields:

1. Go to the form list
2. Click the duplicate icon on the form row
3. Edit the duplicated form as needed

Or programmatically:

```php
use ArtisanPackUI\Forms\Services\FormService;

$formService = app(FormService::class);
$newForm = $formService->duplicate($originalForm);
```

## Next Steps

- [Form Renderer](./form-renderer.md) - Display forms on your site
- [Multi-Step Forms](./multi-step-forms.md) - Create multi-step forms
- [Conditional Logic](./conditional-logic.md) - Add conditional field visibility
