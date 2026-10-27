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
// The types shipped with the package.
type BuiltInFieldType =
    | 'text' | 'email' | 'textarea' | 'select' | 'select_multiple'
    | 'checkbox' | 'checkbox_group' | 'radio'
    | 'number' | 'phone' | 'url' | 'file' | 'date' | 'time'
    | 'datetime' | 'date_range' | 'list'
    | 'toggle' | 'rating' | 'slider' | 'nps' | 'matrix' | 'ranking'
    | 'name' | 'address' | 'signature' | 'consent' | 'captcha'
    | 'section' | 'heading' | 'paragraph' | 'divider' | 'html' | 'hidden';

// Built-in literals (for autocomplete) plus any host-registered string type.
type FieldType = BuiltInFieldType | ( string & {} );
```

The `(string & {})` arm lets a host application register a custom field type
(via `registerFieldComponent`) and hold it across `FormField`,
`StoreFieldRequest`, and the palette/editor props. See
[Custom Field Types](React#custom-field-types).

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

This shows the core keys. Since 1.6.0 fields also carry optional, type-specific keys such as `is_read_only`, `autocomplete`, `sub_inputs`, `calculation`, `date_limits`, `resolved_default_value` and `settings_schema`; see the published `artisanpack-forms.d.ts` for their types and [API Resources](Api-Resources#formfieldresource) for what each holds.

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
    max_words?: number;      // textarea word limit (since 1.6.0)
    integer_only?: boolean;  // number fields (since 1.6.0)
    email_validation?: EmailValidation;  // 'rfc' | 'dns' | 'strict' (since 1.6.0)
    allowed_domains?: string[];          // email fields (since 1.6.0)
    blocked_domains?: string[];          // email fields (since 1.6.0)
    allowed_schemes?: string[];          // URL fields, default http and https (since 1.6.0)
    date_range?: DateRange | '';         // 'no_past' | 'no_future' (since 1.6.0)
    disable_weekends?: boolean;          // date fields (since 1.6.0)
    disabled_dates?: string[];           // YYYY-MM-DD (since 1.6.0)
    min_age?: number;                    // date fields (since 1.6.0)
    max_age?: number;                    // date fields (since 1.6.0)
}
```

Since 1.6.0 text, phone, number and file fields have typed configs (`TextFieldConfig`, `PhoneFieldConfig`, `NumberFieldConfig`, `FileFieldConfig`) for input masks, counters, phone formats, number formats and multi-file uploads, along with the `InputMaskPreset`, `PhoneFormat`, `PhoneCountry` and `NumberFormat` types. The render payload adds `input_mask`, `affix_description` and `phone` to a field, and a submission value has `upload_ids`.

Since 1.6.0 `min_date` and `max_date` may also be relative (`today`, `+30 days`, `-18 years`). Textarea, date and time fields have typed configs (`TextareaFieldConfig` with `rich_text`, `DateFieldConfig` with `date_format` and `date_input`, `TimeFieldConfig` with `time_format` and `time_input`), along with the `EmailValidation`, `DateRange`, `DateFormat`, `DateInputMode`, `TimeFormat` and `TimeInputMode` types. A setting's `input` may be `date_expression`, a text input that takes a date or a relative date. A submission value has `display_html`: a rich text value's sanitized HTML, or null when `display_value` is plain text.

Since 1.6.0 the `section`, `datetime`, `date_range` and `list` field types have typed configs (`SectionFieldConfig`, `DateTimeFieldConfig`, `DateRangeFieldConfig`, `ListFieldConfig`), a `SubInputType` may be `date`, a heading's `ContentFieldConfig` takes `align`, and a file's `FileFieldConfig` takes `storage` (`forms` or `media_library`). The render payload adds `content_html` to a paragraph (its sanitized, formatted HTML), `format_separator` to a composite field, and `list` (its parsed `ListColumn[]` columns, `min_rows`, `max_rows` and `row_label`) to a list field. A list's `resolved_default_value` is its starting rows. A list field's display HTML (`display_html`) is a table of its rows.

Since 1.6.0 the `slider`, `nps`, `matrix`, `ranking` and `signature` field types have typed configs (`SliderFieldConfig`, `NpsFieldConfig`, `MatrixFieldConfig`, `RankingFieldConfig`, `SignatureFieldConfig`). The render payload adds:

- `slider` (`SliderSettings`: the resolved `min`, `max`, `step`, `prefix`, `suffix` and `show_value`) to a slider.
- `nps` (`low` and `high` end labels) to an NPS field.
- `matrix` (`single`, its `MatrixRow[]` rows and `MatrixColumn[]` columns) to a matrix.
- `signature` (`pad_height` and `max_bytes`) to a signature field.

A matrix's `resolved_default_value` maps each row key to `''`. A ranking's is every option value in the order the list starts in. A ranking's display HTML is an ordered list, and a drawn signature's is an `<img>` with the PNG inline.

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
