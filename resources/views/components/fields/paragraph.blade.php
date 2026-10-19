@props(['field'])

@php
    $text = $field->getLayoutContent();
@endphp

<div
    class="prose prose-sm max-w-none {{ $field->css_classes }}"
    id="paragraph-{{ $field->name }}"
>
    {!! nl2br(e($text)) !!}
</div>
