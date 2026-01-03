# Field Types

**Purpose:** Define all available field types, their configuration options, validation rules, and rendering details.

---

## Overview

The forms package includes 16 field types organized into four categories:

| Category | Field Types |
|----------|-------------|
| **Basic** | text, email, phone, textarea, number, url |
| **Choice** | select, radio, checkbox, checkbox_group |
| **Advanced** | date, time, file, hidden |
| **Layout** | heading, paragraph, divider, html |

---

## Field Type Configuration

Each field type is defined in `config/forms.php`:

```php
'field_types' => [
    'text' => [
        'label' => 'Text Input',
        'icon' => 'o-pencil',
        'category' => 'basic',
        'component' => 'forms::components.fields.text',
        'default_label' => 'Text Field',
        'default_config' => [],
        'validations' => ['required', 'min', 'max', 'pattern'],
        'settings' => [
            'placeholder' => ['type' => 'text'],
            'default_value' => ['type' => 'text'],
            'max_length' => ['type' => 'number', 'min' => 1],
        ],
    ],
    // ... additional field types
],
```

---

## Basic Fields

### Text

Standard single-line text input.

| Property | Value |
|----------|-------|
| Type key | `text` |
| Component | `<x-artisanpack-input type="text" />` |
| Validation | required, min, max, pattern |

**Configuration Options:**
- `placeholder` (string): Placeholder text
- `max_length` (int): Maximum character length
- `pattern` (string): Regex pattern for validation

**Example Usage:**
```blade
<x-artisanpack-input
    type="text"
    wire:model="formData.name"
    label="Your Name"
    placeholder="Enter your name"
    required
/>
```

---

### Email

Email address input with built-in validation.

| Property | Value |
|----------|-------|
| Type key | `email` |
| Component | `<x-artisanpack-input type="email" />` |
| Validation | required, email |

**Configuration Options:**
- `placeholder` (string): Placeholder text

**Auto-validation:** Laravel's `email` rule is automatically applied.

---

### Phone

Phone number input.

| Property | Value |
|----------|-------|
| Type key | `phone` |
| Component | `<x-artisanpack-input type="tel" />` |
| Validation | required, pattern |

**Configuration Options:**
- `placeholder` (string): Placeholder text
- `format` (string): Expected format hint (e.g., "(555) 555-5555")

**Note:** Phone validation uses a flexible pattern by default. For strict formatting, use a custom pattern.

---

### Textarea

Multi-line text input.

| Property | Value |
|----------|-------|
| Type key | `textarea` |
| Component | `<x-artisanpack-textarea />` |
| Validation | required, min, max |

**Configuration Options:**
- `placeholder` (string): Placeholder text
- `rows` (int): Number of visible rows (default: 4)
- `max_length` (int): Maximum character length
- `resize` (string): CSS resize behavior (none, vertical, horizontal, both)

---

### Number

Numeric input with optional min/max constraints.

| Property | Value |
|----------|-------|
| Type key | `number` |
| Component | `<x-artisanpack-input type="number" />` |
| Validation | required, numeric, min, max |

**Configuration Options:**
- `placeholder` (string): Placeholder text
- `min` (number): Minimum value
- `max` (number): Maximum value
- `step` (number): Step increment (e.g., 0.01 for decimals)

---

### URL

Website URL input.

| Property | Value |
|----------|-------|
| Type key | `url` |
| Component | `<x-artisanpack-input type="url" />` |
| Validation | required, url |

**Configuration Options:**
- `placeholder` (string): Placeholder text (default: "https://")

---

## Choice Fields

### Select (Dropdown)

Single-select dropdown menu.

| Property | Value |
|----------|-------|
| Type key | `select` |
| Component | `<x-artisanpack-select />` |
| Validation | required, in |

**Configuration Options:**
- `placeholder` (string): Placeholder/default option text
- `options` (array): Array of `{value, label}` objects
- `allow_other` (bool): Allow "Other" option with text input

