@props(['field', 'value' => '', 'error' => null])

@php
    $step = $field->getValidationRule('step') ?? 'any';
    $min = $field->getValidationRule('min');
    $max = $field->getValidationRule('max');
@endphp

<x-artisanpack-input
    type="number"
    :label="$field->label"
    :placeholder="$field->placeholder"
    :hint="$field->help_text"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :class="$field->css_classes"
    :step="$step"
    :min="$min"
    :max="$max"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-describedby="$error ? 'error-' . $field->name : ($field->help_text ? 'hint-' . $field->name : null)"
/>
