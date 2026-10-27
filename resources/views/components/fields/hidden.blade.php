@props(['field', 'value' => ''])

<input
    type="hidden"
    wire:model="formData.{{ $field->name }}"
    id="{{ $field->domId() }}"
    name="{{ $field->name }}"
/>
