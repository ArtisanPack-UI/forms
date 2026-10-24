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

### Name

A Name field collects a person's name in one of three **Name Format** presets:

| Format | Inputs |
|--------|--------|
| **Simple** | One `full` input (`autocomplete="name"`). The most inclusive choice, since many names do not split into first and last. |
| **Normal** (default) | First and Last shown; Prefix, Middle and Suffix available but off until you turn them on. |
| **Extended** | Prefix, First, Middle, Last and Suffix, all shown. |

Each part has its own `autocomplete` token (`honorific-prefix`, `given-name`, `additional-name`, `family-name`, `honorific-suffix`). In the **Inputs** table you can show or hide each part, change its sub-label and placeholder, give it a default value, set a population **Parameter** (filled from the renderer `prefill`, the query string, then the `ap.forms.fieldValue.{parameter}` filter) and mark it required. When a required field marks no part, every shown part is required.

The prefix is a select of **Prefix Choices** (default: Mr., Mrs., Ms., Mx., Dr., Prof., Rev.). The suffix is free text, or a select of **Suffix Choices** when **Suffix Input** is set to a list.

```php
FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'name',
    'name'         => 'name',
    'label'        => 'Your Name',
    'is_required'  => true,
    'field_config' => [
        'name_format'    => 'extended',
        'prefix_choices' => ['Dr.', 'Prof.'],
        'sub_inputs'     => ['middle' => ['enabled' => false]],
    ],
] );
```

The value is stored as a map in `value_array` (`['first' => 'Jane', 'last' => 'Doe']`) and shown as the filled parts joined with spaces ("Dr. Jane Doe"). Merge tags: `{name}` for the full name and `{name:first}`, `{name:last}` and so on for each shown part. Exports get one column per shown part, or one formatted column with **Export Format** set to a single column.

### Address

An Address field collects Street Address, Address Line 2, City, State / Province / Region, ZIP / Postal Code and Country (`address-line1`, `address-line2`, `address-level2`, `address-level1`, `postal-code`, `country-name`). Each part can be shown or hidden, relabelled and required in the **Inputs** table.

| Setting | Description |
|---------|-------------|
| **Address Type** | **International** (free-text state, any postal code, country select), **United States** (state select, ZIP code such as `12345` or `12345-6789`, country fixed to the US) or **Canada** (province select, postal code such as `K1A 0B1`, stored in upper case, country fixed to Canada). |
| **Default Country** | The country selected to start with. |
| **Hide country** | International addresses only: stores the default country without showing the input. |
| **Default State / Province** | For U.S. and Canadian addresses, use the two-letter code (`NY`, `ON`). |

The country list comes from the `ap.forms.countries` filter (ISO 3166-1 alpha-2 code => name), which the **Countries** predefined choice list shares:

```php
addFilter( 'ap.forms.countries', function ( array $countries ): array {
    $countries['XK'] = __( 'Kosovo' );

    return $countries;
} );
```

The value is stored as a map in `value_array`, including a fixed country, and shown as a multi-line postal address:

```text
1 Main St
Albany, NY 12207
United States
```

Merge tags are `{address}` and one per part, such as `{address:city}` and `{address:postal_code}`.

**Address autocomplete providers.** Each input carries `data-forms-part="{key}"` and the fieldset `data-forms-composite="address"`, so a provider script (for example the `artisanpack-ui/bing-places` package) can find them. In the Livewire renderer, the `ap.forms.addressAutocomplete` filter's HTML is printed after the field:

```php
addFilter( 'ap.forms.addressAutocomplete', function ( string $html, FormField $field ): string {
    return $html . view( 'my-places::address-autocomplete', ['field' => $field] )->render();
} );
```

A provider that fills the inputs should set their values and dispatch an `input` event, so Livewire and Vue pick up the change. React inputs need the native value setter before the event.

### Email Confirmation

Turn on **Enable email confirmation** on an email field to ask for the address twice. The field shows two inputs labelled by **Email Sub-Label** (default "Enter Email") and **Confirm Sub-Label** (default "Confirm Email"), with an optional **Confirm Placeholder**. The confirmation is submitted beside the field as `{name}_confirmation` (`data[email_confirmation]` in the API) and checked with Laravel's `confirmed` rule, so a mismatch shows as the email field's error. Only the email is stored; display, notifications and exports are unchanged. Pasting into the confirm input is never blocked, which matters for password-manager and assistive-technology users. Avoid naming another field `{name}_confirmation`.

### Consent

A Consent field is a checkbox for GDPR or terms acceptance that records what was agreed to.

| Setting | Description |
|---------|-------------|
| **Checkbox Text** | The text beside the checkbox (default "I agree to the privacy policy."). |
| **Consent Description** | Shown below the checkbox. Links and basic formatting are allowed; the HTML is sanitized with kses. |

A required consent field must be checked (`accepted`), even if the visitor checks and then unchecks it. A checked consent is stored in `value_array` as:

```php
[
    'consented'    => '1',
    'text'         => 'I agree to the privacy policy.',
    'description'  => 'Read our <a href="/privacy">privacy policy</a>.',
    'revision'     => '9f2c…', // SHA-256 of the text and description
    'consented_at' => '2026-10-24T12:00:00+00:00',
]
```

The `revision` hash changes whenever the text or description does, so you can tell which version each visitor agreed to. Set `privacy.consent.store_text` to `false` (`FORMS_CONSENT_STORE_TEXT=false`) to keep only the hash and time. Submissions show "Consented" with a **View consent text** disclosure; webhooks, the API and the JSON export receive the stored object. An unchecked optional consent stores `['consented' => '0']` and shows "Not consented".

### CAPTCHA

A CAPTCHA field adds a challenge where you place it, for example on the last step of a multi-step form. Pick a **Provider** (or the default from config), a **Widget Size** (normal, compact or invisible) and a **Theme**. See [Spam Protection](Spam-Protection#captcha) for keys, providers and accessibility notes. The token is checked on submit and never stored.

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

- [Form Renderer](Form-Renderer) - Display forms on your site
- [Multi-Step Forms](Multi-Step-Forms) - Create multi-step forms
- [Conditional Logic](Conditional-Logic) - Add conditional field visibility
