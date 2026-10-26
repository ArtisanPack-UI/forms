{{--
    A color field (since 1.6.0): the browser's color picker joined to a text
    input holding the hex code (see ColorFieldType). The picker shows the
    chosen color as its swatch, and the text input is labelled by the field
    label, so the color can be typed as well as picked. Both keep the value
    entangled with `formData.{name}`; the server normalizes it (`F80` becomes
    `#ff8800`) and checks it with `hex_color`.

    `x-artisanpack-colorpicker` is not used because its color input has no
    accessible name and it renders its own label and errors.

    Variables:
    - $field The FormField.
    - $value The value.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $colorType = \ArtisanPackUI\Forms\FieldTypes\ColorFieldType::class;
    $current = is_string($value) ? $value : '';
    $readOnly = $field->isReadOnly();
    $inputId = 'field-' . $field->name;
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => $inputId])
<div
    class="join w-full"
    x-data="{
        value: $wire.entangle(@js('formData.' . $field->name)).live,
        text: @js($current),
        init() {
            this.$watch('value', (value) => { this.text = value ?? ''; });
        },
        get picker() {
            let color = String(this.text ?? '').trim().toLowerCase();

            color = color.startsWith('#') ? color : '#' + color;

            if (/^#[0-9a-f]{3,4}$/.test(color)) {
                color = '#' + [...color.slice(1)].map((digit) => digit + digit).join('');
            }

            return /^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/.test(color) ? color.slice(0, 7) : '#000000';
        },
    }"
>
    <input
        type="color"
        id="{{ $inputId }}-picker"
        @class(['input join-item h-auto w-14 shrink-0 cursor-pointer p-1', 'input-error' => filled($error)])
        value="{{ $colorType::pickerValue($current) }}"
        x-bind:value="picker"
        x-on:input="text = $event.target.value"
        x-on:change="value = $event.target.value"
        aria-label="{{ __( ':field color picker', ['field' => $field->label ?: $field->name] ) }}"
        @if($readOnly) disabled @endif
    />
    <input
        type="text"
        id="{{ $inputId }}"
        name="{{ $field->name }}"
        x-model="text"
        x-on:change="value = text"
        value="{{ $current }}"
        placeholder="{{ $field->placeholder ?: '#rrggbb' }}"
        maxlength="9"
        spellcheck="false"
        autocomplete="off"
        @class(['input input-bordered join-item w-full font-mono', 'input-error' => filled($error), $field->css_classes])
        @if(blank($field->label)) aria-label="{{ $field->name }}" @endif
        @if($field->is_required) required aria-required="true" @endif
        @if($readOnly) readonly aria-readonly="true" @endif
        @if(filled($error)) aria-invalid="true" @endif
        @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
    />
</div>
@endcomponent
