# Implementation Order

**Purpose:** Define the development sequence, phase dependencies, and milestones for building the forms package.

---

## Overview

This document outlines the recommended order for implementing the forms package. The phases are organized to:

- Build foundational components first
- Minimize rework by establishing dependencies early
- Allow for incremental testing at each phase
- Enable parallel development where possible

---

## Phase 1: Foundation

**Duration Estimate:** Core setup, models, and basic service provider

### 1.1 Package Structure

```
src/
├── FormsServiceProvider.php
├── Facades/
│   └── Forms.php
├── Models/
├── Livewire/
├── Services/
├── Mail/
├── Events/
├── Http/
│   └── Controllers/
├── helpers.php
config/
├── forms.php
database/
├── migrations/
├── factories/
resources/
├── views/
│   ├── components/
│   └── livewire/
routes/
├── web.php
tests/
├── Unit/
├── Feature/
└── TestCase.php
```

### 1.2 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| Service provider setup | Register config, routes, views, migrations | None |
| Configuration file | Create `config/forms.php` | None |
| Database migrations | All 7 tables | None |
| Eloquent models | All 7 models with relationships | Migrations |
| Model factories | Factories for all models | Models |
| Base test setup | TestCase with helper methods | Factories |

### 1.3 Deliverables

- [ ] Package installs without errors
- [ ] Migrations run successfully
- [ ] Models can be created via factories
- [ ] Basic test suite passes

---

## Phase 2: Form Management (CRUD)

**Goal:** Ability to create, read, update, delete forms via admin UI

### 2.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| FormService | Business logic for form operations | Models |
| FormController | API/web endpoints for form CRUD | FormService |
| FormsList component | Livewire component listing all forms | Models |
| CreateForm modal | Form creation interface | FormService |
| Form settings | Basic form configuration (name, description) | Models |

### 2.2 Deliverables

- [ ] Forms can be created with name/description
- [ ] Forms list shows all forms with search/filter
- [ ] Forms can be duplicated
- [ ] Forms can be deleted (soft delete)
- [ ] Forms can be published/unpublished

---

## Phase 3: Field Types System

**Goal:** Extensible field type registry with all 16 field types

### 3.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| FieldTypeRegistry | Singleton registry for field types | None |
| Base field config | Common configuration structure | Registry |
| Basic fields | text, textarea, email, number, phone, url, password | Registry |
| Choice fields | select, checkbox, radio, checkbox_group | Basic fields |
| Advanced fields | date, time, file, hidden | Choice fields |
| Layout fields | heading, paragraph, divider | Advanced fields |
| Field validation rules | Validation rule builder | All fields |

### 3.2 Deliverables

- [ ] All 16 field types registered
- [ ] Each field type has validation rules
- [ ] Field types are filterable via hooks
- [ ] Custom field types can be registered

---

## Phase 4: Form Builder UI

**Goal:** Drag-and-drop form builder with field management

### 4.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| FormBuilder component | Main Livewire component | Phase 2, Phase 3 |
| Field palette | Draggable field type list | FieldTypeRegistry |
| Canvas area | Drop zone with sortable fields | FormBuilder |
| Drag-drop integration | SortableJS integration | Canvas |
| FieldEditor component | Sidebar for field configuration | Field palette |
| Field operations | Add, remove, duplicate, reorder | Canvas |
| Form preview | Preview mode toggle | FormBuilder |

### 4.2 Deliverables

- [ ] Fields can be dragged from palette to canvas
- [ ] Fields can be reordered via drag-drop
- [ ] Field properties can be edited in sidebar
- [ ] Fields can be duplicated and deleted
- [ ] Form can be previewed

---

## Phase 5: Form Renderer

**Goal:** Public-facing form display with submission handling

### 5.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| FormRenderer component | Livewire component for display | Phase 4 |
| Field templates | Blade partials for each field type | FieldTypeRegistry |
| Form validation | Server-side validation | Field templates |
| SubmissionService | Handles form submission | Models |
| Success/error states | User feedback after submission | FormRenderer |
| Honeypot protection | Spam protection | FormRenderer |
| Rate limiting | Submission throttling | FormRenderer |

### 5.2 Deliverables

