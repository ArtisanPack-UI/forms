{{-- Mirrors the real artisanpack-form component, which does not inject @csrf or @method. --}}
<form action="{{ $action }}" method="{{ $method }}" {{ $noValidate ? 'novalidate' : '' }} {{ $attributes }}>
    {{ $slot }}
</form>
