{{--
    A slider (since 1.6.0): a native `<input type="range">` from the field's
    minimum to its maximum in steps of its step, with the chosen value shown
    beside it (see SliderFieldType). The value label adds the prefix and
    suffix, and the input's `aria-valuetext` reads the same, so screen readers
    announce "$40" rather than "40". Alpine updates the label while the thumb
    moves; Livewire gets the value once it settles.

    `x-artisanpack-range` is not used because it takes whole-number limits
    only and renders its own label and errors.

    Variables:
    - $field The FormField.
    - $value The value.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $sliderType = \ArtisanPackUI\Forms\FieldTypes\SliderFieldType::class;
    $limits = $sliderType::limits($field);
    $affixes = $sliderType::valueAffixes($field);
    $current = is_numeric($value) ? (string) $value : $sliderType::initialValue($field, null);
    $readOnly = $field->isReadOnly();
    $valueId = $field->domId() . '-value';
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => $field->domId()])
<div
    class="flex items-center gap-4"
    x-data="{
        value: @js($current),
        before: @js($affixes['before']),
        after: @js($affixes['after']),
        get text() { return this.before + this.value + this.after; },
    }"
>
    <input
        type="range"
        id="{{ $field->domId() }}"
        name="{{ $field->name }}"
        wire:model.live.debounce.250ms="formData.{{ $field->name }}"
        x-on:input="value = $event.target.value"
        min="{{ $limits['min'] }}"
        max="{{ $limits['max'] }}"
        step="{{ $limits['step'] }}"
        value="{{ $current }}"
        class="range range-primary flex-1 {{ $field->css_classes }}"
        aria-valuetext="{{ $sliderType::valueText($field, $current) }}"
        x-bind:aria-valuetext="text"
        @if($field->is_required) aria-required="true" @endif
        @if($readOnly) disabled aria-readonly="true" @endif
        @if(filled($error)) aria-invalid="true" @endif
        @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
    />

    @if($sliderType::showsValue($field))
        <output
            id="{{ $valueId }}"
            for="{{ $field->domId() }}"
            class="min-w-16 text-right font-medium tabular-nums"
            aria-hidden="true"
            x-text="text"
        >{{ $sliderType::valueText($field, $current) }}</output>
    @endif
</div>

<div class="mt-1 flex justify-between text-xs text-base-content/60" aria-hidden="true">
    <span>{{ $sliderType::valueText($field, (string) $limits['min']) }}</span>
    <span>{{ $sliderType::valueText($field, (string) $limits['max']) }}</span>
</div>
@endcomponent
