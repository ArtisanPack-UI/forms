<li class="menu-item-stub {{ $active ? 'active' : '' }}" {{ $attributes }}>
    <a href="{{ $link ?? '#' }}">
        @if($icon)
            <span class="icon">{{ $icon }}</span>
        @endif
        {{ $title ?? $slot }}
    </a>
</li>
