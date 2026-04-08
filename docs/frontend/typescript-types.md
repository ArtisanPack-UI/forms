---
title: TypeScript Type Definitions
---

# TypeScript Type Definitions

The Forms package ships with comprehensive TypeScript type definitions in `resources/types/artisanpack-forms.d.ts`. These types are published alongside the React or Vue components when using the `forms:install-frontend` command, or independently via `vendor:publish --tag=forms-types`.

## Publishing Types

```bash
# With frontend components
php artisan forms:install-frontend --stack=react

# Types only
php artisan vendor:publish --tag=forms-types
```

Types are published to `resources/types/artisanpack-forms.d.ts` (standalone) or `resources/js/vendor/artisanpack-forms/types/artisanpack-forms.d.ts` (with frontend components).

## Field Types

```typescript
type FieldType =
    | 'text' | 'email' | 'textarea' | 'select' | 'select_multiple'
    | 'checkbox' | 'checkbox_group' | 'radio' | 'toggle'
    | 'number' | 'phone' | 'url' | 'file' | 'date' | 'time'
    | 'heading' | 'paragraph' | 'divider' | 'html' | 'hidden';
```

## Conditional Logic Types

```typescript
type ConditionalOperator =
    | 'equals' | 'not_equals' | 'contains' | 'not_contains'
    | 'starts_with' | 'ends_with'
    | 'greater_than' | 'less_than' | 'greater_or_equal' | 'less_or_equal'
    | 'is_empty' | 'is_not_empty'
    | 'in' | 'not_in' | 'includes' | 'not_includes'
    | 'checked' | 'unchecked' | 'between';

type ConditionalAction = 'show' | 'hide';
```

## Layout Types

```typescript
type FieldWidth = 'full' | 'half' | 'third' | 'two-thirds';
type LabelPosition = 'above' | 'beside' | 'hidden';
type ErrorDisplay = 'below' | 'tooltip' | 'summary';
type NotificationType = 'admin' | 'autoresponder' | 'custom';
```

## Model Interfaces

### FormField

```typescript
interface FormField {
    id: number;
    form_id: number;
    uuid: string;
    name: string;
    type: FieldType;
    label: string;
    placeholder: string | null;
    help_text: string | null;
    is_required: boolean;
    validation_rules: ValidationRules;
    field_config: Record<string, unknown>;
    conditional_logic: ConditionalLogic | null;
    width: FieldWidth;
    css_classes: string | null;
    sort_order: number;
    options?: FieldOption[];
    created_at: string;
    updated_at: string;
}
```

### FormStep

```typescript
interface FormStep {
    id: number;
    form_id: number;
    title: string;
    description: string | null;
    sort_order: number;
    next_button_text: string | null;
    prev_button_text: string | null;
    fields?: FormField[];
}
```

### Form

```typescript
interface Form {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    submit_button_text: string;
    success_message: string;
    redirect_url: string | null;
    is_multi_step: boolean;
    show_progress_bar: boolean;
    allow_step_navigation: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    fields?: FormField[];
    steps?: FormStep[];
    notifications?: FormNotification[];
    total_submissions_count?: number;
    unread_submissions_count?: number;
}
```

### FormRenderData

The payload returned by the public `/render` endpoint, used by the form renderer components:

```typescript
interface FormRenderData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    submit_button_text: string;
    success_message: string;
    redirect_url: string | null;
    is_multi_step: boolean;
    show_progress_bar: boolean;
    allow_step_navigation: boolean;
    fields: FormField[];
    steps: FormStep[];
    config: {
        honeypot: {
            enabled: boolean;
            field_name: string;
        };
        display: Record<string, unknown>;
    };
}
```

### FormSubmission

```typescript
interface FormSubmission {
    id: number;
    form_id: number;
    submission_number: string;
    page_url: string | null;
    referrer_url: string | null;
    ip_address?: string | null;
    user_agent?: string | null;
    is_read: boolean;
    is_spam: boolean;
    is_starred: boolean;
    admin_notes: string | null;
    created_at: string;
    updated_at: string;
    values?: FormSubmissionValue[];
    uploads?: FormUpload[];
}
```

## Validation Rules

```typescript
interface ValidationRules {
    min?: number;
    max?: number;
    pattern?: string;
    step?: number;
    min_date?: string;
    max_date?: string;
    max_size?: number;       // in KB
    allowed_types?: string[];  // MIME types
}
```

## Conditional Logic

```typescript
interface ConditionalLogicRule {
    field: string;           // UUID of the target field
    operator: ConditionalOperator;
    value: unknown;
}

interface ConditionalLogic {
    action: ConditionalAction;
    logic: 'all' | 'any';   // AND or OR
    rules: ConditionalLogicRule[];
}
```

## API Request Types

```typescript
interface SubmitFormRequest {
    data: Record<string, unknown>;
    files?: Record<string, File | File[]>;
}

interface BulkSubmissionRequest {
    ids: number[];
    action: 'mark_read' | 'mark_unread' | 'mark_spam' | 'delete';
}
```

## API Response Types

```typescript
interface SubmitFormResponse {
    success: boolean;
    message: string;
    submission_id: number;
    redirect_url: string | null;
}

interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

interface ValidationErrorResponse {
    message: string;
    errors: Record<string, string[]>;
}
```

## Next Steps

- [React Components](React) - React implementation
- [Vue Components](Vue) - Vue implementation
- [REST API](Api-Rest-Api) - API endpoint reference