**Options Format:**
```json
{
    "options": [
        {"value": "option1", "label": "Option 1"},
        {"value": "option2", "label": "Option 2"},
        {"value": "option3", "label": "Option 3"}
    ]
}
```

---

### Radio

Single-select radio button group.

| Property | Value |
|----------|-------|
| Type key | `radio` |
| Component | `<x-artisanpack-radio-group />` |
| Validation | required, in |

**Configuration Options:**
- `options` (array): Array of `{value, label}` objects
- `inline` (bool): Display options inline/horizontal (default: false)
- `card` (bool): Use card-style radio buttons (default: false)

**Rendering:**
```blade
<x-artisanpack-radio-group
    wire:model.live="formData.{{ $field->name }}"
    :label="$field->label"
    :required="$field->is_required"
    :options="$field->options"
    option-value="value"
    option-label="label"
    :horizontal="$field->getConfig('inline', false)"
    :card="$field->getConfig('card', false)"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
/>
```

---

### Checkbox

Single checkbox for boolean values.

| Property | Value |
|----------|-------|
| Type key | `checkbox` |
| Component | `<x-artisanpack-checkbox />` |
| Validation | required (if must be checked), accepted |

**Configuration Options:**
- None (label is the main configuration)

**Value Handling:**
- Checked: `"1"` or `true`
- Unchecked: `"0"` or `false`

---

### Checkbox Group

Multiple-select checkbox group.

| Property | Value |
|----------|-------|
| Type key | `checkbox_group` |
| Component | `<x-artisanpack-checkbox-group />` |
| Validation | required, array, min, max |

**Configuration Options:**
- `options` (array): Array of `{value, label}` objects
- `inline` (bool): Display options inline/horizontal (default: false)
- `card` (bool): Use card-style checkboxes (default: false)
- `min_selections` (int): Minimum selections required
- `max_selections` (int): Maximum selections allowed

**Value Handling:**
Returns an array of selected values.

**Rendering:**
```blade
<x-artisanpack-checkbox-group
    wire:model.live="formData.{{ $field->name }}"
    :label="$field->label"
    :required="$field->is_required"
    :options="$field->options"
    option-value="value"
    option-label="label"
    :horizontal="$field->getConfig('inline', false)"
    :card="$field->getConfig('card', false)"
    :error="$errors->first('formData.' . $field->name)"
    :hint="$field->help_text"
/>
```

---

## Advanced Fields

### Date

Date picker input.

| Property | Value |
|----------|-------|
| Type key | `date` |
| Component | `<x-artisanpack-datepicker />` or native input |
| Validation | required, date, after, before |

**Configuration Options:**
- `min_date` (string): Minimum selectable date (Y-m-d or "today")
- `max_date` (string): Maximum selectable date (Y-m-d or "today")
- `format` (string): Display format (default: "Y-m-d")
- `disable_weekends` (bool): Disable weekend selection

**Date Constraints:**
```php
// In validation rules
if ($minDate = $field->getConfig('min_date')) {
    $rules[] = "after_or_equal:{$minDate}";
}
if ($maxDate = $field->getConfig('max_date')) {
    $rules[] = "before_or_equal:{$maxDate}";
}
```

---

### Time

Time picker input.

| Property | Value |
|----------|-------|
| Type key | `time` |
| Component | `<x-artisanpack-input type="time" />` |
| Validation | required, date_format:H:i |

**Configuration Options:**
- `format` (string): 12h or 24h (default: "24h")
- `min_time` (string): Minimum time (HH:mm)
- `max_time` (string): Maximum time (HH:mm)
- `step` (int): Step in minutes (default: 15)

---

### File

File upload input.

| Property | Value |
|----------|-------|
| Type key | `file` |
| Component | Native file input with Livewire |
| Validation | required, file, mimes, max |

**Configuration Options:**
- `allowed_types` (array): Allowed file extensions (default from config)
- `max_size` (int): Max file size in KB (default from config)
- `max_files` (int): Max number of files (default: 1)

