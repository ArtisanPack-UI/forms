Feature Name: Testing Strategy Implementation
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::12-Polish"

## What is the Feature

Implement comprehensive test coverage for the forms package using Pest, including unit tests, feature tests, Livewire component tests, and model factories.

## Tasks

### Test Infrastructure

- [ ] Create `TestCase` base class
  - RefreshDatabase trait
  - Load package service providers
  - Configure testing database (SQLite in-memory)
  - Load migrations
  - Helper methods for common operations

- [ ] Create `Pest.php` configuration
  - Use test case
  - Configure parallel testing if needed

### Model Factories

- [ ] `FormFactory` with states:
  - `inactive()` - Creates inactive form
  - `multiStep()` - Creates form with 3 steps
  - `withFields(count)` - Creates form with fields
  - `withNotifications()` - Creates form with admin and autoresponder

- [ ] `FormFieldFactory` with states:
  - `text()`, `email()` - Specific field types
  - `required()` - Required field
  - `withConditions(targetUuid, operator, value)` - Conditional field

- [ ] `FormStepFactory`
  - Basic step creation

- [ ] `FormSubmissionFactory` with states:
  - `read()`, `spam()`, `starred()` - Status states
  - `withValues(array)` - Include submission values

- [ ] `FormSubmissionValueFactory`
  - Value creation with field reference

- [ ] `FormNotificationFactory` with states:
  - `admin()`, `autoresponder()`, `custom()` - Types

- [ ] `FormUploadFactory`
  - File metadata creation

### Unit Tests

- [ ] `tests/Unit/Models/FormTest.php`
  - Slug generation from name
  - Relationships (fields, steps, submissions, notifications)
  - Form duplication with all related data
  - Unread submissions count

- [ ] `tests/Unit/Models/FormFieldTest.php`
  - Validation rule building
  - Type-specific rules
  - File validation rules
  - Conditional logic accessor

- [ ] `tests/Unit/Models/FormSubmissionTest.php`
  - Submission number generation
  - Status toggle methods
  - Data accessors
  - Email value extraction

- [ ] `tests/Unit/Models/FormNotificationTest.php`
  - Recipient resolution (static and field-based)
  - Placeholder parsing
  - Conditional logic evaluation

- [ ] `tests/Unit/Services/ValidationServiceTest.php`
  - Required vs nullable rules
  - Email, URL, number validation
  - File validation (mimes, size)
  - Pattern validation

- [ ] `tests/Unit/Services/ExportServiceTest.php`
  - CSV header generation
  - Data row formatting
  - Filter hook integration

### Feature Tests

- [ ] `tests/Feature/FormBuilderTest.php`
  - Create form
  - Add fields
  - Reorder fields
  - Duplicate fields
  - Delete fields
  - Enable/disable multi-step
  - Step management

- [ ] `tests/Feature/FormRendererTest.php`
  - Render all field types
  - Required field validation
  - Type-specific validation
  - Successful submission
  - Success message display
  - Redirect after submission

- [ ] `tests/Feature/SubmissionTest.php`
  - Submission creates correct records
  - Submission values stored correctly
  - File uploads handled
  - Metadata captured (IP, URL, etc.)

- [ ] `tests/Feature/ConditionalLogicTest.php`
  - Field shows when condition met
  - Field hides when condition not met
  - Hidden fields not validated
  - Multiple conditions with AND/OR
  - All operators work correctly

- [ ] `tests/Feature/MultiStepFormTest.php`
  - Step navigation
  - Per-step validation
  - Progress indicator accuracy
  - Final submission

- [ ] `tests/Feature/NotificationTest.php`
  - Admin notification sent
  - Autoresponder sent to email field
  - Conditional notification logic
  - Placeholder replacement
  - Queue integration

- [ ] `tests/Feature/ExportTest.php`
  - CSV export generates correctly
  - Filter hooks applied
  - Proper encoding

- [ ] `tests/Feature/IntegrationHooksTest.php`
  - Actions fire at correct times
  - Filters modify data correctly
  - Custom field types can be registered

### Spam Protection Tests

- [ ] Honeypot detection
- [ ] Rate limiting
- [ ] Timestamp validation

### Authorization Tests (if applicable)

- [ ] Form policies
- [ ] Submission policies

## Accessibility Notes

N/A - Testing infrastructure

## UX Notes

N/A - Testing infrastructure

## Testing Notes

- All tests should pass
- Aim for high code coverage
- Test edge cases and error conditions
- Use factories consistently
- Mock external services (mail, storage)

## Documentation Notes

- Document testing setup for contributors
- Document factory states and usage
- Document how to run tests

## Related Documents

- [11-testing-strategy.md](../11-testing-strategy.md)
- All other planning documents
