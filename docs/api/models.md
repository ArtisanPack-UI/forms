---
title: Models
---

# Models

Eloquent model reference for ArtisanPack UI Forms.

## Form

The main form model representing a form definition.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `user_id` | int\|null | Owner user ID |
| `name` | string | Form name |
| `slug` | string | URL-friendly identifier |
| `description` | string\|null | Form description |
| `is_active` | bool | Whether form accepts submissions |
| `is_multi_step` | bool | Multi-step form flag |
| `success_message` | string\|null | Post-submission message |
| `redirect_url` | string\|null | Post-submission redirect |
| `settings` | array | Form settings JSON |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

### Relationships

```php
// Fields belonging to this form
$form->fields; // HasMany<FormField>

// Steps for multi-step forms
$form->steps; // HasMany<FormStep>

// Form submissions
$form->submissions; // HasMany<FormSubmission>

// Email notifications
$form->notifications; // HasMany<FormNotification>

// Owner (if ownership is enabled)
$form->user; // BelongsTo<User>
```

### Scopes

```php
// Active forms only
Form::active()->get();

// Multi-step forms
Form::multiStep()->get();

// Forms by owner
Form::forUser($userId)->get();
```

### Methods

```php
// Get form by slug
$form = Form::findBySlug('contact');

// Check if form is submittable
$form->canReceiveSubmissions(); // bool

// Get ordered fields
$form->getOrderedFields(); // Collection<FormField>

// Duplicate form with fields
$newForm = $form->duplicate();
```

### Example

```php
use ArtisanPackUI\Forms\Models\Form;

$form = Form::create([
    'name' => 'Contact Us',
    'slug' => 'contact',
    'description' => 'Get in touch with our team',
    'is_active' => true,
    'success_message' => 'Thank you for your message!',
    'settings' => [
        'submit_button_text' => 'Send Message',
        'label_position' => 'above',
    ],
]);
```

---

## FormField

Represents an individual field within a form.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `form_id` | int | Parent form ID |
| `step_id` | int\|null | Parent step ID (multi-step) |
| `type` | string | Field type |
| `name` | string | Field identifier |
| `label` | string | Display label |
| `placeholder` | string\|null | Placeholder text |
| `help_text` | string\|null | Help text |
| `required` | bool | Required flag |
| `order` | int | Display order |
| `options` | array\|null | Options for select/radio/checkbox |
| `validation` | array\|null | Validation rules |
| `conditions` | array\|null | Conditional logic |
| `settings` | array\|null | Additional settings |

### Relationships

```php
$field->form;  // BelongsTo<Form>
$field->step;  // BelongsTo<FormStep>
```

### Methods

```php
// Get Laravel validation rules
$rules = $field->getValidationRules(); // array

// Check if field is visible
$field->isVisible($formData); // bool

// Get field options
$field->getOptions(); // array
```

### Field Types

```php
// Text-based
'text', 'email', 'textarea', 'password', 'phone', 'url'

// Numeric
'number', 'range'

// Selection
'select', 'radio', 'checkbox', 'checkbox_group'

// Date/Time
'date', 'time', 'datetime'

// File
'file'

// Layout
'heading', 'html', 'divider', 'hidden'
```

---

## FormStep

Represents a step in a multi-step form.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `form_id` | int | Parent form ID |
| `title` | string | Step title |
| `description` | string\|null | Step description |
| `order` | int | Step order |
| `conditions` | array\|null | Conditional visibility |

### Relationships

```php
$step->form;    // BelongsTo<Form>
$step->fields;  // HasMany<FormField>
```

---

## FormSubmission

Represents a form submission.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `form_id` | int | Parent form ID |
| `submission_number` | string | Human-readable number |
| `ip_address` | string\|null | Submitter IP |
| `user_agent` | string\|null | Browser user agent |
| `metadata` | array\|null | Additional metadata |
| `created_at` | Carbon | Submission timestamp |

### Relationships

```php
$submission->form;     // BelongsTo<Form>
$submission->values;   // HasMany<FormSubmissionValue>
$submission->uploads;  // HasMany<FormUpload>
```

### Methods

```php
// Get all values as key-value array
$data = $submission->getFormData();
// ['name' => 'John', 'email' => 'john@example.com']

// Get specific value
$email = $submission->getValue('email');

// Get values with labels
$values = $submission->getValuesWithLabels();
// [['label' => 'Email', 'name' => 'email', 'value' => 'john@example.com']]

// Access metadata
$source = $submission->getMetadata('source');
```

---

## FormSubmissionValue

Stores individual field values for a submission.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `submission_id` | int | Parent submission ID |
| `field_id` | int\|null | Related field ID |
| `field_name` | string | Field name |
| `field_label` | string | Field label |
| `value` | text | Stored value |

### Relationships

```php
$value->submission;  // BelongsTo<FormSubmission>
$value->field;       // BelongsTo<FormField>
```

---

## FormUpload

Stores file upload metadata.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `submission_id` | int | Parent submission ID |
| `field_id` | int\|null | Related field ID |
| `original_name` | string | Original filename |
| `stored_name` | string | Storage filename |
| `disk` | string | Storage disk name |
| `path` | string | Storage path |
| `mime_type` | string | MIME type |
| `size` | int | File size in bytes |

### Relationships

```php
$upload->submission;  // BelongsTo<FormSubmission>
$upload->field;       // BelongsTo<FormField>
```

### Methods

```php
// Get download URL
$url = $upload->getDownloadUrl();

// Get human-readable file size
$size = $upload->humanFileSize(); // "1.5 MB"

// Check if image
$upload->isImage(); // bool
```

---

## FormNotification

Email notification configuration.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `form_id` | int | Parent form ID |
| `type` | string | `admin` or `autoresponder` |
| `name` | string | Notification name |
| `is_active` | bool | Active flag |
| `recipient_type` | string | `static` or `field` |
| `recipients` | string\|null | Static recipients |
| `recipient_field` | string\|null | Field for recipient |
| `cc` | string\|null | CC addresses |
| `bcc` | string\|null | BCC addresses |
| `from_email` | string\|null | From email |
| `from_name` | string\|null | From name |
| `reply_to_type` | string | `static` or `field` |
| `reply_to_email` | string\|null | Static reply-to |
| `reply_to_field` | string\|null | Field for reply-to |
| `subject` | string | Email subject |
| `message` | text | Email body |
| `include_submission_data` | bool | Include all data |
| `conditions` | array\|null | Send conditions |

### Constants

```php
FormNotification::TYPE_ADMIN = 'admin';
FormNotification::TYPE_AUTORESPONDER = 'autoresponder';
```

### Methods

```php
// Get resolved recipient email
$email = $notification->getRecipientEmail($submission);

// Get reply-to email
$replyTo = $notification->getReplyToEmail($submission);
```

## Next Steps

- [Services](./services.md) - Service class documentation
- [Events](./events.md) - Event documentation
