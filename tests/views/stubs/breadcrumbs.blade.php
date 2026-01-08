<nav class="breadcrumbs-stub" {{ $attributes }}>
    <ul>
        @foreach($items as $item)
            <li>
                @if(isset($item['link']))
                    <a href="{{ $item['link'] }}">{{ $item['label'] ?? $item['name'] ?? '' }}</a>
                @else
                    <span>{{ $item['label'] ?? $item['name'] ?? '' }}</span>
                @endif
            </li>
        @endforeach
        {{ $slot }}
    </ul>
</nav>
