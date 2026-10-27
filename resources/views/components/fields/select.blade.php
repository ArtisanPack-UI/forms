@props(['field', 'value' => '', 'error' => null])

@php
    $options = $field->getRenderOptions();
@endphp

@if($field->isSearchable())
    @component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
        @include('forms::components.combobox', ['field' => $field, 'error' => $error, 'options' => $options, 'multiple' => false])
    @endcomponent
@else
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'focusInput' => true])
<x-artisanpack-select
    :label="null"
    :placeholder="$field->placeholder ?? __( 'Select an option...' )"
    :omit-error="true"
    :aria-invalid="filled($error) ? 'true' : null"
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
    :aria-labelledby="filled($field->label) ? 'label-' . $field->name : null"
    :aria-label="filled($field->label) ? null : \Illuminate\Support\Str::headline($field->name)"
    :aria-describedby="$field->getDescribedBy($error)"
/>
@endcomponent
@endif
