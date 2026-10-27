<div class="textarea-stub">
    @if($label)
        <legend class="fieldset-legend">{{ $label }}</legend>
    @endif
    @if($error && ! $omitError)
        <span class="error">{{ $error }}</span>
    @endif
    @if($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    <textarea id="artisanpack-stub-{{ $id }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="textarea textarea-bordered" rows="{{ $rows }}" {{ $required ? 'required' : '' }} {{ $attributes }}>{{ $slot }}</textarea>
</div>
