/**
 * ArtisanPack UI Forms - TypeScript Type Definitions
 *
 * Type definitions for all API response shapes, models, enums, and
 * specialized types used by the ArtisanPack UI Forms package.
 *
 * Publish with: php artisan vendor:publish --tag=forms-types
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

// ---------------------------------------------------------------------------
// Enum / Union Types
// ---------------------------------------------------------------------------

/**
 * The field types shipped with the package.
 *
 * These map one-to-one to the built-in field components, palette entries,
 * and editor settings. Host applications extend the set at runtime via the
 * field-component registry seam (see `registerFieldComponent`), so consumer
 * code should type against {@link FieldType} rather than this closed union.
 */
export type BuiltInFieldType =
    | 'text'
    | 'email'
    | 'phone'
    | 'number'
    | 'url'
    | 'textarea'
    | 'hidden'
    | 'select'
    | 'radio'
    | 'checkbox'
    | 'toggle'
    | 'checkbox_group'
    | 'select_multiple'
    | 'file'
    | 'date'
    | 'time'
    | 'rating'
    | 'heading'
    | 'paragraph'
    | 'divider'
    | 'html';

/**
 * All available form field types.
 *
 * Includes the {@link BuiltInFieldType} literals (preserved for editor
 * autocomplete) plus any host-registered string type. The `& {}` intersection
 * keeps the literal suggestions from collapsing into a bare `string`, while
 * still allowing custom types such as `booking_slot` to be assigned across
 * `FormField.type`, `StoreFieldRequest.type`, `FieldPaletteItem.type`, and the
 * admin editor props.
 */
export type FieldType = BuiltInFieldType | ( string & {} );

/**
 * Field type category groupings.
 */
export type FieldTypeCategory = 'basic' | 'choice' | 'advanced' | 'layout';

/**
 * Conditional logic operators.
 */
export type ConditionalOperator =
    | 'equals'
    | 'not_equals'
    | 'contains'
    | 'not_contains'
    | 'starts_with'
    | 'ends_with'
    | 'is_empty'
    | 'is_not_empty'
    | 'greater_than'
    | 'less_than'
    | 'greater_or_equal'
    | 'less_or_equal'
    | 'in'
    | 'not_in'
    | 'checked'
    | 'unchecked'
    | 'includes'
    | 'not_includes';

/**
 * Conditional logic actions.
 */
export type ConditionalAction = 'show' | 'hide';

/**
 * Conditional logic conjunction types.
 */
export type ConditionalLogicType = 'all' | 'any';

/**
 * Notification types.
 */
export type NotificationType = 'admin' | 'autoresponder' | 'custom';

/**
 * Field width options for layout.
 */
export type FieldWidth = 'full' | 'half' | 'third' | 'two-thirds';

/**
 * Display label position options.
 */
export type LabelPosition = 'above' | 'beside' | 'hidden';

/**
 * Where a field's label is shown (since 1.6.0). `left` sits beside the input
 * from the `md` breakpoint up; `hidden` keeps it for screen readers.
 */
export type LabelPlacement = 'top' | 'left' | 'hidden';

/**
 * Where a field's description (help text) is shown relative to its input (since 1.6.0).
 */
export type DescriptionPlacement = 'above' | 'below';

/**
 * Whether a field is on the public form or only filled in by admins (since 1.6.0).
 */
export type FieldVisibility = 'visible' | 'administrative';

/**
 * How a radio or checkbox group arranges its choices (since 1.6.0).
 */
export type ChoiceLayout = 'stacked' | 'inline' | '2-col' | '3-col';

/**
 * Where a composite field shows its sub-input labels (since 1.6.0).
 */
export type SubLabelPlacement = 'below' | 'above' | 'hidden';

/**
 * How a composite field is exported to CSV (since 1.6.0).
 */
export type CompositeExportFormat = 'columns' | 'single';

/**
 * The input type of a composite field's sub-input (since 1.6.0).
 */
export type SubInputType = 'text' | 'email' | 'tel' | 'url' | 'number';

/**
 * Error display mode options.
 */
