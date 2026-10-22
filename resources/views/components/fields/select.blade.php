@props(['field', 'value' => '', 'error' => null])

@php
    $options = $field->getConfig('options', []);
    // The input component renders a top label; the layout renders the others.
    $layoutLabel = 'top' !== $field->getLabelPlacement();
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true, 'renderError' => false])
<x-artisanpack-select
    :label="$layoutLabel ? null : $field->label"
    :placeholder="$field->placeholder ?? __( 'Select an option...' )"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :autocomplete="$field->getAutocomplete()"
    :disabled="$field->isReadOnly()"
    :aria-readonly="$field->isReadOnly() ? 'true' : null"
    :class="$field->css_classes"
    :options="$options"
    option-value="value"
    option-label="label"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="$layoutLabel && filled($field->label) ? 'label-' . $field->name : null"
    :aria-describedby="$field->getDescribedBy($error)"
/>
@endcomponent