**Allowed Types Example:**
```json
{
    "allowed_types": ["pdf", "doc", "docx", "jpg", "png"],
    "max_size": 5120,
    "max_files": 3
}
```

**Upload Handling:**
Files are uploaded via Livewire's `WithFileUploads` trait and stored when the form is submitted.

---

### Hidden

Hidden input for passing data.

| Property | Value |
|----------|-------|
| Type key | `hidden` |
| Component | `<input type="hidden" />` |
| Validation | None (not user-editable) |

**Configuration Options:**
- `default_value` (string): The hidden value

**Use Cases:**
- Tracking source/campaign
- Passing page context
- Form identification

---

## Layout Fields

Layout fields don't collect data but help structure the form visually.

### Heading

Section heading within the form.

| Property | Value |
|----------|-------|
| Type key | `heading` |
| Component | `<h2>`, `<h3>`, etc. |
| Validation | None |

**Configuration Options:**
- `level` (string): Heading level (h2, h3, h4, h5, h6)
- `text` (string): Heading text (uses label field)

**Rendering:**
```blade
@php
    $tag = $field->getConfig('level', 'h3');
@endphp

<{{ $tag }} class="text-lg font-semibold mb-2">
    {{ $field->label }}
</{{ $tag }}>
```

---

### Paragraph

Descriptive text within the form.

| Property | Value |
|----------|-------|
| Type key | `paragraph` |
| Component | `<p>` |
| Validation | None |

**Configuration Options:**
- `text` (string): Paragraph content (stored in help_text field)

**Rendering:**
```blade
<div class="prose max-w-none">
    {!! nl2br(e($field->help_text)) !!}
</div>
```

---

### Divider

Visual separator between sections.

| Property | Value |
|----------|-------|
| Type key | `divider` |
| Component | `<hr />` or `<div class="divider" />` |
| Validation | None |

**Configuration Options:**
- `style` (string): solid, dashed, dotted (default: solid)
- `spacing` (string): sm, md, lg (default: md)

**Rendering:**
```blade
<div class="divider {{ $field->getConfig('spacing', 'my-4') }}"></div>
```

---

### HTML

Custom HTML content (admin-only).

| Property | Value |
|----------|-------|
| Type key | `html` |
| Component | Raw HTML output |
| Validation | None |

**Configuration Options:**
- `content` (string): HTML content

**Security:**
HTML content is sanitized using the `artisanpack-ui/security` package if available.

**Rendering:**
```blade
<div class="prose max-w-none">
    {!! $field->getConfig('content', '') !!}
</div>
```

---

## Field Type Registration

Custom field types can be registered via hooks:

```php
use function ArtisanPackUI\Hooks\addFilter;

addFilter('forms.field_types', function (array $fieldTypes) {
    $fieldTypes['rating'] = [
        'label' => 'Star Rating',
        'icon' => 'o-star',
        'category' => 'advanced',
        'component' => 'my-package::components.fields.rating',
        'default_label' => 'Rating',
        'default_config' => [
            'max_stars' => 5,
        ],
        'validations' => ['required', 'min', 'max'],
    ];

    return $fieldTypes;
});
```

---

## Validation Rule Builder

Each field type has a `buildValidationRules()` method that constructs Laravel validation rules:

```php
protected function getTypeValidationRules(): array
{
    return match ($this->type) {
        'email' => ['email:rfc,dns'],
        'url' => ['url'],
        'number' => ['numeric'],
        'date' => ['date'],
        'time' => ['date_format:H:i'],
        'file' => $this->getFileValidationRules(),
        'checkbox_group' => ['array'],
        'select' => ['in:' . implode(',', array_column($this->options, 'value'))],
        'radio' => ['in:' . implode(',', array_column($this->options, 'value'))],
        default => [],
    };
}
```

---

## Related Documents

- [03-form-builder.md](03-form-builder.md) - Field palette and editor
- [04-form-renderer.md](04-form-renderer.md) - Field rendering
- [06-conditional-logic.md](06-conditional-logic.md) - Field visibility
