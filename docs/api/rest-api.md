---
title: REST API
---

# REST API

The Forms package includes a full RESTful API for managing forms, fields, steps, submissions, and notifications programmatically. The API is secured with Laravel Sanctum and follows standard REST conventions.

## Configuration

The API is enabled by default. Configure it in `config/artisanpack/forms.php`:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/v1/forms',
    'middleware' => ['api', 'auth:sanctum'],
    'per_page' => 15,
],
```

## Authentication

Authenticated endpoints require a valid Sanctum token. Include the token in the `Authorization` header:

```
Authorization: Bearer your-api-token
```

Public endpoints (form rendering and submission) do not require authentication.

## Base URL

All endpoints are prefixed with the configured API prefix (default: `/api/v1/forms`).

## Response Format

### Single Resource

```json
{
    "data": {
        "id": 1,
        "name": "Contact Form",
        "slug": "contact-form"
    }
}
```

### Paginated Collection

```json
{
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "per_page": 15,
        "to": 15,
        "total": 42
    },
    "links": {
        "first": "http://example.com/api/v1/forms?page=1",
        "last": "http://example.com/api/v1/forms?page=3",
        "prev": null,
        "next": "http://example.com/api/v1/forms?page=2"
    }
}
```

---

## Forms

### List Forms

```
GET /
```

Returns a paginated list of forms. Requires `viewAny` policy authorization.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by `active` or `inactive` |
| `page` | integer | Page number |
| `per_page` | integer | Results per page (default from config) |

**Example:**

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "https://example.com/api/v1/forms?status=active"
```

### Create Form

```
POST /
```

Creates a new form. Requires `create` policy authorization.

**Request Body:**

```json
{
    "name": "Contact Form",
    "slug": "contact-form",
    "description": "Get in touch with us",
    "submit_button_text": "Send Message",
    "success_message": "Thank you for your message!",
    "redirect_url": null,
    "is_active": true,
    "is_multi_step": false,
    "show_progress_bar": false,
    "allow_step_navigation": false
}
```

**Response:** `201 Created` with `FormResource`

### Get Form

```
GET /{form}
```

Returns a single form with fields, steps, and submission counts. Requires `view` policy authorization.

**Response:** `200 OK` with `FormResource` including loaded relationships

### Update Form

```
PUT /{form}
```

Updates an existing form. Requires `update` policy authorization.

**Response:** `200 OK` with updated `FormResource`

### Delete Form

```
DELETE /{form}
```

Deletes a form and all associated data. Requires `delete` policy authorization.

**Response:** `204 No Content`

---

## Fields

### List Fields

```
GET /{form}/fields
```

Returns all fields for a form, ordered by `sort_order`.

### Create Field

```
POST /{form}/fields
```

Creates a new field on a form.

**Request Body:**

```json
{
    "type": "text",
    "label": "Full Name",
    "name": "full_name",
    "placeholder": "Enter your name",
    "help_text": "Your full legal name",
    "is_required": true,
    "width": "full",
    "step_id": null,
    "validation_rules": {
        "min": 2,
        "max": 100
    }
}
```

**Response:** `201 Created` with `FormFieldResource`

### Update Field

```
PUT /{form}/fields/{field}
```

Updates an existing field.

**Response:** `200 OK` with updated `FormFieldResource`

### Delete Field

```
DELETE /{form}/fields/{field}
```

**Response:** `204 No Content`

### Reorder Fields

```
POST /{form}/fields/reorder
```

Reorders fields by providing an array of UUIDs in the desired order.

**Request Body:**

```json
{
    "ordered_uuids": [
        "uuid-3",
        "uuid-1",
        "uuid-2"
    ],
    "step_id": null
}
```

**Response:** `200 OK`

---

## Steps

### List Steps

```
GET /{form}/steps
```

Returns all steps with their fields.

### Create Step

```
POST /{form}/steps
```

**Request Body:**

```json
{
    "title": "Personal Information",
    "description": "Tell us about yourself",
    "next_button_text": "Continue",
    "prev_button_text": "Go Back"
}
```

