@props(['field'])

@php
    $style = $field->getConfig('style', 'solid');
    $spacing = $field->getConfig('spacing', 'normal');

    $borderStyle = match($style) {
        'dashed' => 'border-dashed',
        'dotted' => 'border-dotted',
        default => 'border-solid',
    };

    $spacingClass = match($spacing) {
        'small' => 'my-2',
        'large' => 'my-8',
        default => 'my-4',
    };
@endphp

<div
    class="divider {{ $borderStyle }} {{ $spacingClass }} {{ $field->css_classes }}"
    role="separator"
    aria-hidden="true"
></div>
