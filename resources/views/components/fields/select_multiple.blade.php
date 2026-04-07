@props(['field', 'value' => [], 'error' => null])

@php
	$options = $field->getConfig('options', []);
@endphp

<x-artisanpack-select
	:label="$field->label"
	:placeholder="$field->placeholder ?? __( 'Select options...' )"
	:hint="$field->help_text"
	:error="$error"
	:required="$field->is_required"
	wire:model.live="formData.{{ $field->name }}"
	id="field-{{ $field->name }}"
	:class="$field->css_classes"
	:multiple="true"
	:options="$options"
	option-value="value"
	option-label="label"
	:aria-required="$field->is_required ? 'true' : 'false'"
	:aria-describedby="$error ? 'error-' . $field->name : ($field->help_text ? 'hint-' . $field->name : null)"
/>
