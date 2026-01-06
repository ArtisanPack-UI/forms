@props(['field', 'value' => '', 'error' => null])

@php
    $rows = $field->getConfig('rows', 4);
@endphp

<x-artisanpack-textarea
    :label="$field->label"
    :placeholder="$field->placeholder"
    :hint="$field->help_text"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :class="$field->css_classes"
    :rows="$rows"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-describedby="$error ? 'error-' . $field->name : ($field->help_text ? 'hint-' . $field->name : null)"
/>
