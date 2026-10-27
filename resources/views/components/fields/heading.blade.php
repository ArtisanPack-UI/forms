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

    $alignClass = match($field->getHeadingAlignment()) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => '',
    };
@endphp

<{{ $tag }}
    class="{{ $classes }} {{ $alignClass }} {{ $field->css_classes }}"
    id="{{ $field->domId('heading') }}"
>
    {{ $field->getLayoutContent() }}
</{{ $tag }}>
