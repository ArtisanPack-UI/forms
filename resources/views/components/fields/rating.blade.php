@props(['field', 'value' => '', 'error' => null])

@php
    $maxRating = \ArtisanPackUI\Forms\FieldTypes\RatingFieldType::maxRating($field);
    $allowHalf = \ArtisanPackUI\Forms\FieldTypes\RatingFieldType::allowsHalf($field);
    $size = $field->getConfig('size', 'md');
    $readOnly = $field->isReadOnly();

    // Native radios keep arrow-key navigation; the ring shows which star has focus.
    $focusClasses = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

    // Size classes for the rating
    $sizeClasses = match($size) {
        'xs' => 'rating-xs',
        'sm' => 'rating-sm',
        'lg' => 'rating-lg',
        default => '',
    };
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
<div
    class="rating {{ $sizeClasses }} {{ $allowHalf ? 'rating-half' : '' }} {{ $field->css_classes }}"
    role="radiogroup"
    @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
    @if($field->is_required) aria-required="true" @endif
    @if($readOnly) aria-readonly="true" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
>
    {{-- Hidden "no rating" option for clearing --}}
    <input
        type="radio"
        wire:model.live="formData.{{ $field->name }}"
        name="{{ $field->name }}"
        value="0"
        class="rating-hidden"
        aria-label="{{ __( 'No rating' ) }}"
        @disabled($readOnly)
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
                class="mask mask-star-2 mask-half-1 bg-warning {{ $focusClasses }}"
                @disabled($readOnly)
                id="field-{{ $field->name }}-{{ $i }}-half"
                aria-label="{{ trans_choice( ':count star|:count stars', $i - 0.5, ['count' => $i - 0.5] ) }}"
            />
            {{-- Full star (right half) --}}
            <input
                type="radio"
                wire:model.live="formData.{{ $field->name }}"
                name="{{ $field->name }}"
                value="{{ $i }}"
                class="mask mask-star-2 mask-half-2 bg-warning {{ $focusClasses }}"
                @disabled($readOnly)
                id="field-{{ $field->name }}-{{ $i }}"
                aria-label="{{ trans_choice( ':count star|:count stars', $i, ['count' => $i] ) }}"
            />
        @endfor
    @else
        @for($i = 1; $i <= $maxRating; $i++)
            <input
                type="radio"
                wire:model.live="formData.{{ $field->name }}"
                name="{{ $field->name }}"
                value="{{ $i }}"
                class="mask mask-star-2 bg-warning {{ $focusClasses }}"
                @disabled($readOnly)
                id="field-{{ $field->name }}-{{ $i }}"
                aria-label="{{ trans_choice( ':count star|:count stars', $i, ['count' => $i] ) }}"
            />
        @endfor
    @endif
</div>
@endcomponent
