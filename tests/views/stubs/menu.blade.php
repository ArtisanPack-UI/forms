<nav class="menu-stub" {{ $attributes }}>
    @if($title)
        <div class="menu-title">
            @if($icon)
                <span class="icon">{{ $icon }}</span>
            @endif
            {{ $title }}
        </div>
    @endif
    <ul class="menu-list">
        {{ $slot }}
    </ul>
</nav>
