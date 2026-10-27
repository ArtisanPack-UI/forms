@props(['field', 'value' => '', 'error' => null])

@if($field->usesRichText())
{{-- The editor references the layout's label with aria-labelledby. --}}
@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
@component('forms::components.fields.partials.counter', ['field' => $field])
@include('forms::components.fields.partials.rich-text', ['field' => $field, 'value' => $value, 'error' => $error])
@endcomponent
@endcomponent
@else
@php
    $rows = $field->getConfig('rows', 4);
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'focusInput' => true])
@component('forms::components.fields.partials.counter', ['field' => $field])
<x-artisanpack-textarea
    :label="null"
    :placeholder="$field->placeholder"
    :omit-error="true"
    :aria-invalid="filled($error) ? 'true' : null"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :autocomplete="$field->getAutocomplete()"
    :readonly="$field->isReadOnly()"
    :class="$field->css_classes"
    :rows="$rows"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="filled($field->label) ? 'label-' . $field->name : null"
    :aria-label="filled($field->label) ? null : \Illuminate\Support\Str::headline($field->name)"
    :aria-describedby="$field->getDescribedBy($error)"
    :minlength="$field->getLengthLimit('min')"
    :maxlength="$field->getLengthLimit('max')"
/>
@endcomponent
@endcomponent
@endif
