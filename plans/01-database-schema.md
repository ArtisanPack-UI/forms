# Database Schema

**Purpose:** Define all database tables, columns, indexes, and relationships for the forms package.

---

## Table Overview

| Table | Purpose |
|-------|---------|
| `forms` | Main form definitions |
| `form_fields` | Individual field configurations |
| `form_steps` | Multi-step form pages/steps |
| `form_submissions` | Submitted form data (header) |
| `form_submission_values` | Individual field values per submission |
| `form_notifications` | Email notification configurations |
| `form_uploads` | File upload metadata |

---

## Entity Relationship Diagram

```
┌─────────────────┐
│      forms      │
└────────┬────────┘
         │
    ┌────┴────┬──────────────┐
    │         │              │
    ▼         ▼              ▼
┌─────────┐ ┌───────────┐ ┌────────────────┐
│ form_   │ │ form_     │ │ form_          │
│ fields  │ │ steps     │ │ notifications  │
└────┬────┘ └─────┬─────┘ └────────────────┘
     │            │
     │ (fields belong to steps OR directly to form)
     │            │
     └────────────┘
              │
              ▼
    ┌─────────────────┐
    │ form_submissions│
    └────────┬────────┘
             │
    ┌────────┴────────┐
    │                 │
    ▼                 ▼
┌───────────────┐ ┌────────────┐
│ form_         │ │ form_      │
│ submission_   │ │ uploads    │
│ values        │ │            │
└───────────────┘ └────────────┘
```

---

## Table: `forms`

The main form definition table.

```sql
CREATE TABLE forms (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Basic Info
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,

    -- Display Settings
    submit_button_text VARCHAR(100) DEFAULT 'Submit',
    success_message TEXT NULL,
    redirect_url VARCHAR(500) NULL,

    -- Form Settings (JSON)
    settings JSON NULL,

    -- Multi-step Configuration
    is_multi_step BOOLEAN DEFAULT FALSE,
    show_progress_bar BOOLEAN DEFAULT TRUE,
    allow_step_navigation BOOLEAN DEFAULT FALSE,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Indexes
    INDEX idx_forms_slug (slug),
    INDEX idx_forms_is_active (is_active)
);
```

### `settings` JSON Structure

```json
{
    "spam_protection": {
        "honeypot": true,
        "rate_limit": true
    },
    "submission": {
        "store_submissions": true,
        "submission_limit": null
    },
    "display": {
        "label_position": "above",
        "show_required_indicator": true,
        "required_indicator": "*"
    }
}
```

---

## Table: `form_steps`

For multi-step forms, defines each step/page.

```sql
CREATE TABLE form_steps (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,

    -- Step Info
    title VARCHAR(255) NULL,
    description TEXT NULL,

    -- Ordering
    sort_order INT UNSIGNED DEFAULT 0,

    -- Navigation
    next_button_text VARCHAR(100) DEFAULT 'Next',
    prev_button_text VARCHAR(100) DEFAULT 'Previous',

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Foreign Keys
    CONSTRAINT fk_form_steps_form
        FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_form_steps_form_id (form_id),
    INDEX idx_form_steps_sort (form_id, sort_order)
);
```

---

## Table: `form_fields`

Individual field configurations.

