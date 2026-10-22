@props(['field', 'value' => [], 'error' => null])

@php
    $options = $field->getRenderOptions();
    $allowsOther = $field->allowsOtherChoice();
    $otherSelected = $allowsOther && is_array($value) && in_array(\ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_VALUE, $value, true);
    $optionValues = collect($options)->map(fn ($option) => (string) ($option['value'] ?? $option))->values()->all();
    $showSelectAll = $field->offersSelectAll() && count($optionValues) > 1;
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
<div
    class="{{ $field->css_classes }}"
    role="group"
    @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
    @if($field->is_required) aria-required="true" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
>
    @if($showSelectAll)
        {{-- Checked when every choice is, mixed when some are; the "Other" choice is left alone. --}}
        <label class="label cursor-pointer justify-start gap-3 mb-2">
            <input
                type="checkbox"
                class="checkbox checkbox-primary"
                id="field-{{ $field->name }}-select-all"
                aria-controls="{{ collect($options)->keys()->map(fn ($index) => 'field-' . $field->name . '-' . $index)->implode(' ') }}"
                x-data="{ choices: @js($optionValues), selected() { const value = $wire.formData?.[@js($field->name)]; return Array.isArray(value) ? value.map(String) : []; } }"
                x-effect="const count = choices.filter(choice => selected().includes(choice)).length; $el.checked = count === choices.length; $el.indeterminate = count > 0 && count < choices.length"
                x-on:change="const others = selected().filter(choice => ! choices.includes(choice)); $wire.set(@js('formData.' . $field->name), $event.target.checked ? [...choices, ...others] : others)"
            />
            <span class="label-text font-medium">{{ __( 'Select All' ) }}</span>
        </label>
    @endif

    <div class="{{ $field->getChoiceLayoutClasses() }}">
        @foreach($options as $index => $option)
            @php
                $optionValue = $option['value'] ?? $option;
                $optionLabel = $option['label'] ?? $option;
            @endphp
            <label class="label cursor-pointer justify-start gap-3">
                <input
                    type="checkbox"
                    wire:model.live="formData.{{ $field->name }}"
                    value="{{ $optionValue }}"
                    class="checkbox checkbox-primary"
                    id="field-{{ $field->name }}-{{ $index }}"
                />
                <span class="label-text">{{ $optionLabel }}</span>
            </label>
        @endforeach

        @if($allowsOther)
            <label class="label cursor-pointer justify-start gap-3">
                <input
                    type="checkbox"
                    wire:model.live="formData.{{ $field->name }}"
                    value="{{ \ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_VALUE }}"
                    class="checkbox checkbox-primary"
                    id="field-{{ $field->name }}-other"
                    @if($otherSelected) aria-controls="field-{{ $field->name }}-other-text" @endif
                />
                <span class="label-text">{{ $field->getOtherChoiceLabel() }}</span>
            </label>
        @endif
    </div>
</div>

@if($otherSelected)
    @include('forms::components.other-choice-input', ['field' => $field, 'error' => $error])
@endif
@endcomponent
