<div class="textarea-stub">
    @if($label)
        <label class="label">{{ $label }}</label>
    @endif
    @if($error)
        <span class="error">{{ $error }}</span>
    @endif
    @if($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    <textarea name="{{ $name }}" placeholder="{{ $placeholder }}" class="textarea textarea-bordered" rows="{{ $rows }}" {{ $required ? 'required' : '' }} {{ $attributes }}>{{ $slot }}</textarea>
</div>
