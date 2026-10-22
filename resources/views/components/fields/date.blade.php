@props(['field', 'value' => '', 'error' => null])

@php
    $minDate = $field->getValidationRule('min_date');
    $maxDate = $field->getValidationRule('max_date');
@endphp

{{-- A plain input rather than <x-artisanpack-input>: that component nests the
     input inside a `.input` wrapper, where daisyUI's `input[type=date]` rule
     makes it inline-block and pins the date text to the top of the box. --}}
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
<input
    type="date"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
    @if(filled($minDate)) min="{{ $minDate }}" @endif
    @if(filled($maxDate)) max="{{ $maxDate }}" @endif
    @if($field->getAutocomplete()) autocomplete="{{ $field->getAutocomplete() }}" @endif
    @if($field->isReadOnly()) readonly @endif
    @if($field->is_required) required aria-required="true" @endif
    @if($error) aria-invalid="true" @endif
    @unless($field->label) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>
@endcomponent
