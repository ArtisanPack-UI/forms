@props(['field', 'error' => null])

@php
    $maxSize = $field->getFileMaxSize();
    $allowedTypes = $field->getFileAllowedTypes();
    $accept = !empty($allowedTypes) ? implode(',', array_map(fn($type) => '.' . $type, $allowedTypes)) : null;
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
<input
    type="file"
    wire:model="files.{{ $field->name }}"
    id="field-{{ $field->name }}"
    class="file-input file-input-bordered w-full {{ $field->css_classes }} @error('files.' . $field->name) file-input-error @enderror"
    @if($accept) accept="{{ $accept }}" @endif
    @if($field->is_required) required aria-required="true" @endif
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>

<div wire:loading wire:target="files.{{ $field->name }}" class="mt-2">
    <span class="loading loading-spinner loading-sm"></span>
    <span class="text-sm text-base-content/70">{{ __( 'Uploading...' ) }}</span>
</div>

@if($maxSize)
    <label class="label">
        <span class="label-text-alt text-base-content/50">
            {{ __( 'Maximum file size: :size KB', ['size' => $maxSize] ) }}
        </span>
    </label>
@endif
@endcomponent
