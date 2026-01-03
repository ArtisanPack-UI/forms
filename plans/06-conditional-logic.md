# Conditional Logic

**Purpose:** Define the conditional logic system for showing/hiding fields based on other field values.

---

## Overview

Conditional logic allows form creators to show or hide fields based on the values of other fields. This creates dynamic, intelligent forms that adapt to user input.

**Features:**
- Show/hide fields based on conditions
- Multiple conditions with AND/OR logic
- Various comparison operators
- Real-time evaluation via Alpine.js
- Server-side validation respects conditions

---

## Data Structure

Conditional logic is stored in the `conditional_logic` JSON column of `form_fields`:

```json
{
    "action": "show",
    "logic": "all",
    "rules": [
        {
            "field_uuid": "abc-123-def",
            "operator": "equals",
            "value": "yes"
        },
        {
            "field_uuid": "xyz-789-ghi",
            "operator": "is_not_empty",
            "value": null
        }
    ]
}
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `action` | string | `"show"` or `"hide"` - what to do when conditions are met |
| `logic` | string | `"all"` (AND) or `"any"` (OR) - how to combine rules |
| `rules` | array | Array of condition rules |

### Rule Properties

| Property | Type | Description |
|----------|------|-------------|
| `field_uuid` | string | UUID of the target field to evaluate |
| `operator` | string | Comparison operator |
| `value` | mixed | Value to compare against (null for unary operators) |

---

## Operators

| Operator | Description | Requires Value |
|----------|-------------|----------------|
| `equals` | Field value equals specified value | Yes |
| `not_equals` | Field value does not equal specified value | Yes |
| `contains` | Field value contains specified string | Yes |
| `not_contains` | Field value does not contain specified string | Yes |
| `starts_with` | Field value starts with specified string | Yes |
| `ends_with` | Field value ends with specified string | Yes |
| `is_empty` | Field value is empty/null | No |
| `is_not_empty` | Field value is not empty | No |
| `greater_than` | Field value is greater than specified number | Yes |
| `less_than` | Field value is less than specified number | Yes |
| `greater_or_equal` | Field value is >= specified number | Yes |
| `less_or_equal` | Field value is <= specified number | Yes |
| `in` | Field value is in specified list (comma-separated) | Yes |
| `not_in` | Field value is not in specified list | Yes |

---

## Frontend Evaluation (Alpine.js)

Conditional logic is evaluated in real-time on the frontend using Alpine.js:

### FormRenderer Integration

```blade
{{-- In form-renderer.blade.php --}}
<div
    x-data="{
        formData: @entangle('formData'),
        evaluateCondition(logic) {
            if (!logic || !logic.rules || logic.rules.length === 0) {
                return true;
            }

            const results = logic.rules.map(rule => this.evaluateRule(rule));
            const conditionMet = logic.logic === 'all'
                ? results.every(r => r)
                : results.some(r => r);

            return logic.action === 'show' ? conditionMet : !conditionMet;
        },
        evaluateRule(rule) {
            const fieldValue = this.getFieldValue(rule.field_uuid);
            const ruleValue = rule.value;

            switch (rule.operator) {
                case 'equals':
                    return String(fieldValue) === String(ruleValue);
                case 'not_equals':
                    return String(fieldValue) !== String(ruleValue);
                case 'contains':
                    return String(fieldValue).includes(String(ruleValue));
                case 'not_contains':
                    return !String(fieldValue).includes(String(ruleValue));
                case 'starts_with':
                    return String(fieldValue).startsWith(String(ruleValue));
                case 'ends_with':
                    return String(fieldValue).endsWith(String(ruleValue));
                case 'is_empty':
                    return fieldValue === '' || fieldValue === null || fieldValue === undefined;
                case 'is_not_empty':
                    return fieldValue !== '' && fieldValue !== null && fieldValue !== undefined;
                case 'greater_than':
                    return parseFloat(fieldValue) > parseFloat(ruleValue);
                case 'less_than':
                    return parseFloat(fieldValue) < parseFloat(ruleValue);
                case 'greater_or_equal':
                    return parseFloat(fieldValue) >= parseFloat(ruleValue);
                case 'less_or_equal':
                    return parseFloat(fieldValue) <= parseFloat(ruleValue);
                case 'in':
                    return ruleValue.split(',').map(v => v.trim()).includes(String(fieldValue));
                case 'not_in':
                    return !ruleValue.split(',').map(v => v.trim()).includes(String(fieldValue));
                default:
                    return true;
            }
        },
        getFieldValue(uuid) {
            // Map UUID to field name via data attribute
            const fieldName = this.fieldMap[uuid];
            return fieldName ? this.formData[fieldName] : '';
        },
        fieldMap: @js($this->fieldUuidMap)
    }"
