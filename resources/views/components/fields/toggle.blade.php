@props(['field', 'value' => false, 'error' => null])

@php
    $size = $field->getConfig('size', 'md');
    $color = $field->getConfig('color', 'primary');

    // Size classes for the toggle
    $sizeClass = match($size) {
        'xs' => 'toggle-xs',
        'sm' => 'toggle-sm',
        'lg' => 'toggle-lg',
        default => '',
    };

    // Color classes for the toggle
    $colorClass = match($color) {
        'primary' => 'toggle-primary',
        'secondary' => 'toggle-secondary',
        'accent' => 'toggle-accent',
        'success' => 'toggle-success',
        'warning' => 'toggle-warning',
        'error' => 'toggle-error',
        'info' => 'toggle-info',
        default => 'toggle-primary',
    };
@endphp

{{-- The label sits beside the box, so the layout only places the description; a hidden label stays for screen readers. --}}
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => false])
<label class="label cursor-pointer justify-start gap-3">
    <input
        type="checkbox"
        wire:model.live="formData.{{ $field->name }}"
        id="{{ $field->domId() }}"
        class="toggle {{ $sizeClass }} {{ $colorClass }} {{ $field->css_classes }}"
        role="switch"
        @if($field->is_required) required aria-required="true" @endif
        @if($field->isReadOnly()) disabled aria-readonly="true" @endif
        @if(filled($error)) aria-invalid="true" @endif
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
