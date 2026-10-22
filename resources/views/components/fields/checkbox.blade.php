@props(['field', 'value' => false, 'error' => null])

{{-- The label sits beside the box, so the layout only places the description; a hidden label stays for screen readers. --}}
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => false])
<label class="label cursor-pointer justify-start gap-3">
    <input
        type="checkbox"
        wire:model.live="formData.{{ $field->name }}"
        id="field-{{ $field->name }}"
        class="checkbox checkbox-primary {{ $field->css_classes }}"
        @if($field->is_required) required aria-required="true" @endif
        @if($field->isReadOnly()) disabled aria-readonly="true" @endif
        @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
    />
    <span @class(['label-text', 'sr-only' => 'hidden' === $field->getLabelPlacement()])>
        {{ $field->label }}
        @if($field->is_required)
            <span class="text-error">*</span>
        @endif
    </span>
</label>
@endcomponent