>
    {{-- Form fields --}}
</div>
```

### Field Wrapper with Conditions

```blade
{{-- Field wrapper with conditional visibility --}}
@foreach($this->currentFields as $field)
    <div
        x-show="evaluateCondition(@js($field->conditional_logic))"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        class="{{ $field->width_class }}"
    >
        @include('forms::components.fields.' . $field->type, ['field' => $field])
    </div>
@endforeach
```

---

## Backend Evaluation (PHP)

Server-side evaluation ensures validation respects conditional logic:

### FormRenderer Method

```php
protected function evaluateConditionalLogic(FormField $field): bool
{
    if (!$field->has_conditional_logic) {
        return true; // Always visible if no conditions
    }

    $logic = $field->conditional_logic;
    $action = $logic['action'] ?? 'show';
    $logicType = $logic['logic'] ?? 'all';
    $rules = $logic['rules'] ?? [];

    if (empty($rules)) {
        return true;
    }

    $results = [];

    foreach ($rules as $rule) {
        // Find the target field by UUID
        $targetField = $this->form->fields->firstWhere('uuid', $rule['field_uuid']);

        if (!$targetField) {
            continue; // Skip if target field doesn't exist
        }

        $fieldValue = $this->formData[$targetField->name] ?? '';
        $ruleValue = $rule['value'] ?? '';
        $operator = $rule['operator'] ?? 'equals';

        $results[] = $this->evaluateRule($fieldValue, $operator, $ruleValue);
    }

    // Apply logic type
    $conditionMet = $logicType === 'all'
        ? !in_array(false, $results, true) // All must be true
        : in_array(true, $results, true);  // At least one must be true

    // Apply action
    return $action === 'show' ? $conditionMet : !$conditionMet;
}

protected function evaluateRule(mixed $fieldValue, string $operator, mixed $ruleValue): bool
{
    // Handle array values (checkbox groups, etc.)
    if (is_array($fieldValue)) {
        $fieldValue = implode(',', $fieldValue);
    }

    return match ($operator) {
        'equals' => (string) $fieldValue === (string) $ruleValue,
        'not_equals' => (string) $fieldValue !== (string) $ruleValue,
        'contains' => str_contains((string) $fieldValue, (string) $ruleValue),
        'not_contains' => !str_contains((string) $fieldValue, (string) $ruleValue),
        'starts_with' => str_starts_with((string) $fieldValue, (string) $ruleValue),
        'ends_with' => str_ends_with((string) $fieldValue, (string) $ruleValue),
        'is_empty' => empty($fieldValue),
        'is_not_empty' => !empty($fieldValue),
        'greater_than' => is_numeric($fieldValue) && (float) $fieldValue > (float) $ruleValue,
        'less_than' => is_numeric($fieldValue) && (float) $fieldValue < (float) $ruleValue,
        'greater_or_equal' => is_numeric($fieldValue) && (float) $fieldValue >= (float) $ruleValue,
        'less_or_equal' => is_numeric($fieldValue) && (float) $fieldValue <= (float) $ruleValue,
        'in' => in_array((string) $fieldValue, array_map('trim', explode(',', (string) $ruleValue))),
        'not_in' => !in_array((string) $fieldValue, array_map('trim', explode(',', (string) $ruleValue))),
        default => true,
    };
}
```

### Validation Integration

Hidden fields are excluded from validation:

```php
protected function buildAllValidationRules(): array
{
    $rules = [];

    foreach ($this->form->fields_ordered as $field) {
        // Skip hidden fields - they don't need validation
        if (!$this->evaluateConditionalLogic($field)) {
            continue;
        }

        $rules["formData.{$field->name}"] = $field->buildValidationRules();
    }

    return $rules;
}
```

---

## Condition Editor UI

The form builder includes a visual condition editor:

### Condition Editor Component

```blade
{{-- resources/views/admin/partials/field-editor-conditional.blade.php --}}

