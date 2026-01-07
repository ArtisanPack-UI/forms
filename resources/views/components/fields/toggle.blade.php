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

    // Build aria-describedby with both hint and error IDs when applicable
    $describedBy = [];
    if ($error) {
        $describedBy[] = 'error-' . $field->name;
    }
    if ($field->help_text) {
        $describedBy[] = 'hint-' . $field->name;
    }
    $ariaDescribedBy = !empty($describedBy) ? implode(' ', $describedBy) : null;
@endphp

<div class="form-control">
    <label class="label cursor-pointer justify-start gap-3">
        <input
            type="checkbox"
            wire:model.live="formData.{{ $field->name }}"
            id="field-{{ $field->name }}"
            class="toggle {{ $sizeClass }} {{ $colorClass }} {{ $field->css_classes }}"
            role="switch"
            @if($field->is_required) required aria-required="true" @endif
            @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
        />
        <span class="label-text">
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error">*</span>
            @endif
        </span>
    </label>

    @if($field->help_text)
        <label class="label" id="hint-{{ $field->name }}">
            <span class="label-text-alt">{{ $field->help_text }}</span>
        </label>
    @endif

    @if($error)
        <label class="label" id="error-{{ $field->name }}">
            <span class="label-text-alt text-error">{{ $error }}</span>
        </label>
    @endif
</div>
