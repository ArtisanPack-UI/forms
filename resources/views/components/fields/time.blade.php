{{--
    A time input: the native time picker, or (since 1.6.0) hour and minute
    dropdowns grouped in a fieldset whose legend is the field label, with an
    AM/PM dropdown on a 12-hour clock. The dropdowns are combined into the
    24-hour `H:i` value entangled with `formData.{name}`; minutes follow the
    field's step (see DateTimeFormat::minuteOptions()).
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $minTime = $field->getValidationRule('min_time');
    $maxTime = $field->getValidationRule('max_time');
    $step = $field->getConfig('step', 60);
@endphp

@if('picker' === $field->getTimeInputMode())
@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => $field->domId()])
<input
    type="time"
    wire:model.live="formData.{{ $field->name }}"
    id="{{ $field->domId() }}"
    class="input input-bordered w-full {{ $field->css_classes }} @if($error) input-error @endif"
    @if($minTime) min="{{ $minTime }}" @endif
    @if($maxTime) max="{{ $maxTime }}" @endif
    @if($step) step="{{ $step }}" @endif
    @if($field->isReadOnly()) readonly @endif
    @if($field->is_required) required aria-required="true" @endif
    @if($error) aria-invalid="true" @endif
    @unless(filled($field->label)) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>
@endcomponent
@else
@php
    $twelveHour = '12' === $field->getTimeFormat();
    $readOnly = $field->isReadOnly();
    $descriptionPlacement = $field->getDescriptionPlacement();
    $describedBy = $field->getDescribedBy($error);
    $hours = $twelveHour ? range(1, 12) : range(0, 23);
    $minutes = \ArtisanPackUI\Forms\Support\DateTimeFormat::minuteOptions(is_numeric($step) ? (int) $step : null);
    $selects = [
        'hour' => ['label' => __( 'Hour' ), 'options' => array_combine($hours, array_map(fn (int $hour): string => $twelveHour ? (string) $hour : str_pad((string) $hour, 2, '0', STR_PAD_LEFT), $hours))],
        'minute' => ['label' => __( 'Minute' ), 'options' => array_combine($minutes, array_map(fn (int $minute): string => str_pad((string) $minute, 2, '0', STR_PAD_LEFT), $minutes))],
    ];

    if ($twelveHour) {
        $selects['period'] = ['label' => __( 'AM or PM' ), 'options' => ['am' => __( 'AM' ), 'pm' => __( 'PM' )]];
    }
@endphp
<fieldset
    class="fieldset {{ $field->css_classes }}"
    id="{{ $field->domId() }}"
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    x-data="{
        value: $wire.entangle(@js('formData.' . $field->name)).live,
        twelveHour: @js($twelveHour),
        hour: '',
        minute: '',
        period: 'am',
        init() {
            this.split();
            this.$watch('value', () => this.split());
        },
        split() {
            const match = /^(\d{2}):(\d{2})(?::\d{2})?$/.exec(this.value ?? '');

            if (match) {
                const hour = Number(match[1]);

                this.minute = String(Number(match[2]));
                this.period = hour < 12 ? 'am' : 'pm';
                this.hour = String(this.twelveHour ? (hour % 12 === 0 ? 12 : hour % 12) : hour);
            } else if (! this.value) {
                [this.hour, this.minute] = ['', ''];
            }
        },
        combine() {
            if (this.hour === '' && this.minute === '') {
                this.value = '';

                return;
            }

            let hour = this.hour === '' ? '' : Number(this.hour);

            if (hour !== '' && this.twelveHour) {
                hour = (hour % 12) + (this.period === 'pm' ? 12 : 0);
            }

            this.value = `${hour === '' ? '' : String(hour).padStart(2, '0')}:${this.minute === '' ? '' : String(this.minute).padStart(2, '0')}`;
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
        @foreach($selects as $part => $select)
            @php($partId = $field->domId() . '-' . $part)
            <div class="flex w-24 flex-col gap-1">
                <label for="{{ $partId }}" class="label text-sm">{{ $select['label'] }}</label>
                <select
                    id="{{ $partId }}"
                    x-model="{{ $part }}"
                    x-on:change="combine()"
                    @class(['select select-bordered w-full', 'select-error' => filled($error)])
                    @if($readOnly) disabled @endif
                    @if($field->is_required && 'period' !== $part) required aria-required="true" @endif
                    @if(filled($error)) aria-invalid="true" @endif
                >
                    @if('period' !== $part)
                        <option value="">{{ $select['label'] }}</option>
                    @endif
                    @foreach($select['options'] as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    @if(filled($error))
        <p class="fieldset-label text-error" id="{{ $field->domId('error') }}" role="alert">{{ $error }}</p>
    @endif
</fieldset>
@endif
