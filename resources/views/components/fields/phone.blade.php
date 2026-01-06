@props(['field', 'value' => '', 'error' => null])

@php
    // Build aria-describedby with both hint and error IDs when applicable
    $describedBy = [];
    if ($error) {
        $describedBy[] = 'error-' . $field->name;
    }
    if ($field->help_text) {
        $describedBy[] = 'hint-' . $field->name;
    }
    $ariaDescribedBy = !empty($describedBy) ? implode(' ', $describedBy) : null;
@endphp

<x-artisanpack-input
    type="tel"
    :label="$field->label"
    :placeholder="$field->placeholder ?? '(123) 456-7890'"
    :hint="$field->help_text"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :class="$field->css_classes"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-describedby="$ariaDescribedBy"
/>
