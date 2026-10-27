<div class="input-stub">
    @if($label)
        <label class="label">{{ $label }}</label>
    @endif
    @if($error && ! $omitError)
        <span class="error">{{ $error }}</span>
    @endif
    @if($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    @if($prepend)
        {{ $prepend }}
    @endif
    @if($prefix)
        <span class="label">{{ $prefix }}</span>
    @endif
    <input id="artisanpack-stub-{{ $id }}" type="{{ $type }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="input input-bordered" {{ $required ? 'required' : '' }} {{ $attributes }} />
    @if($suffix)
        <span class="label">{{ $suffix }}</span>
    @endif
</div>
