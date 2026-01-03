Feature Name: Email Notification System
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::9-Notifications"

## What is the Feature

Implement a configurable email notification system with support for multiple notifications per form, including admin alerts, auto-responders, and custom notifications with placeholder support and conditional sending.

## Tasks

### Notification Types

- [ ] Implement three notification types:
  - `admin` - Notifies site administrators
  - `autoresponder` - Automatic reply to form submitter
  - `custom` - Flexible notification with any recipient

### NotificationEditor Livewire Component

- [ ] Create `NotificationEditor` component
- [ ] Notification list sidebar
  - Display all notifications with type badges
  - Active/inactive toggle
  - Click to select for editing
- [ ] Add notification dropdown (admin/autoresponder/custom)
- [ ] Duplicate and delete actions
- [ ] Reorder notifications

### Notification Settings Form

- [ ] Basic settings
  - Notification name (internal)
  - Type selection
  - Active toggle

- [ ] Recipients section
  - To email(s) - comma-separated
  - To field - select email field from form
  - CC emails
  - BCC emails
  - Reply-To email or field

- [ ] Sender section
  - From name
  - From email

- [ ] Content section
  - Subject line (with placeholders)
  - Message body (with placeholders)
  - Include submission data toggle

- [ ] Conditional sending
  - Enable/disable conditions
  - Same condition builder UI as field conditions
  - Only send when conditions are met

### Placeholder System

- [ ] Implement placeholder replacement in subject and message
- [ ] Available placeholders:
  - `{form_name}` - Form name
  - `{submission_number}` - Unique submission ID
  - `{submission_date}` - Date of submission
  - `{submission_time}` - Time of submission
  - `{all_fields}` - All field values as list
  - `{field_name}` - Value of specific field
  - `{page_url}` - URL where form was submitted
  - `{ip_address}` - Submitter's IP address
- [ ] Show available placeholders in UI

### NotificationService

- [ ] Create `NotificationService` class
- [ ] `sendNotifications(FormSubmission $submission)` method
- [ ] Fetch active notifications for form
- [ ] Evaluate conditions before sending
- [ ] Queue notification jobs

### FormSubmissionNotification Mailable

- [ ] Create `FormSubmissionNotification` mailable
- [ ] Dynamic envelope (from, reply-to, subject)
- [ ] Support multiple recipients
- [ ] Include parsed message content
- [ ] Include submission data table (if enabled)

### Email Template

- [ ] Create base email template
- [ ] Header with form name and submission info
- [ ] Parsed message content
- [ ] Submission data table
- [ ] Footer with metadata (page URL, etc.)
- [ ] Responsive design

### Queue Integration

- [ ] Create `SendFormNotification` job
- [ ] Queue notifications for async sending
- [ ] Handle failures gracefully
- [ ] Configurable queue name

### Defaults for Types

- [ ] Admin notification defaults:
  - To: config mail from address
  - Subject: "New Submission: {form_name}"
  - Message: "A new form submission has been received.\n\n{all_fields}"

- [ ] Autoresponder defaults:
  - To: First email field in form
  - Subject: "Thank you for contacting us"
  - Message: "Thank you for your submission..."

## Accessibility Notes

- Email content should be accessible
- Proper heading structure in emails
- Alt text for any images
- High contrast colors

## UX Notes

- Default templates for each type
- Real-time placeholder preview
- Clear indication of active/inactive notifications
- Confirmation before deleting
- Test send functionality (future enhancement)

## Testing Notes

- Test notification creation and saving
- Test placeholder replacement
- Test conditional sending logic
- Test recipient resolution (static and field-based)
- Test email queuing
- Test multiple notifications per form
- Test autoresponder finds email field

## Documentation Notes

- Document notification types and use cases
- Document all available placeholders
- Document conditional sending setup
- Document email template customization

## Related Documents

- [08-notifications.md](../08-notifications.md)
- [02-models-and-relationships.md](../02-models-and-relationships.md)
- [04-form-renderer.md](../04-form-renderer.md)
