<div class="dropdown-stub" {{ $attributes }}>
    <button class="dropdown-trigger">
        @if($icon)
            <span class="icon">{{ $icon }}</span>
        @endif
        {{ $label ?? $slot }}
    </button>
    <div class="dropdown-content">
        {{ $slot }}
    </div>
</div>
