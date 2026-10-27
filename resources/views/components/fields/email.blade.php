{{--
    An email input. With email confirmation on, a fieldset (legend = the field
    label) holds the email input and a "Confirm Email" input bound to
    `formData.{name}_confirmation`, each with its own sub-label; a mismatch is
    the field's error. Pasting into the confirm input is never blocked.
--}}
@props(['field', 'value' => '', 'error' => null])

@if($field->requiresEmailConfirmation())
@php
    $labels = $field->getEmailConfirmationLabels();
    $confirmKey = $field->getConfirmationKey();
    $subLabelPlacement = $field->getSubLabelPlacement();
    $descriptionPlacement = $field->getDescriptionPlacement();
    $describedBy = $field->getDescribedBy($error);
    $inputs = [
        ['id' => $field->domId(), 'model' => $field->name, 'label' => $labels['email'], 'placeholder' => $field->placeholder, 'autocomplete' => $field->getAutocomplete()],
        ['id' => $field->domId() . '_confirmation', 'model' => $confirmKey, 'label' => $labels['confirm'], 'placeholder' => $labels['confirm_placeholder'], 'autocomplete' => $field->getAutocomplete()],
    ];
@endphp
<fieldset class="fieldset {{ $field->css_classes }}" id="{{ $field->domId('fieldset') }}" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif>
    @if(filled($field->label))
        <legend @class(['fieldset-legend', 'sr-only' => 'hidden' === $field->getLabelPlacement()])>
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    @if(filled($field->help_text) && 'above' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach($inputs as $input)
            <div class="flex flex-col gap-1">
                @if('below' !== $subLabelPlacement)
                    <label for="{{ $input['id'] }}" @class(['label text-sm', 'sr-only' => 'hidden' === $subLabelPlacement])>{{ $input['label'] }}</label>
                @endif
                <input
                    type="email"
                    id="{{ $input['id'] }}"
                    name="{{ $input['model'] }}"
                    wire:model.live="formData.{{ $input['model'] }}"
                    @class(['input input-bordered w-full', 'input-error' => filled($error)])
                    @if(filled($input['placeholder'])) placeholder="{{ $input['placeholder'] }}" @endif
                    @if(filled($input['autocomplete'])) autocomplete="{{ $input['autocomplete'] }}" @endif
                    @if($field->is_required) required aria-required="true" @endif
                    @if(filled($error)) aria-invalid="true" aria-describedby="{{ $field->domId('error') }}" @endif
                />
                @if('below' === $subLabelPlacement)
                    <label for="{{ $input['id'] }}" class="fieldset-label text-xs">{{ $input['label'] }}</label>
                @endif
            </div>
        @endforeach
    </div>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    @if(filled($error))
        <p class="fieldset-label text-error" id="{{ $field->domId('error') }}">{{ $error }}</p>
    @endif
</fieldset>
@else
@php
    // The input component renders a top label; the layout renders the others.
    $layoutLabel = 'top' !== $field->getLabelPlacement();
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true])
<x-artisanpack-input
    type="email"
    :label="$layoutLabel ? null : $field->label"
    :placeholder="$field->placeholder"
    :omit-error="true"
    :aria-invalid="filled($error) ? 'true' : null"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="{{ $field->domId() }}"
    :autocomplete="$field->getAutocomplete()"
    :readonly="$field->isReadOnly()"
    :class="$field->css_classes"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="$layoutLabel && filled($field->label) ? $field->domId('label') : null"
    :aria-describedby="$field->getDescribedBy($error)"
/>
@endcomponent
@endif
