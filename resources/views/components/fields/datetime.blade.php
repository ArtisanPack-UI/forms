{{--
    A date and time input (since 1.6.0): the native `datetime-local` picker,
    entangled with `formData.{name}` as `YYYY-MM-DDTHH:MM` and stored as
    `Y-m-d H:i` (see DateTimeFieldType). The native `min` and `max` follow the
    field's resolved min and max dates, from the start of the first day to the
    end of the last.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $limits = $field->getDateLimits();
    $step = $field->getConfig('step');
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
<input
    type="datetime-local"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
    @if(filled($limits['min'])) min="{{ $limits['min'] }}T00:00" @endif
    @if(filled($limits['max'])) max="{{ $limits['max'] }}T23:59" @endif
    @if(is_numeric($step) && (int) $step > 0) step="{{ (int) $step }}" @endif
    @if($field->isReadOnly()) readonly @endif
    @if($field->is_required) required aria-required="true" @endif
    @if($error) aria-invalid="true" @endif
    @unless($field->label) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>
@endcomponent
