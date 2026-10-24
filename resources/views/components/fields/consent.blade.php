{{--
    A consent checkbox: the field label as a heading, the checkbox with its
    consent text, and the sanitized consent description (links allowed)
    below. The description is part of the checkbox's description, so screen
    readers announce what is being agreed to.

    Variables:
    - $field The FormField.
    - $value Whether the box is checked.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => false, 'error' => null])

@php
    $description = \ArtisanPackUI\Forms\FieldTypes\ConsentFieldType::description($field);
    $describedBy = trim(implode(' ', array_filter([
        '' !== $description ? 'consent-description-' . $field->name : null,
        $field->getDescribedBy($error),
    ])));
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => false])
<div class="flex flex-col gap-2">
    @if(filled($field->label))
        <p @class(['label-text font-medium', 'sr-only' => 'hidden' === $field->getLabelPlacement()]) id="label-{{ $field->name }}">
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </p>
    @endif

    <label class="label cursor-pointer justify-start gap-3">
        <input
            type="checkbox"
            wire:model.live="formData.{{ $field->name }}"
            id="field-{{ $field->name }}"
            class="checkbox checkbox-primary {{ $field->css_classes }}"
            @if($field->is_required) required aria-required="true" @endif
            @if(filled($error)) aria-invalid="true" @endif
            @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        />
        <span class="label-text whitespace-normal">{{ \ArtisanPackUI\Forms\FieldTypes\ConsentFieldType::checkboxText($field) }}</span>
    </label>

    @if('' !== $description)
        <div class="prose prose-sm max-w-none text-base-content/70" id="consent-description-{{ $field->name }}">{!! $description !!}</div>
    @endif
</div>
@endcomponent
