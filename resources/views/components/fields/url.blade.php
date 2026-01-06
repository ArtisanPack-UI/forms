@props(['field', 'value' => '', 'error' => null])

<x-artisanpack-input
    type="url"
    :label="$field->label"
    :placeholder="$field->placeholder ?? 'https://'"
    :hint="$field->help_text"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :class="$field->css_classes"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-describedby="$error ? 'error-' . $field->name : ($field->help_text ? 'hint-' . $field->name : null)"
/>
