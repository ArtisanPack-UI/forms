---
title: Conditional Logic
---

# Conditional Logic

Show or hide fields dynamically based on user input using conditional logic rules.

## Overview

Conditional logic allows you to:

- Show/hide fields based on other field values
- Create dynamic forms that adapt to user input
- Reduce form complexity by showing only relevant fields
- Build branching form flows

## Creating Conditions

### Via Form Builder

1. Select a field in the form builder
2. Go to the "Conditions" tab
3. Add conditions to show/hide the field
4. Set the logic (AND/OR)

### Programmatically

```php
use ArtisanPackUI\Forms\Models\FormField;

// Field that's only visible when "has_business" is checked
FormField::create([
    'form_id' => $form->id,
    'type' => 'text',
    'name' => 'business_name',
    'label' => 'Business Name',
    'order' => 5,
    'conditions' => [
        'logic' => 'and', // 'and' or 'or'
        'rules' => [
            [
                'field' => 'has_business',
                'operator' => 'equals',
                'value' => true,
            ],
        ],
    ],
]);
```

## Condition Structure

```php
'conditions' => [
    'logic' => 'and', // How to combine multiple rules
    'action' => 'show', // 'show' or 'hide'
    'rules' => [
        [
            'field' => 'field_name',     // Source field name
            'operator' => 'equals',       // Comparison operator
            'value' => 'expected_value',  // Value to compare
        ],
        // Additional rules...
    ],
],
```

## Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `equals` | Exact match | `country = "US"` |
| `not_equals` | Does not match | `status != "inactive"` |
| `contains` | Contains substring | `interests contains "sports"` |
| `not_contains` | Does not contain | `email not_contains "@test"` |
| `starts_with` | Starts with string | `phone starts_with "+1"` |
| `ends_with` | Ends with string | `email ends_with ".com"` |
| `greater_than` | Numeric greater than | `age > 18` |
| `less_than` | Numeric less than | `quantity < 100` |
| `greater_or_equal` | Numeric >= | `budget >= 1000` |
| `less_or_equal` | Numeric <= | `age <= 65` |
| `is_empty` | Field is empty | `notes is_empty` |
| `is_not_empty` | Field has value | `email is_not_empty` |
| `in` | Value in array | `country in ["US", "CA"]` |
| `not_in` | Value not in array | `status not_in ["banned"]` |

## Logic Types

### AND Logic

All conditions must be true:

```php
'conditions' => [
    'logic' => 'and',
    'rules' => [
        ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
        ['field' => 'age', 'operator' => 'greater_than', 'value' => 18],
    ],
],
// Shows when country is US AND age > 18
```

### OR Logic

Any condition can be true:

```php
'conditions' => [
    'logic' => 'or',
    'rules' => [
        ['field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
        ['field' => 'role', 'operator' => 'equals', 'value' => 'manager'],
    ],
],
// Shows when role is admin OR manager
```

## Examples

### Show Field Based on Checkbox

```php
// Source: checkbox field
FormField::create([
    'form_id' => $form->id,
    'type' => 'checkbox',
    'name' => 'is_business',
    'label' => 'This is a business inquiry',
    'order' => 1,
]);

// Target: shown when checkbox is checked
FormField::create([
    'form_id' => $form->id,
    'type' => 'text',
    'name' => 'company_name',
    'label' => 'Company Name',
    'order' => 2,
    'conditions' => [
        'rules' => [
            ['field' => 'is_business', 'operator' => 'equals', 'value' => true],
        ],
    ],
]);
```

### Show Field Based on Select

```php
// Source: select field
FormField::create([
    'form_id' => $form->id,
    'type' => 'select',
    'name' => 'contact_method',
    'label' => 'Preferred Contact Method',
    'options' => [
        ['value' => 'email', 'label' => 'Email'],
        ['value' => 'phone', 'label' => 'Phone'],
        ['value' => 'mail', 'label' => 'Mail'],
    ],
    'order' => 1,
]);

// Target: shown when phone is selected
FormField::create([
    'form_id' => $form->id,
    'type' => 'phone',
    'name' => 'phone_number',
    'label' => 'Phone Number',
    'order' => 2,
    'conditions' => [
        'rules' => [
            ['field' => 'contact_method', 'operator' => 'equals', 'value' => 'phone'],
        ],
    ],
]);

// Target: shown when mail is selected
FormField::create([
    'form_id' => $form->id,
    'type' => 'textarea',
    'name' => 'mailing_address',
    'label' => 'Mailing Address',
    'order' => 3,
    'conditions' => [
        'rules' => [
            ['field' => 'contact_method', 'operator' => 'equals', 'value' => 'mail'],
        ],
    ],
]);
```

### Complex Conditions

```php
FormField::create([
    'form_id' => $form->id,
    'type' => 'number',
    'name' => 'discount_code',
    'label' => 'Discount Code',
    'order' => 10,
    'conditions' => [
        'logic' => 'and',
        'rules' => [
            ['field' => 'is_member', 'operator' => 'equals', 'value' => true],
            ['field' => 'order_total', 'operator' => 'greater_than', 'value' => 100],
        ],
    ],
]);
// Shows when user is a member AND order total > 100
```

## Conditional Steps

Apply conditions to entire steps in multi-step forms:

```php
use ArtisanPackUI\Forms\Models\FormStep;

FormStep::create([
    'form_id' => $form->id,
    'title' => 'Business Details',
    'order' => 3,
    'conditions' => [
        'rules' => [
            ['field' => 'account_type', 'operator' => 'equals', 'value' => 'business'],
        ],
    ],
]);
```

## Service Usage

Use the ConditionalLogicService programmatically:

```php
use ArtisanPackUI\Forms\Services\ConditionalLogicService;

$conditionService = app(ConditionalLogicService::class);

// Check if a field should be visible
$isVisible = $conditionService->evaluateConditions(
    $field->conditions,
    $formData
);

// Get visible fields for current form state
$visibleFields = $conditionService->getVisibleFields($form, $formData);
```

## Client-Side Evaluation

Conditions are evaluated in real-time on the client:

```javascript
// Livewire handles this automatically, but you can also
// use Alpine.js for additional client-side logic

<div x-data="{ formData: @entangle('formData') }">
    <div x-show="formData.contact_method === 'phone'">
        <!-- Phone number field -->
    </div>
</div>
```

## Validation with Conditions

Hidden fields are excluded from validation:

```php
// In FormRenderer
protected function getValidationRules(): array
{
    $rules = [];

    foreach ($this->form->fields as $field) {
        // Skip validation for hidden fields
        if (!$this->conditionService->isVisible($field, $this->formData)) {
            continue;
        }

        $rules[$field->name] = $field->getValidationRules();
    }

    return $rules;
}
```

## Tips

1. **Keep it simple**: Complex nested conditions can confuse users
2. **Test thoroughly**: Verify all condition paths work correctly
3. **Consider mobile**: Ensure conditional fields work on all devices
4. **Provide defaults**: Set sensible defaults for conditional fields
5. **Document logic**: Add comments explaining complex conditions

## Debugging

Enable debug mode to see condition evaluation:

```php
$conditionService = app(ConditionalLogicService::class);
$conditionService->setDebug(true);

// Now logs condition evaluation to Laravel log
$isVisible = $conditionService->evaluateConditions($conditions, $data);
```

## Next Steps

- [Multi-Step Forms](Multi-Step-Forms) - Conditional steps
- [Form Builder](Form-Builder) - Creating forms
- [Notifications](Notifications) - Conditional notifications
