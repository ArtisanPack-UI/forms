---
title: API Resources
---

# API Resources

The Forms package uses Laravel API Resources to transform Eloquent models into consistent JSON responses.

## FormResource

Transforms a `Form` model for authenticated API responses.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Form ID |
| `name` | string | Form name |
| `slug` | string | URL-friendly identifier |
| `description` | string\|null | Form description |
| `submit_button_text` | string | Submit button label |
| `success_message` | string | Message shown after submission |
| `redirect_url` | string\|null | URL to redirect after submission |
| `is_multi_step` | boolean | Whether form uses steps |
| `show_progress_bar` | boolean | Show progress indicator |
| `allow_step_navigation` | boolean | Allow jumping between steps |
| `is_active` | boolean | Whether form accepts submissions |
| `label_placement` | string\|null | The form's default label placement: `top`, `left` or `hidden` (since 1.6.0) |
| `description_placement` | string\|null | The form's default description placement: `above` or `below` (since 1.6.0) |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |
| `fields` | array | Form fields (when loaded) |
| `steps` | array | Form steps (when loaded) |
| `notifications` | array | Notifications (when loaded) |
| `total_submissions_count` | integer | Total submissions (when loaded) |
| `unread_submissions_count` | integer | Unread submissions (when loaded) |

## FormRenderResource

