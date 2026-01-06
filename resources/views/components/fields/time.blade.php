@props(['field', 'value' => '', 'error' => null])

@php
    $minTime = $field->getValidationRule('min_time');
    $maxTime = $field->getValidationRule('max_time');
    $step = $field->getConfig('step', 60);

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
    @if($field->label)
        <label class="label" for="field-{{ $field->name }}">
            <span class="label-text">
                {{ $field->label }}
                @if($field->is_required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    <input
        type="time"
        wire:model.live="formData.{{ $field->name }}"
        id="field-{{ $field->name }}"
        class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
        @if($minTime) min="{{ $minTime }}" @endif
        @if($maxTime) max="{{ $maxTime }}" @endif
        @if($step) step="{{ $step }}" @endif
        @if($field->is_required) required aria-required="true" @endif
        @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
    />

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
