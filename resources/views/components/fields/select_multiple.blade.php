@props(['field', 'value' => [], 'error' => null])

@php
	$options = $field->getRenderOptions();
	// The input component renders a top label; the layout renders the others.
	$layoutLabel = 'top' !== $field->getLabelPlacement();
@endphp

@if($field->isSearchable())
	@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
		@include('forms::components.combobox', ['field' => $field, 'error' => $error, 'options' => $options, 'multiple' => true])
	@endcomponent
@else
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'renderLabel' => $layoutLabel, 'focusInput' => true, 'renderError' => false])
<x-artisanpack-select
	:label="$layoutLabel ? null : $field->label"
	:placeholder="$field->placeholder ?? __( 'Select options...' )"
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
	:aria-labelledby="$layoutLabel && filled($field->label) ? 'label-' . $field->name : null"
	:aria-describedby="$field->getDescribedBy($error)"
/>
@endcomponent
@endif
