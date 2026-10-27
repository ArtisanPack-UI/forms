{{--
    A date input: the native date picker, or (since 1.6.0) separate month, day
    and year dropdowns or fields grouped in a fieldset whose legend is the
    field label. The parts are ordered by the field's date format and combined
    into the `Y-m-d` value entangled with `formData.{name}`; an incomplete or
    impossible date (such as 31 February) or a short year (`90`, which is not
    padded) is left for the `date` and `date_format:Y-m-d` rules to reject. The native `min` and `max` and the year dropdown follow the
    field's resolved limits and age limits (see FormField::getDateBounds()).
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $bounds = $field->getDateBounds();
    $inputMode = $field->getDateInputMode();
@endphp

@if('picker' === $inputMode)
{{-- A plain input rather than <x-artisanpack-input>: that component nests the
     input inside a `.input` wrapper, where daisyUI's `input[type=date]` rule
     makes it inline-block and pins the date text to the top of the box. --}}
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => $field->domId()])
<input
    type="date"
    wire:model.live="formData.{{ $field->name }}"
    id="{{ $field->domId() }}"
    class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
    @if(filled($bounds['min'])) min="{{ $bounds['min'] }}" @endif
    @if(filled($bounds['max'])) max="{{ $bounds['max'] }}" @endif
    @if($field->getAutocomplete()) autocomplete="{{ $field->getAutocomplete() }}" @endif
    @if($field->isReadOnly()) readonly @endif
    @if($field->is_required) required aria-required="true" @endif
    @if($error) aria-invalid="true" @endif
    @unless(filled($field->label)) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>
@endcomponent
@else
@php
    $readOnly = $field->isReadOnly();
    $autocomplete = $field->getAutocomplete();
    $order = \ArtisanPackUI\Forms\Support\DateTimeFormat::DATE_PART_ORDER[$field->getDateFormat()];
    $descriptionPlacement = $field->getDescriptionPlacement();
    $describedBy = $field->getDescribedBy($error);
    $years = \ArtisanPackUI\Forms\Support\DateTimeFormat::yearOptions($bounds['min'], $bounds['max']);
    $parts = [
        'month' => ['label' => __( 'Month' ), 'placeholder' => __( 'MM' ), 'length' => 2, 'options' => \ArtisanPackUI\Forms\Support\DateTimeFormat::monthNames()],
        'day' => ['label' => __( 'Day' ), 'placeholder' => __( 'DD' ), 'length' => 2, 'options' => array_combine(range(1, 31), range(1, 31))],
        'year' => ['label' => __( 'Year' ), 'placeholder' => __( 'YYYY' ), 'length' => 4, 'options' => [] === $years ? [] : array_combine($years, $years)],
    ];
@endphp
<fieldset
    class="fieldset {{ $field->css_classes }}"
    id="{{ $field->domId() }}"
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    x-data="{
        value: $wire.entangle(@js('formData.' . $field->name)).live,
        padded: @js('fields' === $inputMode),
        year: '',
        month: '',
        day: '',
        init() {
            this.split();
            this.$watch('value', () => this.split());
        },
        split() {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(this.value ?? '');

            if (match) {
                [this.year, this.month, this.day] = this.padded
                    ? [match[1], match[2], match[3]]
                    : [match[1], String(Number(match[2])), String(Number(match[3]))];
            } else if (! this.value) {
                [this.year, this.month, this.day] = ['', '', ''];
            }
        },
        combine() {
            const [year, month, day] = [String(this.year).trim(), String(this.month).trim(), String(this.day).trim()];

            this.value = year === '' && month === '' && day === ''
                ? ''
                : `${year}-${month === '' ? '' : month.padStart(2, '0')}-${day === '' ? '' : day.padStart(2, '0')}`;
        },
    }"
>
    @if(filled($field->label))
        <legend @class(['fieldset-legend', 'sr-only' => 'hidden' === $field->getLabelPlacement()])>
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    @if(filled($field->help_text) && 'above' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    <div class="flex flex-wrap gap-3">
        @foreach($order as $part)
            @php
                $partId = $field->domId() . '-' . $part;
                $partAutocomplete = 'bday' === $autocomplete ? 'bday-' . $part : null;
            @endphp
            <div @class(['flex flex-col gap-1', 'grow basis-40' => 'month' === $part && 'dropdowns' === $inputMode, 'w-24' => 'month' !== $part || 'fields' === $inputMode])>
                <label for="{{ $partId }}" class="label text-sm">{{ $parts[$part]['label'] }}</label>
                @if('dropdowns' === $inputMode)
                    <select
                        id="{{ $partId }}"
                        x-model="{{ $part }}"
                        x-on:change="combine()"
                        @class(['select select-bordered w-full', 'select-error' => filled($error)])
                        @if($partAutocomplete) autocomplete="{{ $partAutocomplete }}" @endif
                        @if($readOnly) disabled @endif
                        @if($field->is_required) required aria-required="true" @endif
                        @if(filled($error)) aria-invalid="true" @endif
                    >
                        <option value="">{{ $parts[$part]['label'] }}</option>
                        @foreach($parts[$part]['options'] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                @else
                    <input
                        type="text"
                        inputmode="numeric"
                        id="{{ $partId }}"
                        x-model="{{ $part }}"
                        x-on:change="combine()"
                        @class(['input input-bordered w-full', 'input-error' => filled($error)])
                        maxlength="{{ $parts[$part]['length'] }}"
                        placeholder="{{ $parts[$part]['placeholder'] }}"
                        @if($partAutocomplete) autocomplete="{{ $partAutocomplete }}" @endif
                        @if($readOnly) readonly @endif
                        @if($field->is_required) required aria-required="true" @endif
                        @if(filled($error)) aria-invalid="true" @endif
                    />
                @endif
            </div>
        @endforeach
    </div>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    @if(filled($error))
        <p class="fieldset-label text-error" id="{{ $field->domId('error') }}">{{ $error }}</p>
    @endif
</fieldset>
@endif
