@props(['field', 'value' => '', 'error' => null])

@php
    $minTime = $field->getValidationRule('min_time');
    $maxTime = $field->getValidationRule('max_time');
    $step = $field->getConfig('step', 60);
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
<input
    type="time"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
    @if($minTime) min="{{ $minTime }}" @endif
    @if($maxTime) max="{{ $maxTime }}" @endif
    @if($step) step="{{ $step }}" @endif
    @if($field->isReadOnly()) readonly @endif
    @if($field->is_required) required aria-required="true" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>
@endcomponent
