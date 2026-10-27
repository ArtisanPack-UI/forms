{{--
    A CAPTCHA challenge (see CaptchaFieldType).

    The provider's widget renders into a `wire:ignore` container through the
    `formsCaptcha` Alpine component (defined with the form renderer's
    scripts), which loads the provider's script, binds the token to
    `formData.{name}` without a request, and resets the widget when the
    component dispatches `forms-captcha-reset`. Invisible modes and reCAPTCHA
    v3 run the check when the form is submitted. Nothing renders while the
    provider has no keys.

    Variables:
    - $field The FormField.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $widget = \ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType::widget($field);
@endphp

@if(null !== $widget)
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => false])
<div
    id="{{ $field->domId() }}"
    role="group"
    @if(filled($field->label)) aria-labelledby="{{ $field->domId('label') }}" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
    data-forms-captcha="{{ $widget['provider'] }}"
>
    @if(filled($field->label))
        <p @class(['label-text mb-2', 'sr-only' => 'hidden' === $field->getLabelPlacement() || 'invisible' === $widget['size']]) id="{{ $field->domId('label') }}">{{ $field->label }}</p>
    @endif

    <div
        wire:ignore
        x-data="formsCaptcha(@js(array_merge($widget, ['model' => 'formData.' . $field->name, 'formId' => $field->form_id])))"
    >
        <div x-ref="widget"></div>
        <p x-show="failed" x-cloak class="text-sm text-error" role="alert">{{ __( 'The verification check could not be loaded. Please reload the page and try again.' ) }}</p>
    </div>
</div>
@endcomponent
@endif