- [ ] Forms render correctly with all field types
- [ ] Validation errors display properly
- [ ] Submissions are saved to database
- [ ] Success message shows after submission
- [ ] Spam protection works

---

## Phase 6: File Uploads

**Goal:** Secure file upload handling for file fields

### 6.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| UploadService | File storage and retrieval | Phase 5 |
| Livewire file handling | WithFileUploads integration | FormRenderer |
| File validation | Type, size restrictions | UploadService |
| FormUpload model usage | Link uploads to submissions | SubmissionService |
| Secure file access | Protected download routes | UploadService |

### 6.2 Deliverables

- [ ] Files can be uploaded via form
- [ ] Files are stored securely
- [ ] File metadata is saved to database
- [ ] Files can be downloaded from admin

---

## Phase 7: Multi-Step Forms

**Goal:** Wizard-style forms with step navigation

### 7.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| Step management in builder | Add/remove/reorder steps | Phase 4 |
| Step editor | Configure step title, description, buttons | Step management |
| Field-step association | Assign fields to steps | Step editor |
| Step navigation in renderer | Next/previous buttons | Phase 5 |
| Progress indicators | Step progress display | Step navigation |
| Per-step validation | Validate before advancing | Step navigation |

### 7.2 Deliverables

- [ ] Multi-step mode can be enabled/disabled
- [ ] Steps can be added and configured
- [ ] Fields can be assigned to steps
- [ ] Users can navigate between steps
- [ ] Progress is shown clearly

---

## Phase 8: Conditional Logic

**Goal:** Show/hide fields based on other field values

### 8.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| Condition builder UI | Visual rule builder in field editor | Phase 4 |
| Condition JSON structure | Store conditions in field config | Condition builder |
| Alpine.js evaluation | Client-side condition checking | FormRenderer |
| PHP evaluation | Server-side condition checking | SubmissionService |
| Conditional validation | Skip validation for hidden fields | PHP evaluation |

### 8.2 Deliverables

- [ ] Conditions can be configured per field
- [ ] Fields show/hide based on conditions
- [ ] Multiple conditions with AND/OR logic
- [ ] Hidden fields are excluded from validation

---

## Phase 9: Email Notifications

**Goal:** Configurable email notifications on submission

### 9.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| NotificationEditor component | UI for managing notifications | Phase 5 |
| FormNotification model usage | Store notification config | NotificationEditor |
| PlaceholderService | Merge field replacement | FormNotification |
| FormSubmissionNotification mailable | Email template | PlaceholderService |
| NotificationService | Send logic with conditions | Mailable |
| Queue integration | Async email sending | NotificationService |

### 9.2 Deliverables

- [ ] Multiple notifications per form
- [ ] Configurable recipients (static, field-based)
- [ ] Placeholder replacement in subject/body
- [ ] Conditional sending based on field values
- [ ] Emails are queued for performance

---

## Phase 10: Submissions Management

**Goal:** Admin interface for viewing and managing submissions

### 10.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| SubmissionsList component | Livewire table of submissions | Phase 5 |
| Submission filtering | Filter by date, status, search | SubmissionsList |
| SubmissionDetail component | View single submission | SubmissionsList |
| Bulk operations | Select all, delete, export | SubmissionsList |
| ExportService | CSV export functionality | Bulk operations |
| Submission notes/status | Internal tracking features | SubmissionDetail |

### 10.2 Deliverables

- [ ] Submissions are listed in admin
- [ ] Submissions can be filtered and searched
- [ ] Individual submissions can be viewed
- [ ] Submissions can be exported to CSV
- [ ] Bulk delete works

---

## Phase 11: Integration Hooks

**Goal:** Extensibility via artisanpack-ui/hooks

### 11.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| Define action hooks | Document all action points | All phases |
| Define filter hooks | Document all filter points | All phases |
| Implement hooks | Add doAction/applyFilters calls | All phases |
| Laravel events | Dispatch standard Laravel events | Actions |
| Webhook support | Optional webhook on submission | Phase 9 |

### 11.2 Deliverables

- [ ] All hooks are documented
- [ ] Hooks fire at appropriate points
- [ ] Third-party packages can integrate
- [ ] Laravel events work for native listeners

