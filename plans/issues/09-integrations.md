Feature Name: Hook-Based Integration System
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::11-Integrations"

## What is the Feature

Implement an extensibility system using `artisanpack-ui/hooks` that allows third-party packages to integrate with the forms package through actions and filters, plus standard Laravel events for native event listeners.

## Tasks

### Action Hooks (Events)

- [ ] Implement `forms.submission.created` action
  - Fired after a submission is saved
  - Parameters: `FormSubmission $submission`

- [ ] Implement `forms.submission.updated` action
  - Fired after a submission is updated
  - Parameters: `FormSubmission $submission`

- [ ] Implement `forms.submission.deleted` action
  - Fired after a submission is deleted
  - Parameters: `FormSubmission $submission`

- [ ] Implement `forms.form.created` action
  - Fired after a form is created
  - Parameters: `Form $form`

- [ ] Implement `forms.form.updated` action
  - Fired after a form is updated
  - Parameters: `Form $form`

- [ ] Implement `forms.form.deleted` action
  - Fired after a form is deleted
  - Parameters: `Form $form`

- [ ] Implement `forms.notification.before_send` action
  - Fired before email is sent
  - Parameters: `FormNotification $notification, FormSubmission $submission`

- [ ] Implement `forms.notification.sent` action
  - Fired after email is sent
  - Parameters: `FormNotification $notification, FormSubmission $submission`

### Filter Hooks (Modifiers)

- [ ] Implement `forms.field_types` filter
  - Purpose: Register custom field types
  - Parameters: `array $fieldTypes`
  - Returns: `array`

- [ ] Implement `forms.validation_rules` filter
  - Purpose: Modify validation rules for a field
  - Parameters: `array $rules, FormField $field`
  - Returns: `array`

- [ ] Implement `forms.submission_data` filter
  - Purpose: Modify data before saving
  - Parameters: `array $data, Form $form`
  - Returns: `array`

- [ ] Implement `forms.notification_recipients` filter
  - Purpose: Modify email recipients
  - Parameters: `array $recipients, FormNotification $notification`
  - Returns: `array`

- [ ] Implement `forms.notification_message` filter
  - Purpose: Modify email message
  - Parameters: `string $message, FormNotification $notification, FormSubmission $submission`
  - Returns: `string`

- [ ] Implement `forms.export_data` filter
  - Purpose: Modify export row data
  - Parameters: `array $data, FormSubmission $submission`
  - Returns: `array`

- [ ] Implement `forms.export_headers` filter
  - Purpose: Modify CSV headers
  - Parameters: `array $headers, Form $form`
  - Returns: `array`

- [ ] Implement `forms.settings_tabs` filter
  - Purpose: Add custom settings tabs for integrations
  - Parameters: `array $tabs`
  - Returns: `array`

### Laravel Events

- [ ] Create `FormSubmitted` event class
  - Property: `public FormSubmission $submission`
  - Implements `Dispatchable`, `SerializesModels`

- [ ] Create `FormCreated` event class
  - Property: `public Form $form`

- [ ] Create `FormUpdated` event class
  - Property: `public Form $form`

- [ ] Dispatch events at appropriate points in code

### Webhook Support

- [ ] Add webhook configuration to config/forms.php
- [ ] Create `SendWebhook` job
- [ ] Listen for `forms.submission.created` action
- [ ] Send webhook payload with submission data
- [ ] Support webhook secret for verification

### Integration Settings

- [ ] Store integration settings in form's `settings` JSON
- [ ] Structure: `settings.integrations.{provider}.{config}`
- [ ] Allow integration packages to register settings panels

### Documentation

- [ ] Document all available action hooks
- [ ] Document all available filter hooks
- [ ] Document Laravel events
- [ ] Provide integration package example

## Accessibility Notes

N/A - Backend integration layer

## UX Notes

- Integration settings should integrate smoothly into form builder
- Clear indication when integrations are active
- Error handling for failed integrations

## Testing Notes

- Test all action hooks fire at correct times
- Test filter hooks can modify data
- Test Laravel events are dispatched
- Test custom field type registration via hook
- Test webhook sending
- Test integration settings storage

## Documentation Notes

- Document hook system with examples
- Provide example integration package (ConvertKit example)
- Document webhook payload format
- Document how to create integration packages

## Related Documents

- [09-integrations.md](../09-integrations.md)
- [05-field-types.md](../05-field-types.md)
- [08-notifications.md](../08-notifications.md)
