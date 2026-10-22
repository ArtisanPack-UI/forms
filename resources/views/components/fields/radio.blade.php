@props(['field', 'value' => '', 'error' => null])

@php
    $options = $field->getRenderOptions();
    $allowsOther = $field->allowsOtherChoice();
    $otherSelected = $allowsOther && \ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_VALUE === $value;
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
<div
    class="{{ $field->getChoiceLayoutClasses() }} {{ $field->css_classes }}"
    role="radiogroup"
    @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
    @if($field->is_required) aria-required="true" @endif
    @if($field->isReadOnly()) aria-readonly="true" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
>
    @foreach($options as $index => $option)
        @php
            $optionValue = $option['value'] ?? $option;
            $optionLabel = $option['label'] ?? $option;
        @endphp
        <label class="label cursor-pointer justify-start gap-3">
            <input
                type="radio"
                wire:model.live="formData.{{ $field->name }}"
                name="{{ $field->name }}"
                value="{{ $optionValue }}"
                class="radio radio-primary"
                id="field-{{ $field->name }}-{{ $index }}"
                @disabled($field->isReadOnly())
            />
            <span class="label-text">{{ $optionLabel }}</span>
        </label>
    @endforeach

    @if($allowsOther)
        <label class="label cursor-pointer justify-start gap-3">
            <input
                type="radio"
                wire:model.live="formData.{{ $field->name }}"
                name="{{ $field->name }}"
                value="{{ \ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_VALUE }}"
                class="radio radio-primary"
                id="field-{{ $field->name }}-other"
                @if($otherSelected) aria-controls="field-{{ $field->name }}-other-text" @endif
                @disabled($field->isReadOnly())
            />
            <span class="label-text">{{ $field->getOtherChoiceLabel() }}</span>
        </label>
    @endif
</div>

@if($otherSelected)
    @include('forms::components.other-choice-input', ['field' => $field, 'error' => $error])
@endif
@endcomponent
