Feature Name: Database Schema Implementation
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::1-Foundation"

## What is the Feature

Implement all database migrations for the forms package, including 7 tables that store form definitions, fields, steps, submissions, submission values, notifications, and file uploads.

## Tasks

### Tables to Create

- [ ] Create `forms` table migration
  - Basic info: name, slug, description
  - Display settings: submit_button_text, success_message, redirect_url
  - Multi-step config: is_multi_step, show_progress_bar, allow_step_navigation
  - Status: is_active
  - Settings JSON column
- [ ] Create `form_steps` table migration
  - Form relationship
  - Step info: title, description
  - Navigation: next_button_text, prev_button_text
  - Ordering: sort_order
- [ ] Create `form_fields` table migration
  - Form and step relationships
  - Field identity: uuid, name
  - Type: type column
  - Labels: label, placeholder, help_text
  - Validation: is_required, validation_rules JSON
  - Configuration: field_config JSON, default_value
  - Conditional logic: conditional_logic JSON
  - Layout: width, css_classes, sort_order
- [ ] Create `form_submissions` table migration
  - Form relationship
  - Metadata: submission_number, page_url, referrer_url, ip_address, user_agent
  - Status: is_read, is_spam, is_starred
  - Admin notes
- [ ] Create `form_uploads` table migration
  - Submission and field relationships
  - File info: original_name, stored_name, disk, path, mime_type, size
- [ ] Create `form_submission_values` table migration
  - Submission and field relationships
  - Field reference: field_name, field_label, field_type
  - Value storage: value, value_array JSON, upload_id
- [ ] Create `form_notifications` table migration
  - Form relationship
  - Type: type column (admin, autoresponder, custom)
  - Recipients: to_email, to_field, cc_emails, bcc_emails, reply_to
  - Content: from_name, from_email, subject, message
  - Options: conditional_logic JSON, include_submission_data, is_active

### Configuration

- [ ] Add `form-uploads` disk configuration to filesystems.php publish
- [ ] Create indexes for common query patterns

### Quality Checks

- [ ] All migrations run without errors
- [ ] Foreign key constraints are properly set
- [ ] Cascade deletes work correctly
- [ ] Rollback migrations work correctly

## Accessibility Notes

N/A - Database layer

## UX Notes

N/A - Database layer

## Testing Notes

- Test that all migrations run successfully on fresh database
- Test rollback for each migration
- Verify foreign key constraints prevent orphan records
- Test cascade delete behavior

## Documentation Notes

- Document the entity relationship diagram
- Document JSON column structures with examples
- Document index strategy and query patterns

## Related Documents

- [01-database-schema.md](../01-database-schema.md)
- [02-models-and-relationships.md](../02-models-and-relationships.md)