Transforms a `Form` model for public client-side rendering. Includes configuration needed by frontend components.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id`, `name`, `slug`, `description` | | As in `FormResource` |
| `submit_button_text`, `success_message`, `redirect_url` | | As in `FormResource` |
| `is_multi_step`, `show_progress_bar`, `allow_step_navigation` | | As in `FormResource` |
| `fields` | array | The public form fields (always included) |
| `steps` | array | All form steps, with their public fields (always included) |
| `config.honeypot.enabled` | boolean | Whether honeypot is active |
| `config.honeypot.field_name` | string | Honeypot field name |
| `config.display` | object | Display configuration |

The render payload differs from the authenticated field resources:

- Administrative fields are left out (since 1.6.0).
- Fields have no `settings_schema`.
- Each field adds `resolved_default_value`, `label_placement` and `description_placement` (see below).
- Option images that are not safe to show are dropped.

## FormFieldResource

Transforms a `FormField` model. Keys marked "when …" are only present in that case.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Field ID |
| `form_id` | integer | Parent form ID |
| `step_id` | integer\|null | Step ID, for multi-step forms |
| `uuid` | string | Unique field identifier |
| `name` | string | Field name attribute |
| `type` | string | Field type (text, email, select, etc.) |
| `label` | string | Display label |
| `placeholder` | string\|null | Placeholder text |
| `help_text` | string\|null | Help text |
| `is_required` | boolean | Whether field is required |
| `validation_rules` | object | Validation configuration |
| `field_config` | object | Type-specific settings. An HTML field's `content` and a consent field's `consent_description` are sanitized. |
| `default_value` | string\|null | Default value (sanitized for HTML fields) |
| `conditional_logic` | object\|null | Visibility conditions |
| `width` | string | Field width (full, half, third, two-thirds) |
| `css_classes` | string\|null | Custom CSS classes |
| `sort_order` | integer | Display order |
| `options` | array | Options for choice fields, when it has any. For a field that loads its choices from a source, the resolved choices. |
| `is_read_only` | boolean | Whether the field is read-only (since 1.6.0) |
| `read_only_value` | mixed | The value a read-only field submits, when read-only (since 1.6.0) |
| `autocomplete` | string\|null | The input's `autocomplete` token (since 1.6.0) |
| `error_message` | string | The custom error message, when set (since 1.6.0) |
| `required_message` | string | The custom required message, when set (since 1.6.0) |
| `dynamic_parameter` | string\|null | The dynamic population parameter name, when population is on (since 1.6.0) |
| `is_administrative` | boolean | Whether the field is administrative (since 1.6.0) |
| `other_choice_label` | string | The "Other" choice label, when the field offers one (since 1.6.0) |
| `sub_inputs` | array | A composite field's inputs, each with `key`, `label`, `default_label`, `placeholder`, `enabled`, `required`, `autocomplete`, `type` and more (since 1.6.0) |
| `required_sub_inputs` | array | A composite field's required input keys (since 1.6.0) |
| `sub_label_placement` | string | A composite field's sub-label placement: `below`, `above` or `hidden` (since 1.6.0) |
| `format_separator` | string | What a composite field's inputs are joined with when shown (since 1.6.0) |
| `email_confirmation` | object | `email`, `confirm` and `confirm_placeholder` labels, when confirmation is on (since 1.6.0) |
| `consent` | object | A consent field's `text` and sanitized `description` (since 1.6.0) |
| `captcha` | object\|null | A CAPTCHA field's public widget settings: `provider`, `site_key`, `script`, `size`, `theme` and, for a score-based provider, `action`. Null when the provider has no keys. Never the secret key. (since 1.6.0) |
| `input_mask` | object | `mask` and `hint`, when the field has an input mask (since 1.6.0) |
| `affix_description` | string | A number field's prefix/suffix description for screen readers, when set (since 1.6.0) |
| `content_html` | string | A paragraph's sanitized HTML (since 1.6.0) |
| `list` | object | A list field's `columns`, `min_rows`, `max_rows` and `row_label` (since 1.6.0) |
| `slider` | object | A slider's `min`, `max`, `step`, `prefix`, `suffix` and `show_value` (since 1.6.0) |
| `nps` | object | An NPS field's `low` and `high` labels (since 1.6.0) |
| `matrix` | object | A matrix field's `single` flag, `rows` and `columns` (since 1.6.0) |
| `calculation` | object | A calculation field's `formula` and `decimals` (since 1.6.0) |
| `signature` | object | A signature field's `pad_height` and `max_bytes` (since 1.6.0) |
| `phone` | object | A US or International phone field's `format`, `default_country` and `countries` (with calling codes) (since 1.6.0) |
| `date_limits` | object | A date, date and time or date range field's earliest and latest allowed dates, resolved on the server for today: `{ min, max }` as `YYYY-MM-DD`, null when unbounded (since 1.6.0) |
| `date_bounds` | object | The bounds a date field's input offers: `date_limits` narrowed by its age limits, `{ min, max }` (since 1.6.0) |
| `label_placement` | string\|null | The label placement set on the field or its form; render payload only (since 1.6.0) |
| `description_placement` | string\|null | The description placement set on the field or its form; render payload only (since 1.6.0) |
| `resolved_default_value` | mixed | The value the field starts with, after dynamic population; render payload only (since 1.6.0) |
| `settings_schema` | array | The type's settings schema for the builders; not in the render payload (since 1.6.0) |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |

`date_limits` and `date_bounds` are worked out in the application's timezone, so the React and Vue renderers use the same "today" as the server. In `resolved_default_value`, the server fills in `{user:…}`, `{date}` and `{ip}` and applies the `ap.forms.fieldValue.{parameter}` filter; page tags and the query string are left for the client. See [Dynamic Population](Usage-Dynamic-Population).

## FormStepResource

Transforms a `FormStep` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Step ID |
| `form_id` | integer | Parent form ID |
| `title` | string | Step title |
| `description` | string\|null | Step description |
| `sort_order` | integer | Step order |
| `next_button_text` | string\|null | Next button label |
| `prev_button_text` | string\|null | Previous button label |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |
| `fields` | array | Step fields (when loaded) |

## FormSubmissionResource

Transforms a `FormSubmission` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Submission ID |
| `form_id` | integer | Parent form ID |
| `submission_number` | string | Human-readable submission number |
| `page_url` | string\|null | Page where form was submitted |
| `referrer_url` | string\|null | Referring page |
| `ip_address` | string\|null | Submitter IP, only when `privacy.include_ip_address` is on |
| `user_agent` | string\|null | Submitter user agent, only when `privacy.include_user_agent` is on |
| `is_read` | boolean | Read status |
| `is_spam` | boolean | Spam flag |
| `is_starred` | boolean | Starred flag |
| `admin_notes` | string\|null | Admin notes |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |
| `values` | array | Submission values (when loaded) |
| `uploads` | array | File uploads (when loaded). Uploads of masked fields are left out for users who may not reveal masked values, and "Do not store" uploads never appear. |
| `administrative_fields` | array | The form's administrative fields, without their settings schema, when the submission is loaded with `form.fields`. Fields masked from the user are left out (since 1.6.0). |

## FormSubmissionValueResource

Transforms a `FormSubmissionValue` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Value ID |
| `submission_id` | integer | Parent submission ID |
| `field_id` | integer\|null | Field ID; null when the field has been deleted |
| `field_name` | string | Field name |
| `field_label` | string | The field's label when the value was submitted |
| `field_admin_label` | string\|null | The field's admin label when the value was submitted (since 1.6.0) |
| `field_type` | string | Field type |
| `value` | mixed | The stored value, or the masked string for a masked field (see below) |
| `value_array` | array\|null | The stored array value: chosen options, a composite field's inputs, a list's rows, upload IDs, a consent record. Null when masked. |
| `display_value` | string | The value as people read it (option labels, formatted dates). The masked string when masked. |
| `display_values` | array | The option labels of an array value (since 1.6.0). Empty when masked. |
| `display_html` | string\|null | Sanitized HTML for values shown as HTML, such as rich text or a list's table (since 1.6.0). Null when masked. |
| `is_masked` | boolean | Whether the value is masked for the current user (since 1.6.0) |
| `is_redacted` | boolean | Whether the value was redacted after its field's redaction period (since 1.6.0) |
| `upload_id` | integer\|null | A file value's first upload. Null when masked. |
| `upload_ids` | array | Every upload of a file value, in order (since 1.6.0). Empty when masked. |
| `created_at` | string | ISO 8601 timestamp |

**Masked values.** For a field with **Mask in admin** on, a user who may not reveal masked values gets `is_masked: true`. `value` and `display_value` then hold a masked string such as `••••1234` (the last four characters; values under eight characters are masked in full), **not** the stored data, and `value_array`, `display_values`, `display_html`, `upload_id` and `upload_ids` are empty. Check `is_masked` before you store or process a value. Users who may reveal masked values (`revealMaskedValues`) get the real value and `is_masked: false`. See [Field Privacy & Visibility](Usage-Field-Privacy).

A redacted value has `is_redacted: true` and no value.

## FormUploadResource

Transforms a `FormUpload` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Upload ID |
| `submission_id` | integer | Parent submission ID |
| `field_id` | integer\|null | The file field's ID |
| `original_name` | string | Original file name |
| `mime_type` | string | File MIME type |
| `size` | integer | File size in bytes |
| `human_size` | string | File size for display, such as `1.5 MB` |
| `is_image` | boolean | Whether the file is an image |
| `url` | string | URL to download the file |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |

## FormNotificationResource

Transforms a `FormNotification` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Notification ID |
| `form_id` | integer | Parent form ID |
| `type` | string | Notification type (admin, autoresponder, custom) |
| `name` | string | Notification name |
| `to_email` | string\|null | Fixed recipient email(s) |
| `to_field` | string\|null | Name of the field whose value is the recipient |
| `cc_emails`, `bcc_emails` | string\|null | CC and BCC recipients |
| `reply_to_email` | string\|null | Fixed reply-to address |
| `reply_to_field` | string\|null | Name of the field whose value is the reply-to address |
| `from_name`, `from_email` | string\|null | Sender |
| `subject` | string | Email subject |
| `message` | string | Email body with placeholders |
| `conditional_logic` | object\|null | Conditions for sending |
| `include_submission_data` | boolean | Whether the email includes the submitted values (and "Do not store" files, for admin notifications) |
| `is_active` | boolean | Whether notification is active |
| `sort_order` | integer | Order |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |

## PaginatedResourceCollection

A reusable wrapper that provides consistent pagination for any resource type.

**Usage:**

```php
use ArtisanPackUI\Forms\Http\Resources\PaginatedResourceCollection;

return new PaginatedResourceCollection(
    $query->paginate( $perPage )->withQueryString(),
    FormResource::class
);
```

**Response Structure:**

```json
{
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "to": 15,
        "total": 72
    },
    "links": {
        "first": "...?page=1",
        "last": "...?page=5",
        "prev": null,
        "next": "...?page=2"
    }
}
```

## Next Steps

- [REST API](Rest-Api) - Full endpoint reference
- [Models](Models) - Eloquent model documentation
- [Services](Services) - Service class documentation
