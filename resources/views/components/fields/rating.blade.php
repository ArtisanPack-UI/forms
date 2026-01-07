@props(['field', 'value' => '', 'error' => null])

@php
    $maxRating = $field->getConfig('max_rating', 5);
    $allowHalf = $field->getConfig('allow_half', false);
    $size = $field->getConfig('size', 'md');

    // Size classes for the rating
    $sizeClasses = match($size) {
        'xs' => 'rating-xs',
        'sm' => 'rating-sm',
        'lg' => 'rating-lg',
        default => '',
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
        class="rating {{ $sizeClasses }} {{ $allowHalf ? 'rating-half' : '' }} {{ $field->css_classes }}"
        role="radiogroup"
        @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
        @if($field->is_required) aria-required="true" @endif
        @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
    >
        {{-- Hidden "no rating" option for clearing --}}
        <input
            type="radio"
            wire:model.live="formData.{{ $field->name }}"
            name="{{ $field->name }}"
            value="0"
            class="rating-hidden"
            aria-label="No rating"
            @if(empty($value) || $value == 0) checked @endif
        />

        @if($allowHalf)
            @for($i = 1; $i <= $maxRating; $i++)
                {{-- Half star (left half) --}}
                <input
                    type="radio"
                    wire:model.live="formData.{{ $field->name }}"
                    name="{{ $field->name }}"
                    value="{{ $i - 0.5 }}"
                    class="mask mask-star-2 mask-half-1 bg-warning"
                    id="field-{{ $field->name }}-{{ $i }}-half"
                    aria-label="{{ $i - 0.5 }} stars"
                />
                {{-- Full star (right half) --}}
                <input
                    type="radio"
                    wire:model.live="formData.{{ $field->name }}"
                    name="{{ $field->name }}"
                    value="{{ $i }}"
                    class="mask mask-star-2 mask-half-2 bg-warning"
                    id="field-{{ $field->name }}-{{ $i }}"
                    aria-label="{{ $i }} stars"
                />
            @endfor
        @else
            @for($i = 1; $i <= $maxRating; $i++)
                <input
                    type="radio"
                    wire:model.live="formData.{{ $field->name }}"
                    name="{{ $field->name }}"
                    value="{{ $i }}"
                    class="mask mask-star-2 bg-warning"
                    id="field-{{ $field->name }}-{{ $i }}"
                    aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                />
            @endfor
        @endif
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
