@props(['field', 'value' => '', 'error' => null])

@php
    // The input component renders a top label; the layout renders the others.
    $layoutLabel = 'top' !== $field->getLabelPlacement();

    // An address typed without a scheme gets one when the input loses focus,
    // as the server adds it before validation (see UrlSchemes).
    $schemes = $field->getAllowedUrlSchemes();
    $scheme = in_array('https', $schemes, true) ? 'https' : (in_array('http', $schemes, true) ? 'http' : null);
    $addScheme = null === $scheme ? null : "const typed = \$el.value.trim(); if (typed !== '' && ! /^[a-z][a-z0-9+.\\-]*:(?!\\d)/i.test(typed)) { \$el.value = (typed.startsWith('//') ? '{$scheme}:' : '{$scheme}://') + typed; \$el.dispatchEvent(new Event('input')); }";
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true])
<x-artisanpack-input
    type="url"
    :label="$layoutLabel ? null : $field->label"
    :placeholder="$field->placeholder ?? 'https://'"
    :omit-error="true"
    :aria-invalid="filled($error) ? 'true' : null"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :autocomplete="$field->getAutocomplete()"
    :readonly="$field->isReadOnly()"
    :class="$field->css_classes"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="$layoutLabel && filled($field->label) ? 'label-' . $field->name : null"
    :aria-describedby="$field->getDescribedBy($error)"
    :x-on:blur="$addScheme"
/>
@endcomponent
