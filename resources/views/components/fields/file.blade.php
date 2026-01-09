@props(['field', 'error' => null])

@php
    $maxSize = $field->getValidationRule('max_size');
    $allowedTypes = $field->getValidationRule('allowed_types', []);
    $multiple = $field->getConfig('multiple', false);
    $accept = !empty($allowedTypes) ? implode(',', array_map(fn($type) => '.' . $type, $allowedTypes)) : null;

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
        type="file"
        wire:model="files.{{ $field->name }}"
        id="field-{{ $field->name }}"
        class="file-input file-input-bordered w-full {{ $field->css_classes }} @error('files.' . $field->name) file-input-error @enderror"
        @if($accept) accept="{{ $accept }}" @endif
        @if($multiple) multiple @endif
        @if($field->is_required) required aria-required="true" @endif
        @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
    />

    <div wire:loading wire:target="files.{{ $field->name }}" class="mt-2">
        <span class="loading loading-spinner loading-sm"></span>
        <span class="text-sm text-base-content/70">{{ __( 'Uploading...' ) }}</span>
    </div>

    @if($field->help_text)
        <label class="label" id="hint-{{ $field->name }}">
            <span class="label-text-alt">{{ $field->help_text }}</span>
        </label>
    @endif

    @if($maxSize)
        <label class="label">
            <span class="label-text-alt text-base-content/50">
                {{ __( 'Maximum file size: :size KB', ['size' => $maxSize] ) }}
            </span>
        </label>
    @endif

    @if($error)
        <label class="label" id="error-{{ $field->name }}">
            <span class="label-text-alt text-error">{{ $error }}</span>
        </label>
    @endif
</div>
