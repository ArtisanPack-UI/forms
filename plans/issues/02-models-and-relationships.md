Feature Name: Eloquent Models and Relationships
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::1-Foundation"

## What is the Feature

Implement all Eloquent models for the forms package with proper relationships, casts, scopes, accessors, and business logic methods.

## Tasks

### Models to Create

- [ ] Create `Form` model
  - Fillable attributes
  - Casts: settings (array), booleans for is_multi_step, show_progress_bar, etc.
  - Relationships: hasMany fields, steps, submissions, notifications
  - Scopes: active(), multiStep(), singleStep()
  - Accessors: unread_submissions_count, total_submissions_count, fields_ordered
  - Methods: getSetting(), duplicate(), getRouteKeyName()
  - Boot: auto-generate slug from name

- [ ] Create `FormStep` model
  - Fillable attributes
  - Relationships: belongsTo form, hasMany fields
  - Accessors: step_number, is_first_step, is_last_step
  - Methods: getPreviousStep(), getNextStep()

- [ ] Create `FormField` model
  - Fillable attributes
  - Casts: is_required (bool), validation_rules/field_config/conditional_logic (array)
  - Relationships: belongsTo form and step, hasMany submissionValues
  - Scopes: required(), ofType(), withoutStep(), inStep()
  - Accessors: has_conditional_logic, is_file_field, options, width_class
  - Methods: getConfig(), getValidationRule(), buildValidationRules()
  - Boot: auto-generate UUID

- [ ] Create `FormSubmission` model
  - Fillable attributes
  - Casts: is_read, is_spam, is_starred (bool)
  - Relationships: belongsTo form, hasMany values and uploads
  - Scopes: unread(), read(), notSpam(), spam(), starred(), recent()
  - Accessors: data, data_array
  - Methods: markAsRead(), markAsUnread(), toggleSpam(), toggleStar(), getValue(), getEmailValue()
  - Boot: auto-generate submission_number

- [ ] Create `FormSubmissionValue` model
  - Fillable attributes
  - Casts: value_array (array), created_at (datetime)
  - Relationships: belongsTo submission, field, upload
  - Accessors: display_value, is_array_value, is_file
  - Note: $timestamps = false

- [ ] Create `FormNotification` model
  - Fillable attributes
  - Casts: conditional_logic (array), booleans
  - Constants: TYPE_ADMIN, TYPE_AUTORESPONDER, TYPE_CUSTOM
  - Relationships: belongsTo form
  - Scopes: active(), ofType(), admin(), autoresponder()
  - Accessors: has_conditional_logic, is_autoresponder
  - Methods: getRecipientEmails(), getReplyToEmail(), parseMessage(), parseSubject()

- [ ] Create `FormUpload` model
  - Fillable attributes
  - Relationships: belongsTo submission and field
  - Accessors: url, full_path, human_size, extension, is_image
  - Methods: download(), delete() (also deletes file from storage)

### Model Factories

- [ ] Create `FormFactory` with states: inactive(), multiStep(), withFields(), withNotifications()
- [ ] Create `FormFieldFactory` with states: text(), email(), required(), withConditions()
- [ ] Create `FormStepFactory`
- [ ] Create `FormSubmissionFactory` with states: read(), spam(), starred(), withValues()
- [ ] Create `FormSubmissionValueFactory`
- [ ] Create `FormNotificationFactory` with states: admin(), autoresponder(), custom()
- [ ] Create `FormUploadFactory`

## Accessibility Notes

N/A - Model layer

## UX Notes

N/A - Model layer

## Testing Notes

- Test all model relationships
- Test scopes return correct records
- Test accessors return expected values
- Test business logic methods (duplicate, toggle methods, etc.)
- Test auto-generation of slugs, UUIDs, submission numbers
- Test factories create valid models

## Documentation Notes

- Document available scopes for each model
- Document accessor/mutator behavior
- Document factory states and usage

## Related Documents

- [02-models-and-relationships.md](../02-models-and-relationships.md)
- [01-database-schema.md](../01-database-schema.md)
