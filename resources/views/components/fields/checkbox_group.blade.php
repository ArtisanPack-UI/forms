@props(['field', 'value' => [], 'error' => null])

@php
    $options = $field->getConfig('options', []);
    $allowsOther = $field->allowsOtherChoice();
    $otherSelected = $allowsOther && is_array($value) && in_array(\ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_VALUE, $value, true);
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
<div
    class="flex flex-col gap-2 {{ $field->css_classes }}"
    role="group"
    @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
    @if($field->is_required) aria-required="true" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
>
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

@if($otherSelected)
    @include('forms::components.other-choice-input', ['field' => $field, 'error' => $error])
@endif
@endcomponent
