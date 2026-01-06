@props(['field'])

@php
    $level = $field->getConfig('level', 'h3');
    $validLevels = ['h2', 'h3', 'h4', 'h5', 'h6'];
    $tag = in_array($level, $validLevels) ? $level : 'h3';

    $classes = match($tag) {
        'h2' => 'text-2xl font-bold',
        'h3' => 'text-xl font-semibold',
        'h4' => 'text-lg font-semibold',
        'h5' => 'text-base font-medium',
        'h6' => 'text-sm font-medium',
        default => 'text-xl font-semibold',
    };
@endphp

<{{ $tag }}
    class="{{ $classes }} {{ $field->css_classes }}"
    id="heading-{{ $field->name }}"
>
    {{ $field->label }}
</{{ $tag }}>
