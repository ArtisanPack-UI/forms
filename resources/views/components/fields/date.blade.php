@props(['field', 'value' => '', 'error' => null])

@php
    $minDate = $field->getValidationRule('min_date');
    $maxDate = $field->getValidationRule('max_date');
@endphp

<x-artisanpack-input
    type="date"
    :label="$field->label"
    :hint="$field->help_text"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :class="$field->css_classes"
    :min="$minDate"
    :max="$maxDate"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-describedby="$error ? 'error-' . $field->name : ($field->help_text ? 'hint-' . $field->name : null)"
/>
