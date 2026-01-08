<form action="{{ $action }}" method="{{ $method }}" {{ $noValidate ? 'novalidate' : '' }} {{ $attributes }}>
    @csrf
    {{ $slot }}
</form>
