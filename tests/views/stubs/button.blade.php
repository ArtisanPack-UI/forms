<button type="{{ $type }}" class="btn btn-stub{{ $color ? ' btn-'.$color : '' }}{{ $size ? ' btn-'.$size : '' }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes }}>
    @if($icon)
        <span class="icon">{{ $icon }}</span>
    @endif
    {{ $slot }}
</button>
