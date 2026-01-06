@props(['field'])

@php
    $content = $field->getConfig('content', '');
    $hasKses = function_exists('kses');

    // Sanitize HTML content using ArtisanPackUI Security's kses() function
    // This properly cleanses both tags and attributes to prevent XSS
    // If kses is not available, we'll escape and render as plain text
    $sanitizedHtml = $hasKses ? kses($content) : null;
    $escapedText = !$hasKses ? e($content) : null;
@endphp

<div
    class="html-content prose prose-sm max-w-none {{ $field->css_classes }}"
    id="html-{{ $field->name }}"
>
    @if($hasKses)
        {!! $sanitizedHtml !!}
    @else
        {{ $escapedText }}
    @endif
</div>
