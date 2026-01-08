<div class="tab-stub" data-name="{{ $name }}" {{ $attributes }}>
    @if($label)
        <div class="tab-label">
            @if($icon)
                <span class="icon">{{ $icon }}</span>
            @endif
            {{ $label }}
        </div>
    @endif
    <div class="tab-content">
        {{ $slot }}
    </div>
</div>
