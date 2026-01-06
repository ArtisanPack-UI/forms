@props(['field', 'value' => ''])

<input
    type="hidden"
    wire:model="formData.{{ $field->name }}"
    id="field-{{ $field->name }}"
    name="{{ $field->name }}"
/>
