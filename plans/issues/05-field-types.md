Feature Name: Field Types System
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::3-FieldTypes"

## What is the Feature

Implement all 16+ field types with their configuration options, validation rules, and rendering components. Includes extensibility via hooks for custom field types.

## Tasks

### Field Type Registry

- [ ] Create field type configuration in `config/forms.php`
- [ ] Each type defines: label, icon, category, component, default_label, default_config, validations, settings
- [ ] Implement `forms.field_types` filter hook for extensibility

### Basic Fields

- [ ] **Text** field
  - Config: placeholder, max_length, pattern
  - Validation: required, min, max, pattern
  - Component: `<x-artisanpack-input type="text" />`

- [ ] **Email** field
  - Config: placeholder
  - Validation: required, email
  - Component: `<x-artisanpack-input type="email" />`

- [ ] **Phone** field
  - Config: placeholder, format hint
  - Validation: required, pattern (flexible phone format)
  - Component: `<x-artisanpack-input type="tel" />`

- [ ] **Textarea** field
  - Config: placeholder, rows, max_length, resize
  - Validation: required, min, max
  - Component: `<x-artisanpack-textarea />`

- [ ] **Number** field
  - Config: placeholder, min, max, step
  - Validation: required, numeric, min, max
  - Component: `<x-artisanpack-input type="number" />`

- [ ] **URL** field
  - Config: placeholder
  - Validation: required, url
  - Component: `<x-artisanpack-input type="url" />`

### Choice Fields

- [ ] **Select** field
  - Config: placeholder, options array, allow_other
  - Validation: required, in (option values)
  - Component: `<x-artisanpack-select />`
  - Options format: `{value, label}` array

- [ ] **Radio** field
  - Config: options array, inline, card style
  - Validation: required, in (option values)
  - Component: `<x-artisanpack-radio-group />`

- [ ] **Checkbox** field (single)
  - Config: none (label is main config)
  - Validation: required (if must be checked), accepted
  - Component: `<x-artisanpack-checkbox />`
  - Value: "1" or "0"

- [ ] **Checkbox Group** field
  - Config: options array, inline, card, min/max selections
  - Validation: required, array, min, max
  - Component: `<x-artisanpack-checkbox-group />`
  - Value: array of selected values

### Advanced Fields

- [ ] **Date** field
  - Config: min_date, max_date, format, disable_weekends
  - Validation: required, date, after, before
  - Component: `<x-artisanpack-datepicker />` or native input

- [ ] **Time** field
  - Config: format (12h/24h), min_time, max_time, step
  - Validation: required, date_format:H:i
  - Component: `<x-artisanpack-input type="time" />`

- [ ] **File** field
  - Config: allowed_types, max_size, max_files
  - Validation: required, file, mimes, max
  - Component: Native file input with Livewire
  - Security: Never allow executable extensions

- [ ] **Hidden** field
  - Config: default_value
  - Validation: none (not user-editable)
  - Component: `<input type="hidden" />`

### Layout Fields

- [ ] **Heading** field
  - Config: level (h2-h6), text (uses label)
  - No validation, not stored
  - Component: Dynamic heading tag

- [ ] **Paragraph** field
  - Config: text (uses help_text)
  - No validation, not stored
  - Component: `<div class="prose">{!! nl2br(e($text)) !!}</div>`

- [ ] **Divider** field
  - Config: style (solid/dashed/dotted), spacing
  - No validation, not stored
  - Component: `<div class="divider"></div>`

- [ ] **HTML** field (admin content only)
  - Config: content (HTML)
  - No validation, not stored
  - Component: Sanitized HTML output

### Validation Rule Builder

- [ ] Implement `buildValidationRules()` method on FormField model
- [ ] Handle required vs nullable
- [ ] Add type-specific rules based on field type
- [ ] Process custom min/max/pattern from validation_rules JSON
- [ ] Build file validation rules (mimes, max size)

### Field Type Extensibility

- [ ] Create `forms.field_types` filter hook
- [ ] Document how to register custom field types
- [ ] Example: star rating custom field type

## Accessibility Notes

- All field components must support proper labeling
- Required indicator must be accessible
- Help text linked via aria-describedby
- Error messages properly associated

## UX Notes

- Consistent styling across all field types
- Clear visual distinction between input and display fields
- Options editor should be intuitive (add/remove/reorder)
- File upload should show selected file before submission

## Testing Notes

- Test each field type renders correctly
- Test validation rules for each type
- Test file validation (type, size)
- Test options-based fields (select, radio, checkbox_group)
- Test custom field type registration via hooks

## Documentation Notes

- Document each field type with configuration options
- Document validation rules available per type
- Document how to create custom field types
- Include screenshots/examples

## Related Documents

- [05-field-types.md](../05-field-types.md)
- [03-form-builder.md](../03-form-builder.md)
- [04-form-renderer.md](../04-form-renderer.md)
