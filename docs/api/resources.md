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

All fields from `FormResource` plus:

| Field | Type | Description |
|-------|------|-------------|
| `fields` | array | All form fields (always included) |
| `steps` | array | All form steps (always included) |
| `config.honeypot.enabled` | boolean | Whether honeypot is active |
| `config.honeypot.field_name` | string | Honeypot field name |
| `config.display` | object | Display configuration |

## FormFieldResource

Transforms a `FormField` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Field ID |
| `form_id` | integer | Parent form ID |
| `uuid` | string | Unique field identifier |
| `name` | string | Field name attribute |
| `type` | string | Field type (text, email, select, etc.) |
| `label` | string | Display label |
| `placeholder` | string\|null | Placeholder text |
| `help_text` | string\|null | Help text below field |
| `is_required` | boolean | Whether field is required |
| `validation_rules` | object | Validation configuration |
| `field_config` | object | Additional field settings |
| `conditional_logic` | object\|null | Visibility conditions |
| `width` | string | Field width (full, half, third, two-thirds) |
| `css_classes` | string\|null | Custom CSS classes |
| `sort_order` | integer | Display order |
| `options` | array\|null | Options for select/radio/checkbox fields |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |

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
| `ip_address` | string\|null | Submitter IP (if privacy allows) |
| `user_agent` | string\|null | Submitter user agent (if privacy allows) |
| `is_read` | boolean | Read status |
| `is_spam` | boolean | Spam flag |
| `is_starred` | boolean | Starred flag |
| `admin_notes` | string\|null | Admin notes |
| `created_at` | string | ISO 8601 timestamp |
| `updated_at` | string | ISO 8601 timestamp |
| `values` | array | Submission values (when loaded) |
| `uploads` | array | File uploads (when loaded) |

## FormSubmissionValueResource

Transforms a `FormSubmissionValue` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Value ID |
| `submission_id` | integer | Parent submission ID |
| `field_name` | string | Field name |
| `field_type` | string | Field type |
| `value` | mixed | Submitted value |

## FormUploadResource

Transforms a `FormUpload` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Upload ID |
| `submission_id` | integer | Parent submission ID |
| `field_name` | string | Upload field name |
| `original_filename` | string | Original file name |
| `mime_type` | string | File MIME type |
| `file_size` | integer | File size in bytes |
| `download_url` | string | URL to download the file |

## FormNotificationResource

Transforms a `FormNotification` model.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Notification ID |
| `form_id` | integer | Parent form ID |
| `type` | string | Notification type (admin, autoresponder, custom) |
| `subject` | string | Email subject |
| `recipients` | string | Recipient email(s) |
| `message` | string | Email body with placeholders |
| `is_enabled` | boolean | Whether notification is active |
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
