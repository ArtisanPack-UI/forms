@props(['field', 'value' => '', 'error' => null])

@php
    $minDate = $field->getValidationRule('min_date');
    $maxDate = $field->getValidationRule('max_date');

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

{{-- A plain input rather than <x-artisanpack-input>: that component nests the
     input inside a `.input` wrapper, where daisyUI's `input[type=date]` rule
     makes it inline-block and pins the date text to the top of the box. --}}
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
        type="date"
        wire:model.live="formData.{{ $field->name }}"
        id="field-{{ $field->name }}"
        class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
        @if(filled($minDate)) min="{{ $minDate }}" @endif
        @if(filled($maxDate)) max="{{ $maxDate }}" @endif
        @if($field->getAutocomplete()) autocomplete="{{ $field->getAutocomplete() }}" @endif
        @if($field->isReadOnly()) readonly @endif
        @if($field->is_required) required aria-required="true" @endif
        @if($error) aria-invalid="true" @endif
        @unless($field->label) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
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
