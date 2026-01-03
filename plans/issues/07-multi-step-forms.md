Feature Name: Multi-Step Forms
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::7-MultiStep"

## What is the Feature

Implement wizard-style multi-step forms with step navigation, progress indicators, per-step validation, and customizable step settings.

## Tasks

### Form Builder - Step Management

- [ ] Implement `enableMultiStep()` method
  - Create initial step
  - Move existing fields to first step
- [ ] Implement `disableMultiStep()` method
  - Remove step associations from fields
  - Delete all steps
- [ ] Implement step tab UI in form builder
  - Display all steps with field counts
  - Drag-to-reorder steps
  - Active step indicator
  - Add step button
- [ ] Implement step CRUD operations
  - `addStep()` - Create new step
  - `selectStep()` - Switch active step
  - `updateStep()` - Save step settings
  - `deleteStep()` - Remove step (move fields to previous)
  - `reorderSteps()` - Change step order

### Step Editor

- [ ] Create step settings sidebar panel
  - Step title
  - Step description
  - Next button text
  - Previous button text
  - Delete step button (with confirmation)

### Form Renderer - Step Navigation

- [ ] State management
  - `currentStepIndex` property
  - `steps()` computed property
  - `currentStep()` computed property
  - `totalSteps()` computed property
  - `isFirstStep()` / `isLastStep()` computed properties

- [ ] Navigation methods
  - `nextStep()` - Validate current step, advance if valid
  - `previousStep()` - Go back without validation
  - `goToStep(index)` - Direct navigation (if allowed)
  - `validateStep()` - Validate only current step's fields

### Progress Indicators

- [ ] Implement numbered steps progress indicator
  - Show step numbers with completed checkmarks
  - Connector lines between steps
  - Active step highlighting
  - Clickable steps (if allow_step_navigation enabled)

- [ ] Implement progress bar variant
  - Percentage complete
  - Step count display
  - Current step title

- [ ] Implement breadcrumb style variant
  - Horizontal step list
  - Links to completed steps

### Navigation Buttons

- [ ] Previous button (hidden on first step)
- [ ] Next button (shows on all but last step)
- [ ] Submit button (shows only on last step)
- [ ] Custom button text per step
- [ ] Loading states during validation

### Validation

- [ ] Per-step validation before advancing
- [ ] Full form validation on final submit
- [ ] Error display for current step only
- [ ] Conditional field validation respects steps

### Keyboard Navigation

- [ ] Enter to advance (except in textarea)
- [ ] Escape to go back
- [ ] Tab navigation within step

### Edge Cases

- [ ] Handle empty steps (no fields)
- [ ] Handle step with only layout fields
- [ ] Handle conditional fields across steps
- [ ] Persist form data across navigation

## Accessibility Notes

- Progress indicator must be accessible to screen readers
- Announce step changes with aria-live
- Steps should be navigable via keyboard
- Current step clearly indicated
- Focus management when changing steps

## UX Notes

- Smooth transitions between steps
- Clear progress indication
- Mobile-responsive step indicators
- Option to show all steps vs current only
- Ability to review previous steps

## Testing Notes

- Test enabling/disabling multi-step mode
- Test step add/edit/delete/reorder
- Test navigation forward and backward
- Test per-step validation
- Test final submission validation
- Test progress indicator accuracy
- Test keyboard navigation
- Test with conditional fields

## Documentation Notes

- Document how to enable multi-step mode
- Document step configuration options
- Document progress indicator variants
- Document validation behavior

## Related Documents

- [07-multi-step-forms.md](../07-multi-step-forms.md)
- [03-form-builder.md](../03-form-builder.md)
- [04-form-renderer.md](../04-form-renderer.md)