```sql
CREATE TABLE form_fields (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,
    step_id BIGINT UNSIGNED NULL,  -- NULL for single-step forms

    -- Field Identity
    uuid VARCHAR(36) NOT NULL UNIQUE,  -- For frontend tracking
    name VARCHAR(100) NOT NULL,         -- Field name attribute

    -- Field Type
    type VARCHAR(50) NOT NULL,          -- text, email, select, etc.

    -- Labels & Display
    label VARCHAR(255) NULL,
    placeholder VARCHAR(255) NULL,
    help_text TEXT NULL,

    -- Validation
    is_required BOOLEAN DEFAULT FALSE,
    validation_rules JSON NULL,         -- Laravel validation rules

    -- Field-Type Specific Configuration
    field_config JSON NULL,             -- Options for select, file types, etc.

    -- Default Value
    default_value TEXT NULL,

    -- Conditional Logic
    conditional_logic JSON NULL,

    -- Layout
    width VARCHAR(20) DEFAULT 'full',   -- full, half, third, two-thirds
    css_classes VARCHAR(255) NULL,

    -- Ordering
    sort_order INT UNSIGNED DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Foreign Keys
    CONSTRAINT fk_form_fields_form
        FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_fields_step
        FOREIGN KEY (step_id) REFERENCES form_steps(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_form_fields_form_id (form_id),
    INDEX idx_form_fields_step_id (step_id),
    INDEX idx_form_fields_uuid (uuid),
    INDEX idx_form_fields_sort (form_id, step_id, sort_order)
);
```

### `validation_rules` JSON Structure

```json
{
    "min": 2,
    "max": 255,
    "pattern": null,
    "custom_message": "Please enter at least 2 characters"
}
```

### `field_config` JSON Structure (Examples)

**For Select/Radio/Checkbox Group:**
```json
{
    "options": [
        {"value": "option1", "label": "Option 1"},
        {"value": "option2", "label": "Option 2"}
    ],
    "allow_other": false
}
```

**For File Upload:**
```json
{
    "allowed_types": ["pdf", "doc", "docx"],
    "max_size": 5120,
    "max_files": 1
}
```

**For Date:**
```json
{
    "min_date": null,
    "max_date": null,
    "format": "Y-m-d"
}
```

### `conditional_logic` JSON Structure

```json
{
    "action": "show",
    "logic": "all",
    "rules": [
        {
            "field_uuid": "abc-123",
            "operator": "equals",
            "value": "yes"
        }
    ]
}
```

See [06-conditional-logic.md](06-conditional-logic.md) for detailed specification.

---

## Table: `form_submissions`

Header record for each form submission.

```sql
CREATE TABLE form_submissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,

    -- Submission Metadata
    submission_number VARCHAR(50) NOT NULL,  -- e.g., "FORM-2024-00001"

    -- Request Info
    page_url VARCHAR(500) NULL,
    referrer_url VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    is_spam BOOLEAN DEFAULT FALSE,
    is_starred BOOLEAN DEFAULT FALSE,

    -- Notes (admin can add notes)
    admin_notes TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Foreign Keys
    CONSTRAINT fk_form_submissions_form
        FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_submissions_form_id (form_id),
    INDEX idx_submissions_is_read (is_read),
    INDEX idx_submissions_is_spam (is_spam),
    INDEX idx_submissions_created_at (created_at),
    INDEX idx_submissions_number (submission_number)
);
```

---

## Table: `form_submission_values`

Individual field values for each submission.

```sql
CREATE TABLE form_submission_values (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NULL,  -- Can be NULL if field was deleted

    -- Field Reference (denormalized for deleted fields)
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(255) NULL,
    field_type VARCHAR(50) NOT NULL,

    -- Value Storage
    value TEXT NULL,           -- For simple values
    value_array JSON NULL,     -- For multi-select, checkbox groups

    -- File Reference (if field type is file)
    upload_id BIGINT UNSIGNED NULL,

    -- Timestamps
    created_at TIMESTAMP NULL,

    -- Foreign Keys
    CONSTRAINT fk_submission_values_submission
        FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_values_field
        FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE SET NULL,
    CONSTRAINT fk_submission_values_upload
        FOREIGN KEY (upload_id) REFERENCES form_uploads(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_submission_values_submission (submission_id),
    INDEX idx_submission_values_field (field_id)
);
```

---

## Table: `form_notifications`

Email notification configurations per form.

