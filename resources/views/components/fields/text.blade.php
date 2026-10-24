@props(['field', 'value' => '', 'error' => null])

@php
    // The input component renders a top label; the layout renders the others.
    $layoutLabel = 'top' !== $field->getLabelPlacement();
    $mask = $field->getInputMask();
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true, 'renderError' => false])
@component('forms::components.fields.partials.counter', ['field' => $field])
<x-artisanpack-input
    type="text"
    :label="$layoutLabel ? null : $field->label"
    :placeholder="$field->placeholder"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :autocomplete="$field->getAutocomplete()"
    :readonly="$field->isReadOnly()"
    :class="$field->css_classes"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="$layoutLabel && filled($field->label) ? 'label-' . $field->name : null"
    :aria-describedby="$field->getDescribedBy($error)"
    :minlength="$field->getLengthLimit('min')"
    :maxlength="$field->getLengthLimit('max')"
    :x-mask="$mask"
/>
@endcomponent
@include('forms::components.fields.partials.input-extras', ['field' => $field])
@endcomponent
