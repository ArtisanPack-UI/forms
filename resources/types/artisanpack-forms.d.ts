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
 * All available form field types.
 */
export type FieldType =
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
    | 'checkbox_group'
    | 'select_multiple'
    | 'file'
    | 'date'
    | 'time'
    | 'heading'
    | 'paragraph'
    | 'divider'
    | 'html';

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
 * Error display mode options.
 */
export type ErrorDisplay = 'below' | 'tooltip' | 'summary';

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
    max_size?: number;
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
}

/**
 * Configuration for file upload fields.
 */
export interface FileFieldConfig extends BaseFieldConfig {
    max_size?: number;
    allowed_mimes?: string[];
}

/**
 * Discriminated union of all field configurations.
 * Use the field's `type` to determine which config shape applies.
 */
export type FieldConfig =
    | OptionsFieldConfig
    | FileFieldConfig
    | BaseFieldConfig
    | null;

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
    field_type: FieldType;
    value: string | null;
    value_array: string[] | null;
    display_value: string;
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