```sql
CREATE TABLE form_notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id BIGINT UNSIGNED NOT NULL,

    -- Notification Type
    type VARCHAR(50) NOT NULL,  -- 'admin', 'autoresponder', 'custom'
    name VARCHAR(255) NOT NULL,

    -- Recipients
    to_email VARCHAR(500) NULL,           -- Static email(s), comma-separated
    to_field VARCHAR(100) NULL,           -- Field name to get email from
    cc_emails VARCHAR(500) NULL,
    bcc_emails VARCHAR(500) NULL,
    reply_to_email VARCHAR(255) NULL,
    reply_to_field VARCHAR(100) NULL,     -- Field name to get reply-to from

    -- Email Content
    from_name VARCHAR(255) NULL,
    from_email VARCHAR(255) NULL,
    subject VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,                 -- Supports {field_name} placeholders

    -- Conditional Sending
    conditional_logic JSON NULL,           -- Same structure as field conditions

    -- Settings
    include_submission_data BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,

    -- Ordering
    sort_order INT UNSIGNED DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Foreign Keys
    CONSTRAINT fk_form_notifications_form
        FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_notifications_form_id (form_id),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_active (is_active)
);
```

### Notification Types

| Type | Purpose |
|------|---------|
| `admin` | Notifies site administrators |
| `autoresponder` | Automatic reply to form submitter |
| `custom` | Custom notification with any recipient |

---

## Table: `form_uploads`

Metadata for file uploads.

```sql
CREATE TABLE form_uploads (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    field_id BIGINT UNSIGNED NULL,

    -- File Info
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    disk VARCHAR(50) DEFAULT 'form-uploads',
    path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NULL,
    size BIGINT UNSIGNED NOT NULL,  -- bytes

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Foreign Keys
    CONSTRAINT fk_form_uploads_submission
        FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_form_uploads_field
        FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_uploads_submission (submission_id),
    INDEX idx_uploads_field (field_id)
);
```

---

## Migration Files

Migrations should be created in this order to handle dependencies:

1. `xxxx_xx_xx_000001_create_forms_table.php`
2. `xxxx_xx_xx_000002_create_form_steps_table.php`
3. `xxxx_xx_xx_000003_create_form_fields_table.php`
4. `xxxx_xx_xx_000004_create_form_submissions_table.php`
5. `xxxx_xx_xx_000005_create_form_uploads_table.php`
6. `xxxx_xx_xx_000006_create_form_submission_values_table.php`
7. `xxxx_xx_xx_000007_create_form_notifications_table.php`

---

## Storage Configuration

Add to `config/filesystems.php`:

```php
'disks' => [
    // ... existing disks

    'form-uploads' => [
        'driver' => 'local',
        'root' => storage_path('app/form-uploads'),
        'visibility' => 'private',
    ],
],
```

---

## Database Indexes Strategy

### Query Patterns and Supporting Indexes

| Query Pattern | Supporting Index |
|--------------|------------------|
| Find form by slug | `idx_forms_slug` |
| List active forms | `idx_forms_is_active` |
| Get fields for form | `idx_form_fields_form_id` |
| Get fields for step | `idx_form_fields_step_id` |
| Order fields | `idx_form_fields_sort` |
| Find submissions by form | `idx_submissions_form_id` |
| Filter unread submissions | `idx_submissions_is_read` |
| Recent submissions | `idx_submissions_created_at` |
| Get submission values | `idx_submission_values_submission` |

---

## Data Retention

The `submissions` table supports optional automatic cleanup via the `PruneSubmissionsCommand`:

```bash
# In scheduler
$schedule->command('forms:prune-submissions')->daily();
```

Retention period is configurable in `config/forms.php`:

```php
'submissions' => [
    'retention_days' => 365, // Keep for 1 year
    // null = keep forever
],
```

---

## Related Documents

- [02-models-and-relationships.md](02-models-and-relationships.md) - Eloquent models for these tables
- [05-field-types.md](05-field-types.md) - Field type configurations stored in `field_config`
- [06-conditional-logic.md](06-conditional-logic.md) - Conditional logic JSON structure
- [08-notifications.md](08-notifications.md) - Notification system using `form_notifications`