export type ErrorDisplay = 'below' | 'tooltip' | 'summary';

/**
 * Renderer layout options.
 *
 * `stacked` places the submit button below the fields. `inline` places it in
 * the field row so a short form (e.g. an email field and a Subscribe button)
 * sits on one line and wraps when the row is too narrow.
 *
 * @since 1.5.2
 */
export type FormLayout = 'stacked' | 'inline';

// ---------------------------------------------------------------------------
// Conditional Logic
// ---------------------------------------------------------------------------

/**
 * A single conditional logic rule.
 */
export interface ConditionalLogicRule {
    field: string;
    operator: ConditionalOperator;
    value?: string | number | string[];
}

/**
 * Complete conditional logic configuration for a field or notification.
 */
export interface ConditionalLogic {
    action: ConditionalAction;
    logic: ConditionalLogicType;
    rules: ConditionalLogicRule[];
}

// ---------------------------------------------------------------------------
// Field Configuration (per field type)
// ---------------------------------------------------------------------------

/**
 * Option entry for select, radio, checkbox_group, and select_multiple fields.
 */
export interface FieldOption {
    label: string;
    value: string;
    /** Whether the option starts selected (since 1.6.0). */
    default?: boolean;
    [key: string]: string | boolean | undefined;
}

/**
 * Validation rules that can be set per field.
 */
export interface ValidationRules {
    min?: number;
    max?: number;
    pattern?: string;
    step?: number;
    min_date?: string;
    max_date?: string;
    min_time?: string;
    max_time?: string;
    /** @deprecated since 1.6.0 — file fields store this in `field_config.max_size`. */
    max_size?: number;
    /** @deprecated since 1.6.0 — file fields store this in `field_config.allowed_types`. */
    allowed_types?: string[];
}

/**
 * Base field configuration shared by all field types.
 */
interface BaseFieldConfig {
    [key: string]: unknown;
}

/**
 * Configuration for fields that support options (select, radio, checkbox_group, select_multiple).
 */
export interface OptionsFieldConfig extends BaseFieldConfig {
    options: FieldOption[];
    /** Whether radio and checkbox groups add an "Other" choice with a text input (since 1.6.0). */
    enable_other?: boolean;
    /** The "Other" choice's label. Defaults to "Other" (since 1.6.0). */
    other_label?: string;
    /** A registered choice source the options are loaded from instead of `options` (since 1.6.0). */
    choices_source?: string;
    /** How a radio or checkbox group arranges its choices. Defaults to `stacked` (since 1.6.0). */
    layout?: ChoiceLayout;
    /** Whether a radio or checkbox group shows its choices in a random order (since 1.6.0). */
    randomize?: boolean;
    /** Whether a checkbox group offers a "Select All" checkbox (since 1.6.0). */
    select_all?: boolean;
    /** Whether a select or multi-select is a searchable combobox (since 1.6.0). */
    searchable?: boolean;
}

/**
 * A composite field's own settings for one sub-input, in
 * `field_config.sub_inputs.{key}` (since 1.6.0).
 */
export interface SubInputConfig {
    enabled?: boolean;
    required?: boolean;
    label?: string;
    placeholder?: string;
}

/**
 * Configuration for composite (multi-input) fields (since 1.6.0).
 */
export interface CompositeFieldConfig extends BaseFieldConfig {
    /** Per sub-input overrides, keyed by sub-input key. */
    sub_inputs?: Record<string, SubInputConfig>;
    /** Where the sub-input labels are shown. Defaults to `below`. */
    sub_label_placement?: SubLabelPlacement;
    /** How the field is exported. Defaults to `columns`. */
    export_format?: CompositeExportFormat;
}

/**
 * One of a composite field's sub-inputs, with the field's settings applied
 * (`FormField::getSubInputs()` on the server; since 1.6.0).
 */
export interface SubInputDefinition {
    key: string;
    /** The sub-label, already translated. */
    label: string;
    /** The type's own sub-label, shown as the builder's placeholder. */
    default_label: string;
    placeholder: string | null;
    enabled: boolean;
    /** Whether a required field requires this input (see `required_sub_inputs`). */
    required: boolean;
    autocomplete: string | null;
    type: SubInputType;
}

