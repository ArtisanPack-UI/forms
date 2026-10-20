@props(['field', 'value' => '', 'error' => null])

@php
    $options = $field->getConfig('options', []);
@endphp

<x-artisanpack-select
    :label="$field->label"
    :placeholder="$field->placeholder ?? __( 'Select an option...' )"
    :hint="$field->help_text"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :disabled="$field->isReadOnly()"
    :aria-readonly="$field->isReadOnly() ? 'true' : null"
    :class="$field->css_classes"
    :options="$options"
    option-value="value"
    option-label="label"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-describedby="$error ? 'error-' . $field->name : ($field->help_text ? 'hint-' . $field->name : null)"
/>