**Response:** `201 Created` with `FormStepResource`

### Update Step

```
PUT /{form}/steps/{step}
```

**Response:** `200 OK` with updated `FormStepResource`

### Delete Step

```
DELETE /{form}/steps/{step}
```

**Response:** `204 No Content`

### Reorder Steps

```
POST /{form}/steps/reorder
```

**Request Body:**

```json
{
    "ordered_ids": [3, 1, 2]
}
```

**Response:** `200 OK`

---

## Submissions

### List Submissions

```
GET /{form}/submissions
```

Returns paginated submissions for a form. Requires `viewSubmissions` policy authorization.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `is_read` | boolean | Filter by read status |
| `is_spam` | boolean | Filter by spam status |
| `is_starred` | boolean | Filter by starred status |

### Get Submission

```
GET /{form}/submissions/{submission}
```

Returns a single submission with values and uploads.

### Update Submission

```
PUT /{form}/submissions/{submission}
```

Updates submission metadata (not field values).

**Request Body:**

```json
{
    "is_read": true,
    "is_starred": false,
    "is_spam": false,
    "admin_notes": "Followed up via email"
}
```

**Response:** `200 OK` with updated `FormSubmissionResource`

### Delete Submission

```
DELETE /{form}/submissions/{submission}
```

**Response:** `204 No Content`

### Export Submissions

```
GET /{form}/submissions/export
```

Exports all submissions as a CSV file (streamed response).

### Bulk Actions

```
POST /{form}/submissions/bulk
```

Performs bulk actions on multiple submissions.

**Request Body:**

```json
{
    "ids": [1, 2, 3],
    "action": "mark_read"
}
```

**Available Actions:** `mark_read`, `mark_unread`, `mark_spam`, `mark_not_spam`, `delete`

**Response:** `200 OK` with affected count

### Download Upload

```
GET /submissions/{submission}/uploads/{upload}/download
```

Downloads a file attached to a submission.

---

## Notifications

### List Notifications

```
GET /{form}/notifications
```

Returns all notifications for a form. Requires `manageNotifications` policy authorization.

### Create Notification

```
POST /{form}/notifications
```

**Request Body:**

```json
{
    "type": "admin",
    "subject": "New Contact Form Submission",
    "recipients": "admin@example.com",
    "message": "A new submission was received for {{form_name}}.",
    "is_enabled": true
}
```

**Response:** `201 Created` with `FormNotificationResource`

### Update Notification

```
PUT /{form}/notifications/{notification}
```

**Response:** `200 OK` with updated `FormNotificationResource`

### Delete Notification

```
DELETE /{form}/notifications/{notification}
```

**Response:** `204 No Content`

---

## Public Endpoints

These endpoints do not require authentication.

### Render Form

```
GET /{form}/render
```

Returns the form definition for client-side rendering. Only returns active forms.

**Response:** `200 OK` with `FormRenderResource` including:
- Form fields and steps
- Honeypot configuration
- Display settings

### Submit Form

```
POST /{form}/submit
```

Submits a form response. Includes honeypot detection and rate limiting.

**Request Body:** `multipart/form-data` with field values and optional file uploads.

**Response:**

```json
{
    "success": true,
    "message": "Form submitted successfully.",
    "submission_id": 42,
    "redirect_url": null
}
```

**Error Responses:**
- `422`: Validation errors
- `429`: Rate limited

---

## Error Handling

### Validation Errors (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."],
        "slug": ["The slug has already been taken."]
    }
}
```

### Not Found (404)

```json
{
    "message": "No query results for model [Form]."
}
```

### Rate Limited (429)

```json
{
    "message": "Too many submissions. Please try again later."
}
```

## Next Steps

- [API Resources](Api-Resources) - Resource transformation details
- [TypeScript Types](Frontend-Typescript-Types) - Type definitions for API responses
- [React Components](Frontend-React) - React form renderer
- [Vue Components](Frontend-Vue) - Vue form renderer