/**
 * A predefined choice list the builders' bulk editor can insert (since 1.6.0).
 */
export interface PredefinedChoiceList {
    key: string;
    label: string;
    choices: Array<{ value: string; label: string }>;
}

/**
 * Configuration for file upload fields.
 */
export interface FileFieldConfig extends BaseFieldConfig {
    /** Maximum upload size in KB. */
    max_size?: number;
    /** Allowed file extensions, without a leading dot (e.g. `pdf`). */
    allowed_types?: string[];
}

/**
 * Configuration for rating fields.
 *
 * @since 1.6.0
 */
export interface RatingFieldConfig extends BaseFieldConfig {
    /** Number of stars, from 1 to 10. Defaults to 5. */
    max_rating?: number;
    /** Whether half stars can be chosen. */
    allow_half?: boolean;
    /** Star size. Defaults to `md`. */
    size?: 'xs' | 'sm' | 'md' | 'lg';
}

/**
 * Configuration for toggle (switch) fields.
 *
 * @since 1.6.0
 */
export interface ToggleFieldConfig extends BaseFieldConfig {
    /** Switch size. Defaults to `md`. */
    size?: 'xs' | 'sm' | 'md' | 'lg';
    /** daisyUI color. Defaults to `primary`. */
    color?: 'primary' | 'secondary' | 'accent' | 'success' | 'warning' | 'error' | 'info';
    /** Text shown for "on" in submissions, notifications and exports. Defaults to "Yes". */
    checked_label?: string;
    /** Text shown for "off". Defaults to "No". */
    unchecked_label?: string;
}

/**
 * Settings every input type offers (since 1.6.0), stored in `field_config`.
 */
export interface CommonFieldConfig extends BaseFieldConfig {
    /** An `autocomplete` token overriding the type's default (WCAG 1.3.5). */
    autocomplete?: string;
    /** Label shown in submissions, exports and admin notifications instead of the label. */
    admin_label?: string;
    /** Message for every failed rule except the required one. May be a translation key. */
    error_message?: string;
    /** Message shown when a required field is left empty. May be a translation key. */
    required_message?: string;
    /** Whether the field may be populated from the prefill, query string or a filter. */
    dynamic_population?: boolean;
    /** The prefill key and query string parameter; defaults to the field name. */
    dynamic_parameter?: string;
    /** Where the label is shown; unset keeps the form's default (since 1.6.0). */
    label_placement?: LabelPlacement;
    /** Where the description is shown; unset keeps the form's default (since 1.6.0). */
    description_placement?: DescriptionPlacement;
    /** `administrative` fields are left out of the public form and filled in on the submission (since 1.6.0). */
    visibility?: FieldVisibility;
    /** Rejects a value already submitted for the field (text, email, phone, number, url and hidden; since 1.6.0). */
    no_duplicates?: boolean;
    /** Sends the value to notifications and webhooks without saving it (since 1.6.0). */
    do_not_store?: boolean;
    /** Masks the value in the admin unless the user may reveal it (since 1.6.0). */
    mask_in_admin?: boolean;
    /** Days after which `forms:prune-submissions` redacts the value (since 1.6.0). */
    redact_after_days?: number;
}

/**
 * Configuration for paragraph and HTML layout fields. A heading's text is
 * stored in the field `label`.
 */
export interface ContentFieldConfig extends BaseFieldConfig {
    content?: string;
}

/**
 * Discriminated union of all field configurations.
 * Use the field's `type` to determine which config shape applies.
 */
export type FieldConfig =
    | OptionsFieldConfig
    | FileFieldConfig
    | RatingFieldConfig
    | ToggleFieldConfig
    | CommonFieldConfig
    | ContentFieldConfig
    | CompositeFieldConfig
    | BaseFieldConfig
    | null;

/**
 * How a field setting is edited in the builder.
 *
 * @since 1.6.0
 */
export type FieldSettingInput = 'text' | 'integer' | 'number' | 'select' | 'toggle' | 'date' | 'time' | 'list';