<div class="space-y-4">
    {{-- Enable/Disable Toggle --}}
    <label class="flex items-center justify-between cursor-pointer">
        <span class="label-text font-medium">Enable Conditional Logic</span>
        <input
            type="checkbox"
            class="toggle toggle-primary toggle-sm"
            wire:model.live="fieldData.conditional_logic.enabled"
            x-on:change="$wire.initConditionalLogic()"
        />
    </label>

    @if(!empty($fieldData['conditional_logic']['enabled']))
        {{-- Action Selector --}}
        <div>
            <label class="label">
                <span class="label-text">Action</span>
            </label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="conditional_action"
                        value="show"
                        wire:model.live="fieldData.conditional_logic.action"
                        class="radio radio-sm radio-primary"
                    />
                    <span>Show this field</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="conditional_action"
                        value="hide"
                        wire:model.live="fieldData.conditional_logic.action"
                        class="radio radio-sm radio-primary"
                    />
                    <span>Hide this field</span>
                </label>
            </div>
        </div>

        {{-- Logic Type --}}
        <div>
            <label class="label">
                <span class="label-text">When</span>
            </label>
            <select
                wire:model.live="fieldData.conditional_logic.logic"
                class="select select-bordered w-full"
            >
                <option value="all">All conditions are met (AND)</option>
                <option value="any">Any condition is met (OR)</option>
            </select>
        </div>

        {{-- Condition Rules --}}
        <div class="space-y-3">
            <label class="label">
                <span class="label-text">Conditions</span>
            </label>

            @foreach($fieldData['conditional_logic']['rules'] ?? [] as $index => $rule)
                <div class="flex items-center gap-2 p-3 bg-base-200 rounded-lg">
                    {{-- Target Field --}}
                    <select
                        wire:model.live="fieldData.conditional_logic.rules.{{ $index }}.field_uuid"
                        class="select select-bordered select-sm flex-1"
                    >
                        <option value="">Select field...</option>
                        @foreach($this->availableConditionFields as $condField)
                            <option value="{{ $condField->uuid }}">{{ $condField->label }}</option>
                        @endforeach
                    </select>

                    {{-- Operator --}}
                    <select
                        wire:model.live="fieldData.conditional_logic.rules.{{ $index }}.operator"
                        class="select select-bordered select-sm"
                    >
                        <option value="equals">equals</option>
                        <option value="not_equals">does not equal</option>
                        <option value="contains">contains</option>
                        <option value="not_contains">does not contain</option>
                        <option value="is_empty">is empty</option>
                        <option value="is_not_empty">is not empty</option>
                        <option value="greater_than">is greater than</option>
                        <option value="less_than">is less than</option>
                    </select>

                    {{-- Value (hidden for unary operators) --}}
                    @if(!in_array($rule['operator'] ?? '', ['is_empty', 'is_not_empty']))
                        @php
                            $targetField = $this->form->fields->firstWhere('uuid', $rule['field_uuid']);
                        @endphp

                        @if($targetField && in_array($targetField->type, ['select', 'radio', 'checkbox_group']))
                            {{-- Show options dropdown --}}
                            <select
                                wire:model.live="fieldData.conditional_logic.rules.{{ $index }}.value"
                                class="select select-bordered select-sm flex-1"
                            >
                                <option value="">Select value...</option>
                                @foreach($targetField->options as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                type="text"
                                wire:model.blur="fieldData.conditional_logic.rules.{{ $index }}.value"
                                class="input input-bordered input-sm flex-1"
                                placeholder="Value"
                            />
                        @endif
                    @endif

                    {{-- Remove Button --}}
                    <button
                        type="button"
                        wire:click="removeConditionRule({{ $index }})"
                        class="btn btn-ghost btn-sm btn-square text-error"
                    >
                        <x-artisanpack-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>
            @endforeach

            {{-- Add Rule Button --}}
            <button
                type="button"
                wire:click="addConditionRule"
                class="btn btn-ghost btn-sm"
            >
                <x-artisanpack-icon name="o-plus" class="w-4 h-4" />
                Add Condition
            </button>
        </div>
    @endif
</div>
```

---

## Use Cases

### Example 1: Show Additional Fields Based on Selection

**Scenario:** Show "Other" text field when user selects "Other" in a dropdown.

```json
{
    "action": "show",
    "logic": "all",
    "rules": [
        {
            "field_uuid": "source-field-uuid",
            "operator": "equals",
            "value": "other"
        }
    ]
}
```

### Example 2: Show Section Based on Multiple Conditions

**Scenario:** Show shipping address fields only if "Ship to different address" is checked AND country is US.

```json
{
    "action": "show",
    "logic": "all",
    "rules": [
        {
            "field_uuid": "different-address-uuid",
            "operator": "equals",
            "value": "1"
        },
        {
            "field_uuid": "country-uuid",
            "operator": "equals",
            "value": "US"
        }
    ]
}
```

### Example 3: Hide Fields Based on Any Condition

**Scenario:** Hide discount code field if customer is premium OR order total > $100.

```json
{
    "action": "hide",
    "logic": "any",
    "rules": [
        {
            "field_uuid": "customer-type-uuid",
            "operator": "equals",
            "value": "premium"
        },
        {
            "field_uuid": "order-total-uuid",
            "operator": "greater_than",
            "value": "100"
        }
    ]
}
```

---

## Related Documents

- [03-form-builder.md](03-form-builder.md) - Condition editor in form builder
- [04-form-renderer.md](04-form-renderer.md) - Runtime evaluation
- [05-field-types.md](05-field-types.md) - Field types that can be condition targets
