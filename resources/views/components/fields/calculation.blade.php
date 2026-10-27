{{--
    A calculation (since 1.6.0): a read-only input showing the result of the
    field's formula, worked out on the server from the other values whenever
    one changes (see CalculationFieldType and FormRenderer::evaluateConditionalLogic()).
    The input stays focusable and labelled, so screen readers can read the
    result; it is not bound to `formData`, since the client's value is never
    used.

    Variables:
    - $field The FormField.
    - $value The result.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $result = is_scalar($value) ? (string) $value : '';
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => $field->domId()])
<input
    type="text"
    id="{{ $field->domId() }}"
    name="{{ $field->name }}"
    value="{{ $result }}"
    readonly
    aria-readonly="true"
    inputmode="decimal"
    data-forms-calculation="{{ $field->name }}"
    class="input input-bordered w-full bg-base-200 font-medium tabular-nums {{ $field->css_classes }}"
    @if(blank($field->label)) aria-label="{{ $field->name }}" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>
@endcomponent