/**
 * One type-specific setting from a field type's settings schema
 * (`ArtisanPackUI\Forms\Support\FieldSettings` on the server). The builders
 * render a field's settings from its `settings_schema`.
 *
 * @since 1.6.0
 */
export interface FieldSettingDefinition {
    /** The storage key. */
    key: string;
    /** Builder label, already translated. */
    label: string;
    input: FieldSettingInput;
    /** The column the value is stored in. */
    store: 'field_config' | 'validation_rules';
    /** A column an older copy may still live in; read first, cleared on write. */
    legacy_store?: 'field_config' | 'validation_rules';
    /** The builder tab the setting belongs to. */
    tab: 'general' | 'validation' | 'advanced';
    hint?: string;
    placeholder?: string;
    /** The value the renderer falls back to when the setting is unset. */
    default?: string | number | boolean;
    min?: number;
    max?: number;
    max_length?: number;
    step?: number | 'any';
    options?: Array<{ value: string; label: string }>;
    monospace?: boolean;
}

// ---------------------------------------------------------------------------
// Model Types (API Resource Shapes)
// ---------------------------------------------------------------------------

/**
 * Form field as returned by the API (FormFieldResource).
 */
export interface FormField {
    id: number;
    form_id: number;
    step_id: number | null;
    uuid: string;
    name: string;
    type: FieldType;
    label: string | null;
    placeholder: string | null;
    help_text: string | null;
    is_required: boolean;
    validation_rules: ValidationRules | null;
    field_config: FieldConfig;
    default_value: string | null;
    conditional_logic: ConditionalLogic | null;
    width: FieldWidth;
    css_classes: string | null;
    sort_order: number;
    /** Present only when the field type supports options and options are non-empty. */
    options?: FieldOption[];
    /** Whether the field is shown but not editable (since 1.6.0). */
    is_read_only?: boolean;
    /**
     * The value a read-only field submits, after the `ap.forms.readOnlyValue`
     * filter (since 1.6.0). Present only when `is_read_only` is true.
     */
    read_only_value?: unknown;
    /**
     * The `autocomplete` token the input exposes: the field's setting or the
     * type's default, or null for none (since 1.6.0).
     */
    autocomplete?: string | null;
    /** The custom message for every failed rule except "required", translated (since 1.6.0). */
    error_message?: string;
    /** The custom message for a required field left empty, translated (since 1.6.0). */
    required_message?: string;
    /**
     * The prefill key and query string parameter that populate the field, or
     * null when dynamic population is off (since 1.6.0).
     */
    dynamic_parameter?: string | null;
    /** Whether the field is left out of the public form and filled in on the submission (since 1.6.0). */
    is_administrative?: boolean;
    /**
     * The label of the field's "Other" choice, present only when a radio or
     * checkbox group offers one (since 1.6.0).
     */
    other_choice_label?: string;
    /**
     * The label placement set on the field or its form, or null when neither
     * sets one. Only in the public render payload (since 1.6.0).
     */
    label_placement?: LabelPlacement | null;
    /**
     * The description placement set on the field or its form, or null when
     * neither sets one. Only in the public render payload (since 1.6.0).
     */
    description_placement?: DescriptionPlacement | null;
    /**
     * A composite field's sub-inputs, enabled or not (since 1.6.0). Present
     * only for composite fields.
     */
    sub_inputs?: SubInputDefinition[];
    /** The keys of the sub-inputs a submission must fill in (since 1.6.0). */
    required_sub_inputs?: string[];
    /** Where a composite field shows its sub-input labels (since 1.6.0). */
    sub_label_placement?: SubLabelPlacement;
    /**
     * The value the field starts with: its default value with server merge
     * tags replaced, the options marked as defaults, or the
     * `ap.forms.fieldValue.{parameter}` filter's value; a map of sub-input
     * keys for a composite field. Page tags (`{embed_url}`, `{referer}`,
     * `{query:…}`) are left for the client. Only in the public render payload
     * (since 1.6.0).
     */
    resolved_default_value?: string | boolean | string[] | Record<string, string> | null;
    /**
     * The field type's settings schema (since 1.6.0). Left out of the public
     * render payload.
     */
    settings_schema?: FieldSettingDefinition[];
    created_at: string;
    updated_at: string;
}

