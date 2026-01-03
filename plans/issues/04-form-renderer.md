Feature Name: Form Renderer Component
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::5-FormRenderer"

## What is the Feature

Implement the public-facing form renderer Livewire component that displays forms, handles validation, processes submissions, and provides user feedback. Includes spam protection, file uploads, and multi-step navigation.

## Tasks

### FormRenderer Livewire Component

- [ ] Create `FormRenderer` Livewire component
  - Properties: form, formData, files, honeypot, isSubmitted, currentStepIndex, formLoadedAt
  - Use WithFileUploads trait
  - Computed properties: currentFields, currentStep, totalSteps, isFirstStep, isLastStep, progressPercentage
  - Methods: mount(), submit(), nextStep(), previousStep(), goToStep()

### Field Rendering

- [ ] Create Blade partials for each field type in `resources/views/components/fields/`
  - text.blade.php
  - email.blade.php
  - phone.blade.php
  - textarea.blade.php
  - number.blade.php
  - url.blade.php
  - select.blade.php
  - radio.blade.php (using x-artisanpack-radio-group)
  - checkbox.blade.php
  - checkbox_group.blade.php (using x-artisanpack-checkbox-group)
  - date.blade.php
  - time.blade.php
  - file.blade.php
  - hidden.blade.php
  - heading.blade.php
  - paragraph.blade.php
  - divider.blade.php
  - html.blade.php

### Validation

- [ ] Implement `buildAllValidationRules()` method
- [ ] Build rules from field configuration
- [ ] Type-specific validation (email, url, number, date, file)
- [ ] Respect conditional logic (skip hidden fields)
- [ ] Custom validation messages support
- [ ] Per-step validation for multi-step forms

### Submission Handling

- [ ] Create `SubmissionService` class
- [ ] Generate submission number
- [ ] Store submission metadata (IP, URL, user agent)
- [ ] Save field values to submission_values table
- [ ] Handle file uploads
- [ ] Trigger notifications
- [ ] Fire hooks and events

### Spam Protection

- [ ] Implement honeypot field (hidden from users via CSS)
- [ ] Silent success for bot submissions (no actual save)
- [ ] Rate limiting per IP
- [ ] Timestamp validation (reject too-fast submissions)

### File Upload Handling

- [ ] Validate file types and sizes
- [ ] Store files securely
- [ ] Create FormUpload records
- [ ] Preview uploaded files before submission

### Success States

- [ ] Show success message after submission
- [ ] Optional redirect to URL
- [ ] Reset form option
- [ ] Fire JavaScript event for integrations

### Error Handling

- [ ] Display validation errors inline with fields
- [ ] Show error summary at top of form
- [ ] Handle submission failures gracefully

## Accessibility Notes

- All form fields must have associated labels
- Required fields marked with aria-required
- Error messages linked with aria-describedby
- Focus moves to first error on validation failure
- Success message announced to screen readers
- Progress indicators for multi-step have proper ARIA

## UX Notes

- Loading states during submission
- Disable submit button while processing
- Smooth transitions for multi-step navigation
- Clear progress indication
- Mobile-responsive layout
- Field width classes support (full, half, third)

## Testing Notes

- Test form rendering with all field types
- Test required field validation
- Test type-specific validation (email, url, etc.)
- Test file upload validation and storage
- Test honeypot protection
- Test rate limiting
- Test multi-step navigation and per-step validation
- Test submission creates correct records
- Test success message and redirect
- Test conditional field visibility

## Documentation Notes

- Document how to embed forms in pages
- Document customization options
- Document available CSS classes
- Document JavaScript events fired

## Related Documents

- [04-form-renderer.md](../04-form-renderer.md)
- [05-field-types.md](../05-field-types.md)
- [06-conditional-logic.md](../06-conditional-logic.md)
