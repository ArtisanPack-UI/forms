Feature Name: Form Builder Admin Interface
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::4-FormBuilder"

## What is the Feature

Implement the admin form builder interface using Livewire, featuring a drag-and-drop field palette, canvas area, and field editor sidebar. Uses `@artisanpack-ui/livewire-drag-and-drop` package for accessible drag-and-drop functionality.

## Tasks

### FormBuilder Livewire Component

- [ ] Create `FormBuilder` Livewire component
  - Properties: form, formData, fields, activeStepId, selectedField, fieldData
  - Computed properties: availableFieldTypes, sortedFieldTypes, currentStepFields
  - Methods for form operations: save(), enableMultiStep(), disableMultiStep()
  - Methods for field operations: addField(), selectField(), updateField(), deleteField(), duplicateField(), reorderFields()
  - Methods for step operations (multi-step): addStep(), selectStep(), updateStep(), deleteStep(), reorderSteps()

### Layout Structure

- [ ] Create three-column layout
  - Left: Field palette (draggable field types)
  - Center: Canvas area (form preview with sortable fields)
  - Right: Settings sidebar (form settings or field editor)

### Field Palette

- [ ] Display field types organized by category (Basic, Choice, Advanced, Layout)
- [ ] Each field type shows icon and label
- [ ] Implement drag functionality using `x-drag-item` directive
- [ ] Support keyboard accessibility for field selection

### Canvas Area

- [ ] Display all form fields in order
- [ ] Implement `x-drag-context` for reordering
- [ ] Show field labels and types
- [ ] Visual feedback for selected field
- [ ] Handle empty state with helpful message
- [ ] Support multi-step form with step tabs

### Field Editor Sidebar

- [ ] Create `FieldEditor` sub-component
- [ ] General settings tab: label, placeholder, help text, width
- [ ] Validation tab: required toggle, min/max, pattern, custom messages
- [ ] Options tab (for select/radio/checkbox_group): add/remove/reorder options
- [ ] Conditional logic tab: condition builder UI
- [ ] Advanced tab: CSS classes, default value

### Form Settings Sidebar

- [ ] Form name and slug
- [ ] Description
- [ ] Submit button text
- [ ] Success message or redirect URL
- [ ] Spam protection settings
- [ ] Multi-step toggle

### Preview Mode

- [ ] Toggle between edit and preview modes
- [ ] Preview renders form as users will see it
- [ ] Disable editing in preview mode

### Keyboard Accessibility

- [ ] Tab navigation through all interactive elements
- [ ] Enter to select/activate
- [ ] Escape to deselect/close modals
- [ ] Arrow keys for list navigation
- [ ] Focus management for dynamic content

## Accessibility Notes

- Use `x-drag-context` and `x-drag-item` from livewire-drag-and-drop (has built-in accessibility)
- All interactive elements must be keyboard accessible
- Announce changes to screen readers using aria-live regions
- Use proper heading hierarchy in panels
- Ensure color is not the only indicator of state

## UX Notes

- Auto-save form changes (debounced)
- Show unsaved changes indicator
- Confirmation before deleting fields
- Smooth animations for drag-drop
- Clear visual feedback for selected state
- Responsive design for smaller screens

## Testing Notes

- Test form creation and saving
- Test field add/edit/delete/duplicate/reorder
- Test multi-step enable/disable
- Test step management
- Test field settings persistence
- Test conditional logic configuration
- Test preview mode toggle
- Test keyboard navigation

## Documentation Notes

- Document the builder interface
- Document field type configuration options
- Document multi-step form setup
- Include screenshots of the interface

## Related Documents

- [03-form-builder.md](../03-form-builder.md)
- [05-field-types.md](../05-field-types.md)