/**
 * Form step as returned by the API (FormStepResource).
 */
export interface FormStep {
    id: number;
    form_id: number;
    title: string | null;
    description: string | null;
    sort_order: number;
    next_button_text: string;
    prev_button_text: string;
    created_at: string;
    updated_at: string;
    /** Included when the step is loaded with its fields relationship. */
    fields?: FormField[];
}

/**
 * Form notification as returned by the API (FormNotificationResource).
 */
export interface FormNotification {
    id: number;
    form_id: number;
    type: NotificationType;
    name: string;
    to_email: string | null;
    to_field: string | null;
    cc_emails: string | null;
    bcc_emails: string | null;
    reply_to_email: string | null;
    reply_to_field: string | null;
    from_name: string | null;
    from_email: string | null;
    subject: string;
    message: string;
    conditional_logic: ConditionalLogic | null;
    include_submission_data: boolean;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

/**
 * Form as returned by the API (FormResource).
 */
export interface Form {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    submit_button_text: string;
    success_message: string | null;
    redirect_url: string | null;
    is_multi_step: boolean;
    show_progress_bar: boolean;
    allow_step_navigation: boolean;
    is_active: boolean;
    /** The form's default label placement, or null for top (since 1.6.0). */
    label_placement: LabelPlacement | null;
    /** The form's default description placement, or null for below (since 1.6.0). */
    description_placement: DescriptionPlacement | null;
    created_at: string;
    updated_at: string;
    /** Included when the fields relationship is loaded. */
    fields?: FormField[];
    /** Included when the steps relationship is loaded. */
    steps?: FormStep[];
    /** Included when the notifications relationship is loaded. */
    notifications?: FormNotification[];
    /** Present when submissions count is available. */
    total_submissions_count?: number;
    /** Present when unread submissions count is available. */
    unread_submissions_count?: number;
}

/**
 * File upload as returned by the API (FormUploadResource).
 */
export interface FormUpload {
    id: number;
    submission_id: number;
    field_id: number | null;
    original_name: string;
    mime_type: string | null;
    size: number;
    human_size: string;
    is_image: boolean;
    url: string;
    created_at: string;
    updated_at: string;
}

/**
 * Submission value as returned by the API (FormSubmissionValueResource).
 */
export interface FormSubmissionValue {
    id: number;
    submission_id: number;
    field_id: number | null;
    field_name: string;
    field_label: string | null;
    /** The field's admin label when the value was submitted (since 1.6.0). */
    field_admin_label: string | null;
    field_type: FieldType;
    value: string | null;
    value_array: string[] | null;
    display_value: string;
    /** Option labels for an array value, one per item (since 1.6.0). */
    display_values: string[];
    /**
     * Whether the value is masked because its field is masked in the admin
     * and the user may not reveal it. `value` and `display_value` then hold
     * the masked text (since 1.6.0).
     */
    is_masked: boolean;
    /** Whether `forms:prune-submissions` redacted the value (since 1.6.0). */
    is_redacted: boolean;
    upload_id: number | null;
    created_at: string;
}

/**
 * Form submission as returned by the API (FormSubmissionResource).
 */
export interface FormSubmission {
    id: number;
    form_id: number;
    submission_number: string;
    page_url: string | null;
    referrer_url: string | null;
    /** Only present when privacy.include_ip_address is enabled. */
    ip_address?: string | null;
    /** Only present when privacy.include_user_agent is enabled. */
    user_agent?: string | null;
    is_read: boolean;
    is_spam: boolean;
    is_starred: boolean;
    admin_notes: string | null;
    created_at: string;
    updated_at: string;
    /** Included when the values relationship is loaded. */
    values?: FormSubmissionValue[];
    /** Included when the uploads relationship is loaded. */
    uploads?: FormUpload[];
    /**
     * The form's administrative fields, which admins fill in on the
     * submission. Included by the single-submission endpoints (since 1.6.0).
     */
    administrative_fields?: FormField[];
}

// ---------------------------------------------------------------------------
// Form Render Data
// ---------------------------------------------------------------------------

/**
 * Honeypot spam protection configuration.
 */
export interface HoneypotConfig {
    enabled: boolean;
    field_name: string;
}

/**
 * Display configuration for form rendering.
 */
export interface DisplayConfig {
    label_position: LabelPosition;
    show_required_indicator: boolean;
    required_indicator: string;
    error_display: ErrorDisplay;
}

/**
 * Complete render payload for client-side form rendering (FormRenderResource).
 */
export interface FormRenderData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    submit_button_text: string;
    success_message: string | null;
    redirect_url: string | null;
    is_multi_step: boolean;
    show_progress_bar: boolean;
    allow_step_navigation: boolean;
    fields: FormField[];
    steps: FormStep[];
    config: {
        honeypot: HoneypotConfig;
        display: DisplayConfig;
    };
}

