<div class="card-stub" {{ $attributes }}>
    @if($title)
        <div class="card-header">
            <h3>{{ $title }}</h3>
            @if($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
