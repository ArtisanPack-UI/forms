@props(['field'])

@php
    $text = $field->help_text ?? $field->label;
@endphp

<div
    class="prose prose-sm max-w-none {{ $field->css_classes }}"
    id="paragraph-{{ $field->name }}"
>
    {!! nl2br(e($text)) !!}
</div>