// ---------------------------------------------------------------------------
// API Request / Response Types
// ---------------------------------------------------------------------------

/**
 * Request body for submitting a form via the API.
 */
export interface SubmitFormRequest {
    data: Record<string, unknown>;
    files?: Record<string, File | File[]>;
}

/**
 * Successful response from the form submission endpoint.
 */
export interface SubmitFormResponse {
    message: string;
    submission_id: number | null;
    redirect_url?: string;
}

/**
 * Request body for creating a form via the API.
 */
export interface StoreFormRequest {
    name: string;
    slug?: string;
    description?: string | null;
    submit_button_text?: string;
    success_message?: string | null;
    redirect_url?: string | null;
    is_active?: boolean;
    is_multi_step?: boolean;
    show_progress_bar?: boolean;
    allow_step_navigation?: boolean;
    /** The default label placement; null clears it (since 1.6.0). */
    label_placement?: LabelPlacement | null;
    /** The default description placement; null clears it (since 1.6.0). */
    description_placement?: DescriptionPlacement | null;
}

/**
 * Request body for updating a form via the API.
 */
export interface UpdateFormRequest extends Partial<StoreFormRequest> {}

/**
 * Request body for creating a form field via the API.
 */
export interface StoreFieldRequest {
    name: string;
    type: FieldType;
    step_id?: number | null;
    label?: string | null;
    placeholder?: string | null;
    help_text?: string | null;
    is_required?: boolean;
    validation_rules?: ValidationRules | null;
    field_config?: FieldConfig;
    default_value?: string | null;
    conditional_logic?: ConditionalLogic | null;
    width?: FieldWidth;
    css_classes?: string | null;
    sort_order?: number;
}

/**
 * Request body for updating a form field via the API.
 */
export interface UpdateFieldRequest extends Partial<StoreFieldRequest> {}

/**
 * Request body for creating a form step via the API.
 */
export interface StoreStepRequest {
    title?: string | null;
    description?: string | null;
    sort_order?: number;
    next_button_text?: string;
    prev_button_text?: string;
}

/**
 * Request body for updating a form step via the API.
 */
export interface UpdateStepRequest extends Partial<StoreStepRequest> {}

/**
 * Request body for creating a notification via the API.
 */
export interface StoreNotificationRequest {
    type: NotificationType;
    name: string;
    to_email?: string | null;
    to_field?: string | null;
    cc_emails?: string | null;
    bcc_emails?: string | null;
    reply_to_email?: string | null;
    reply_to_field?: string | null;
    from_name?: string | null;
    from_email?: string | null;
    subject: string;
    message: string;
    conditional_logic?: ConditionalLogic | null;
    include_submission_data?: boolean;
    is_active?: boolean;
    sort_order?: number;
}

/**
 * Request body for updating a notification via the API.
 */
export interface UpdateNotificationRequest extends Partial<StoreNotificationRequest> {}

/**
 * Request body for updating submission metadata via the API.
 */
