Feature Name: Conditional Logic System
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::8-ConditionalLogic"

## What is the Feature

Implement a conditional logic system that allows form creators to show or hide fields based on the values of other fields. Includes visual condition builder UI, real-time Alpine.js evaluation, and server-side validation integration.

## Tasks

### Data Structure

- [ ] Define JSON structure for `conditional_logic` column
  - `action`: "show" or "hide"
  - `logic`: "all" (AND) or "any" (OR)
  - `rules`: array of condition rules
  - Each rule: `field_uuid`, `operator`, `value`

### Operators

- [ ] Implement all comparison operators:
  - `equals` / `not_equals`
  - `contains` / `not_contains`
  - `starts_with` / `ends_with`
  - `is_empty` / `is_not_empty`
  - `greater_than` / `less_than`
  - `greater_or_equal` / `less_or_equal`
  - `in` / `not_in` (comma-separated lists)

### Frontend Evaluation (Alpine.js)

- [ ] Create Alpine.js methods in FormRenderer:
  - `evaluateCondition(logic)` - Main evaluation function
  - `evaluateRule(rule)` - Single rule evaluation
  - `getFieldValue(uuid)` - Get field value by UUID
- [ ] Build `fieldMap` (UUID to field name mapping)
- [ ] Use `x-show` with `evaluateCondition()` for field visibility
- [ ] Add smooth transitions for show/hide

### Server-Side Evaluation (PHP)

- [ ] Implement `evaluateConditionalLogic(FormField $field)` method
- [ ] Implement `evaluateRule($fieldValue, $operator, $ruleValue)` method
- [ ] Handle array values (checkbox groups)
- [ ] Skip validation for hidden fields in `buildAllValidationRules()`

### Condition Editor UI

- [ ] Create condition builder in field editor sidebar
- [ ] Toggle to enable/disable conditional logic
- [ ] Action selector (show/hide)
- [ ] Logic type selector (all/any)
- [ ] Rule list with add/remove functionality
- [ ] For each rule:
  - Target field dropdown (other fields in form)
  - Operator dropdown
  - Value input (or dropdown for select/radio fields)
- [ ] Dynamic value input based on target field type

### FormBuilder Integration

- [ ] Expose `availableConditionFields` computed property
- [ ] Filter out current field and layout fields
- [ ] Handle fields in different steps

### Edge Cases

- [ ] Handle circular dependencies (field A depends on B, B depends on A)
- [ ] Handle deleted target fields gracefully
- [ ] Handle fields in different steps of multi-step forms
- [ ] Handle deeply nested conditions

## Accessibility Notes

- Hidden fields should be truly hidden (not just visually)
- Screen readers should not read hidden field labels
- Use appropriate ARIA live regions for dynamic visibility changes
- Ensure hidden fields are skipped in tab order

## UX Notes

- Real-time field visibility updates as user types
- Smooth animations for show/hide (not jarring)
- Clear indication in builder which fields have conditions
- Condition preview in field list
- Warning if condition references deleted field

## Testing Notes

- Test all operators with various data types
- Test "all" vs "any" logic combination
- Test nested/complex conditions
- Test that hidden fields are not validated
- Test that hidden field values are not included in submission (optional)
- Test frontend and backend evaluation produce same results
- Test condition editor saves correctly

## Documentation Notes

- Document conditional logic JSON structure
- Document all available operators with examples
- Document common use cases (show "other" field, cascading selects, etc.)
- Document limitations (no circular dependencies)

## Related Documents

- [06-conditional-logic.md](../06-conditional-logic.md)
- [03-form-builder.md](../03-form-builder.md)
- [04-form-renderer.md](../04-form-renderer.md)
