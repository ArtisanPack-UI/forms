{{--
    Places a field's label, description and error around its input (the slot).

    - label: `top`, `left` (beside the input from `md` up) or `hidden` (`sr-only`,
      still announced). See FormField::getLabelPlacement().
    - description: `above` or `below` the input, always with the `hint-{name}` ID
      the input's `aria-describedby` points at.

    Variables:
    - $field       The FormField.
    - $error       The validation error, or null.
    - $renderLabel Whether this layout renders the label. False when the input
                   component renders it (top placement) or the label sits beside
                   a single checkbox or toggle.
    - $labelFor    The input's ID for `<label for>`, or null when the input
                   references the label with `aria-labelledby` instead.
    - $focusInput  Whether clicking a label without `for` focuses the input
                   (for inputs whose ID the component generates; not groups).
    - $renderError Whether this layout renders the error. False when the input
                   component renders it.
--}}
@php
    $renderLabel = ( $renderLabel ?? true ) && filled( $field->label );
    $labelFor = $labelFor ?? null;
    $focusInput = $focusInput ?? false;
    $renderError = $renderError ?? true;
    $labelPlacement = $field->getLabelPlacement();
    $descriptionPlacement = $field->getDescriptionPlacement();
    $isLeft = $renderLabel && 'left' === $labelPlacement;
@endphp

<div @class(['form-control', 'md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:items-start md:gap-x-4' => $isLeft])>
    @if($renderLabel)
        <label
            id="{{ $field->domId('label') }}"
            @class(['label', 'sr-only' => 'hidden' === $labelPlacement, 'md:pt-3' => $isLeft])
            @if($labelFor) for="{{ $labelFor }}" @elseif($focusInput) x-on:click="$el.nextElementSibling?.querySelector('input:not([type=hidden]), select, textarea')?.focus()" @endif
        >
            <span class="label-text">
                {{ $field->label }}
                @if($field->is_required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    <div @class(['md:col-start-2' => $isLeft])>
        @if(filled($field->help_text) && 'above' === $descriptionPlacement)
            <label class="label" id="{{ $field->domId('hint') }}">
                <span class="label-text-alt">{{ $field->help_text }}</span>
            </label>
        @endif

        {{ $slot }}

        @if(filled($field->help_text) && 'below' === $descriptionPlacement)
            <label class="label" id="{{ $field->domId('hint') }}">
                <span class="label-text-alt">{{ $field->help_text }}</span>
            </label>
        @endif

        @if($renderError && $error)
            <label class="label" id="{{ $field->domId('error') }}">
                <span class="label-text-alt text-error">{{ $error }}</span>
            </label>
        @endif
    </div>
</div>
