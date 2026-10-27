<div class="select-stub">
    @if($label)
        <legend class="fieldset-legend">{{ $label }}</legend>
    @endif
    @if($error && ! $omitError)
        <span class="error">{{ $error }}</span>
    @endif
    @if($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    <select id="artisanpack-stub-{{ $id }}" name="{{ $name }}" class="select select-bordered" {{ $required ? 'required' : '' }} {{ $attributes }}>
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @if($options)
            @foreach($options as $option)
                <option value="{{ $option[$optionValue] ?? '' }}">{{ $option[$optionLabel] ?? '' }}</option>
            @endforeach
        @endif
        {{ $slot }}
    </select>
</div>
