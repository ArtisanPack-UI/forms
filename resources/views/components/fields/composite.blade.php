{{--
    A composite (multi-input) field, such as a name or an address.

    A `<fieldset>` whose `<legend>` is the field label, with one labelled input
    per enabled sub-input bound to `formData.{name}.{key}`. Each input carries
    its sub-input's `autocomplete` token and its own error; a field-level
    summary follows when any part is invalid. Sub-labels sit below or above
    their input, or are visually hidden. A `left` label placement shows the
    legend on top, since a legend cannot sit beside its group. A `select`
    sub-input renders its options; a `wide` one spans the row. Each input
    carries `data-forms-part="{key}"` and the fieldset
    `data-forms-composite="{type}"`, so address autocomplete providers can
    find them; an address field also renders the `ap.forms.addressAutocomplete`
    filter's HTML after the fieldset.

    Variables:
    - $field     The FormField.
    - $value     The value, a map of sub-input keys.
    - $error     The field-level validation error, or null.
    - $subErrors The errors by sub-input key.
--}}
@props(['field', 'value' => [], 'error' => null, 'subErrors' => []])

@php
    $subInputs = $field->getEnabledSubInputs();
    $requiredKeys = $field->getRequiredSubInputKeys();
    $subLabelPlacement = $field->getSubLabelPlacement();
    $descriptionPlacement = $field->getDescriptionPlacement();
    $subErrors = array_intersect_key($subErrors ?? [], $subInputs);
    $hasErrors = filled($error) || [] !== $subErrors;
    $describedBy = trim(implode(' ', array_filter([
        filled($field->help_text) ? 'hint-' . $field->name : null,
        $hasErrors ? 'error-' . $field->name : null,
    ])));
@endphp

<fieldset
    class="fieldset {{ $field->css_classes }}"
    id="field-{{ $field->name }}"
    data-forms-composite="{{ $field->type }}"
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
>
    @if(filled($field->label))
        <legend @class(['fieldset-legend', 'sr-only' => 'hidden' === $field->getLabelPlacement()])>
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    @if(filled($field->help_text) && 'above' === $descriptionPlacement)
        <p class="fieldset-label" id="hint-{{ $field->name }}">{{ $field->help_text }}</p>
    @endif

    <div @class(['grid grid-cols-1 gap-3', 'sm:grid-cols-2' => count($subInputs) > 1])>
        @foreach($subInputs as $key => $subInput)
            @php
                $inputId = 'field-' . $field->name . '-' . $key;
                $subError = $subErrors[$key] ?? null;
                $isRequired = in_array($key, $requiredKeys, true);
            @endphp
            <div @class(['flex flex-col gap-1', 'sm:col-span-2' => $subInput['wide'] && count($subInputs) > 1]) wire:key="sub-input-{{ $field->uuid }}-{{ $key }}">
                @if('below' !== $subLabelPlacement)
                    <label for="{{ $inputId }}" @class(['label text-sm', 'sr-only' => 'hidden' === $subLabelPlacement])>
                        {{ $subInput['label'] }}
                        @if($isRequired)
                            <span class="text-error" aria-hidden="true">*</span>
                        @endif
                    </label>
                @endif

                @if('select' === $subInput['type'])
                    <select
                        id="{{ $inputId }}"
                        name="{{ $field->name }}[{{ $key }}]"
                        data-forms-part="{{ $key }}"
                        wire:model.live="formData.{{ $field->name }}.{{ $key }}"
                        @class(['select select-bordered w-full', 'select-error' => filled($subError)])
                        @if(filled($subInput['autocomplete'])) autocomplete="{{ $subInput['autocomplete'] }}" @endif
                        @if($isRequired) required aria-required="true" @endif
                        @if(filled($subError)) aria-invalid="true" aria-describedby="error-{{ $field->name }}-{{ $key }}" @endif
                    >
                        <option value="">{{ filled($subInput['placeholder']) ? $subInput['placeholder'] : __( 'Select…' ) }}</option>
                        @foreach($subInput['options'] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                @else
                    <input
                        type="{{ $subInput['type'] }}"
                        id="{{ $inputId }}"
                        name="{{ $field->name }}[{{ $key }}]"
                        data-forms-part="{{ $key }}"
                        wire:model.live="formData.{{ $field->name }}.{{ $key }}"
                        @class(['input input-bordered w-full', 'input-error' => filled($subError)])
                        @if(filled($subInput['placeholder'])) placeholder="{{ $subInput['placeholder'] }}" @endif
                        @if(filled($subInput['autocomplete'])) autocomplete="{{ $subInput['autocomplete'] }}" @endif
                        @if($isRequired) required aria-required="true" @endif
                        @if(filled($subError)) aria-invalid="true" aria-describedby="error-{{ $field->name }}-{{ $key }}" @endif
                    />
                @endif

                @if('below' === $subLabelPlacement)
                    <label for="{{ $inputId }}" class="fieldset-label text-xs">
                        {{ $subInput['label'] }}
                        @if($isRequired)
                            <span class="text-error" aria-hidden="true">*</span>
                        @endif
                    </label>
                @endif

                @if(filled($subError))
                    <p class="text-xs text-error" id="error-{{ $field->name }}-{{ $key }}">{{ $subError }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="hint-{{ $field->name }}">{{ $field->help_text }}</p>
    @endif

    @if($hasErrors)
        <p class="fieldset-label text-error" id="error-{{ $field->name }}" role="alert">
            {{ filled($error) ? $error : trans_choice( 'Please fix :count highlighted part of :field.|Please fix :count highlighted parts of :field.', count($subErrors), ['count' => count($subErrors), 'field' => $field->label] ) }}
        </p>
    @endif
</fieldset>

@if('address' === $field->type)
    {!! applyFilters( 'ap.forms.addressAutocomplete', '', $field ) !!}
@endif
