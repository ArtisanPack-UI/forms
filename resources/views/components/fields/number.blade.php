{{--
    A number input. With a number format (since 1.6.0) it is a text input with
    a decimal keypad, so visitors can type `1,234.56` or `1.234,56`; the
    server converts it to a plain decimal (see NumberFormat). The prefix and
    suffix sit beside the input and are given to screen readers by the
    `affix-{name}` description.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $format = $field->getNumberFormat();
    $step = $field->getValidationRule('step') ?? ($field->isIntegerOnly() ? 1 : 'any');
    $min = $field->getValidationRule('min');
    $max = $field->getValidationRule('max');
    $affixes = $field->getAffixes();
    // The input component renders a top label; the layout renders the others.
    $layoutLabel = 'top' !== $field->getLabelPlacement();
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true, 'renderError' => false])
<x-artisanpack-input
    :type="null === $format ? 'number' : 'text'"
    :inputmode="null === $format ? null : ($field->isIntegerOnly() ? 'numeric' : 'decimal')"
    :label="$layoutLabel ? null : $field->label"
    :placeholder="$field->placeholder"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :autocomplete="$field->getAutocomplete()"
    :readonly="$field->isReadOnly()"
    :class="$field->css_classes"
    :prefix="$affixes['prefix']"
    :suffix="$affixes['suffix']"
    :step="null === $format ? $step : null"
    :min="null === $format ? $min : null"
    :max="null === $format ? $max : null"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="$layoutLabel && filled($field->label) ? 'label-' . $field->name : null"
    :aria-describedby="$field->getDescribedBy($error)"
/>
@include('forms::components.fields.partials.input-extras', ['field' => $field])
@endcomponent
