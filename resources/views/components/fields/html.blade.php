@props(['field'])

@php
    // Admin-authored HTML is sanitized with htmLawed's safe mode (scripts,
    // event handlers and javascript: URLs removed), or escaped when
    // artisanpack-ui/security is not installed.
    $sanitizedHtml = \ArtisanPackUI\Forms\Models\FormField::sanitizeHtml($field->getLayoutContent());
@endphp

<div
    class="html-content prose prose-sm max-w-none {{ $field->css_classes }}"
    id="html-{{ $field->name }}"
>
    {!! $sanitizedHtml !!}
</div>
