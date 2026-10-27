@props(['field', 'value' => [], 'error' => null])

@php
	$options = $field->getRenderOptions();
@endphp

@if($field->isSearchable())
	@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => $field->domId()])
		@include('forms::components.combobox', ['field' => $field, 'error' => $error, 'options' => $options, 'multiple' => true])
	@endcomponent
@else
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'focusInput' => true])
<x-artisanpack-select
	:label="null"
	:placeholder="$field->placeholder ?? __( 'Select options...' )"
	:omit-error="true"
	:aria-invalid="filled($error) ? 'true' : null"
	:required="$field->is_required"
	wire:model.live="formData.{{ $field->name }}"
	id="{{ $field->domId() }}"
	:class="$field->css_classes"
	:multiple="true"
	:options="$options"
	option-value="value"
	option-label="label"
	:aria-required="$field->is_required ? 'true' : 'false'"
	:aria-labelledby="filled($field->label) ? $field->domId('label') : null"
	:aria-label="filled($field->label) ? null : \Illuminate\Support\Str::headline($field->name)"
	:aria-describedby="$field->getDescribedBy($error)"
/>
@endcomponent
@endif