---

## Phase 12: Polish & Documentation

**Goal:** Production readiness

### 12.1 Tasks

| Task | Description | Dependencies |
|------|-------------|--------------|
| Error handling | Consistent error responses | All phases |
| Loading states | Wire:loading on all actions | All phases |
| Accessibility audit | ARIA labels, keyboard nav | All phases |
| Performance optimization | Eager loading, caching | All phases |
| Comprehensive tests | Full test coverage | All phases |
| README documentation | Usage documentation | All phases |
| Artisan commands | form:list, form:export, etc. | All phases |

### 12.2 Deliverables

- [ ] All tests pass
- [ ] No accessibility issues
- [ ] Performance is acceptable
- [ ] Documentation is complete
- [ ] Package is ready for release

---

## Dependency Graph

```
Phase 1 (Foundation)
    │
    ├── Phase 2 (Form CRUD)
    │       │
    │       └── Phase 3 (Field Types)
    │               │
    │               └── Phase 4 (Form Builder)
    │                       │
    │                       ├── Phase 5 (Form Renderer)
    │                       │       │
    │                       │       ├── Phase 6 (File Uploads)
    │                       │       │
    │                       │       ├── Phase 9 (Notifications)
    │                       │       │
    │                       │       └── Phase 10 (Submissions)
    │                       │
    │                       ├── Phase 7 (Multi-Step)
    │                       │
    │                       └── Phase 8 (Conditional Logic)
    │
    └── Phase 11 (Integration Hooks) ←── runs parallel throughout
            │
            └── Phase 12 (Polish)
```

---

## Parallel Development Opportunities

These components can be developed simultaneously:

### Track A: Builder
- Phase 4 → Phase 7 → Phase 8

### Track B: Renderer
- Phase 5 → Phase 6 → Phase 9 → Phase 10

### Track C: Cross-cutting
- Phase 11 (hooks) can be added incrementally
- Phase 3 (field types) can expand over time

---

## Testing Milestones

| Milestone | Criteria |
|-----------|----------|
| M1: Foundation | Migrations run, models create, factories work |
| M2: Builder | Can create form with fields via UI |
| M3: Renderer | Can submit form and see submission saved |
| M4: Multi-Step | Can complete multi-step form |
| M5: Conditional | Fields show/hide correctly |
| M6: Notifications | Emails send on submission |
| M7: Management | Can view/export submissions in admin |
| M8: Release | All tests pass, docs complete |

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Drag-drop complexity | Use proven library (SortableJS) |
| Conditional logic edge cases | Comprehensive test coverage |
| File upload security | Use Laravel's built-in validation |
| Email deliverability | Queue jobs, logging, retry logic |
| Performance with many fields | Lazy loading, pagination |
| Breaking changes | Semantic versioning, deprecation warnings |

---

## Configuration Checklist

Before release, ensure `config/forms.php` includes:

```php
return [
    // Storage
    'disk' => 'forms',
    'upload_path' => 'form-uploads',

    // Limits
    'max_file_size' => 10240, // KB
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'png'],

    // Spam protection
    'honeypot' => [
        'enabled' => true,
        'field_name' => 'website_url',
    ],
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 5,
        'decay_minutes' => 1,
    ],

    // Notifications
    'queue_notifications' => true,
    'notification_queue' => 'default',

    // Integrations
    'webhooks' => [
        'enabled' => false,
    ],

    // UI
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'datetime_format' => 'Y-m-d H:i',
];
```

---

## Post-Release Roadmap

Features to consider after initial release:

1. **Form Templates** - Pre-built form templates (Contact, Survey, Registration)
2. **Analytics** - Conversion tracking, abandonment rates
3. **A/B Testing** - Test different form variations
4. **Payments** - Stripe/PayPal integration package
5. **Save & Resume** - Allow users to save progress
6. **Pre-population** - Fill fields from URL params or user data
7. **Calculated Fields** - Dynamic values based on other fields
8. **Repeater Fields** - Add multiple entries for a field group

---

## Related Documents

- [README.md](README.md) - Package overview
- [01-database-schema.md](01-database-schema.md) - Database structure
- [11-testing-strategy.md](11-testing-strategy.md) - Testing approach