export interface UpdateSubmissionRequest {
    is_read?: boolean;
    is_spam?: boolean;
    is_starred?: boolean;
    admin_notes?: string | null;
    /**
     * Values for the form's administrative fields, keyed by field name and
     * validated with each field's rules (since 1.6.0).
     */
    admin_values?: Record<string, unknown>;
}

/**
 * Request body for reordering fields via the API.
 */
export interface ReorderFieldsRequest {
    fields: Array<{ id: number; sort_order: number; step_id?: number | null }>;
}

/**
 * Request body for reordering steps via the API.
 */
export interface ReorderStepsRequest {
    steps: Array<{ id: number; sort_order: number }>;
}

/**
 * Request body for bulk submission operations via the API.
 */
export interface BulkSubmissionRequest {
    ids: number[];
    action: 'mark_read' | 'mark_unread' | 'mark_spam' | 'mark_not_spam' | 'delete';
}

/**
 * Response from bulk submission operations.
 */
export interface BulkSubmissionResponse {
    message: string;
    affected: number;
}

// ---------------------------------------------------------------------------
// Paginated Response
// ---------------------------------------------------------------------------

/**
 * Laravel paginated API response wrapper.
 */
export interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}

// ---------------------------------------------------------------------------
// API Error Response
// ---------------------------------------------------------------------------

/**
 * Standard Laravel validation error response.
 */
export interface ValidationErrorResponse {
    message: string;
    errors: Record<string, string[]>;
}

/**
 * Generic API error response.
 */
export interface ErrorResponse {
    message: string;
}

// ---------------------------------------------------------------------------
// Field Type Metadata
// ---------------------------------------------------------------------------

/**
 * Metadata for a single field type definition.
 */
export interface FieldTypeMetadata {
    icon: string;
    category: FieldTypeCategory;
    has_options: boolean;
    supports_placeholder: boolean;
    supports_default_value: boolean;
    validation_options: string[];
    is_layout?: boolean;
}

/**
 * Map of all field types to their metadata.
 */
export type FieldTypeMap = Record<FieldType, FieldTypeMetadata>;

/**
 * Conditional logic operator metadata.
 */
export interface OperatorMetadata {
    label: string;
    needs_value: boolean;
    value_type: 'text' | 'number' | 'list' | 'none';
    supports_types: FieldType[];
}

// ---------------------------------------------------------------------------
// Admin Component Types
// ---------------------------------------------------------------------------

/**
 * Field palette entry for the form builder sidebar.
 */
export interface FieldPaletteItem {
    type: FieldType;
    label: string;
    icon: string;
    category: FieldTypeCategory;
    /**
     * Optional raw SVG path data (16x16 viewBox) rendered in place of the
     * built-in icon lookup. Host-registered palette items use this to supply
     * an icon for a custom field type that is not in the built-in icon set.
     */
    iconPath?: string;
}

/**
 * Field palette grouped by category.
 */
export interface FieldPaletteGroup {
    label: string;
    fields: FieldPaletteItem[];
}

/**
 * Status filter for submissions list.
 */
export type SubmissionStatusFilter = 'all' | 'unread' | 'read' | 'spam' | 'starred';

/**
 * Date range filter for submissions list.
 */
export type SubmissionDateRange = 'all' | 'today' | 'week' | 'month' | 'year';

/**
 * Sort direction.
 */
export type SortDirection = 'asc' | 'desc';

/**
 * Form list status filter.
 */
export type FormStatusFilter = 'all' | 'active' | 'inactive';

/**
 * Submission status counts for the submissions list filter bar.
 */
export interface SubmissionStatusCounts {
    all: number;
    unread: number;
    read: number;
    spam: number;
    starred: number;
}

/**
 * Notification type metadata for the notification editor.
 */
export interface NotificationTypeInfo {
    type: NotificationType;
    label: string;
    description: string;
}

/**
 * Conditional action for notifications (send/skip instead of show/hide).
 */
export type NotificationConditionalAction = 'send' | 'skip';

/**
 * Conditional logic for notifications.
 */
export interface NotificationConditionalLogic {
    action: NotificationConditionalAction;
    logic: ConditionalLogicType;
    rules: ConditionalLogicRule[];
}
