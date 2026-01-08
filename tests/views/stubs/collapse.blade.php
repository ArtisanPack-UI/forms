<div class="collapse-stub" {{ $attributes }}>
    @if($title)
        <div class="collapse-header">{{ $title }}</div>
    @endif
    <div class="collapse-content" style="{{ $open ? '' : 'display: none;' }}">
        {{ $slot }}
    </div>
</div>
