<div class="input-stub">
    @if($label)
        <label class="label">{{ $label }}</label>
    @endif
    @if($error)
        <span class="error">{{ $error }}</span>
    @endif
    @if($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="input input-bordered" {{ $required ? 'required' : '' }} {{ $attributes }} />
</div>
