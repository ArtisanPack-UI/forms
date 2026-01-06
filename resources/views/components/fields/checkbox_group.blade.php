@props(['field', 'value' => [], 'error' => null])

@php
    $options = $field->getConfig('options', []);

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
        <label class="label" id="label-{{ $field->name }}">
            <span class="label-text">
                {{ $field->label }}
                @if($field->is_required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    <div
        class="flex flex-col gap-2 {{ $field->css_classes }}"
        role="group"
        @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
        @if($field->is_required) aria-required="true" @endif
        @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
    >
        @foreach($options as $index => $option)
            @php
                $optionValue = $option['value'] ?? $option;
                $optionLabel = $option['label'] ?? $option;
            @endphp
            <label class="label cursor-pointer justify-start gap-3">
                <input
                    type="checkbox"
                    wire:model.live="formData.{{ $field->name }}"
                    value="{{ $optionValue }}"
                    class="checkbox checkbox-primary"
                    id="field-{{ $field->name }}-{{ $index }}"
                />
                <span class="label-text">{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>

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
