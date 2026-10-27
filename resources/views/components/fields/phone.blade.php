{{--
    A telephone input. An International phone field (since 1.6.0) puts a
    country selector, bound to `formData.{name}_country`, before the input; a
    number typed without a `+` country code is read in that country (see
    PhoneFieldType). An input mask formats the number as it is typed.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    // The input component renders a top label; the layout renders the others.
    $layoutLabel = 'top' !== $field->getLabelPlacement();
    $mask = $field->getInputMask();
    $countries = $field->usesPhoneCountrySelector() ? \ArtisanPackUI\Forms\FieldTypes\PhoneFieldType::countries() : [];
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true, 'renderError' => false])
<x-artisanpack-input
    type="tel"
    :label="$layoutLabel ? null : $field->label"
    :placeholder="$field->placeholder ?? ([] === $countries ? __( '(123) 456-7890' ) : null)"
    :error="$error"
    :required="$field->is_required"
    wire:model.live="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    :autocomplete="$field->getAutocomplete()"
    :readonly="$field->isReadOnly()"
    :class="$field->css_classes"
    :aria-required="$field->is_required ? 'true' : 'false'"
    :aria-labelledby="$layoutLabel && filled($field->label) ? 'label-' . $field->name : null"
    :aria-describedby="$field->getDescribedBy($error)"
    :x-mask="$mask"
>
    @if([] !== $countries)
        <x-slot:prepend>
            <select
                id="field-{{ $field->getPhoneCountryKey() }}"
                wire:model.live="formData.{{ $field->getPhoneCountryKey() }}"
                class="select join-item w-auto max-w-48"
                autocomplete="country"
                aria-label="{{ __( ':field country', ['field' => $field->label] ) }}"
                @disabled($field->isReadOnly())
            >
                @foreach($countries as $country)
                    <option value="{{ $country['value'] }}">{{ $country['label'] }}</option>
                @endforeach
            </select>
        </x-slot:prepend>
    @endif
</x-artisanpack-input>
@include('forms::components.fields.partials.input-extras', ['field' => $field])
@endcomponent
