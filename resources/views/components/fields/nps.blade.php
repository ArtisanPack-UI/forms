{{--
    A Net Promoter Score question (since 1.6.0): a radio group of the scores
    0 to 10, shown as a row of buttons, with the low and high labels under
    the ends (see NpsFieldType). Each native radio is visually hidden inside
    its button, so arrow keys still move between scores and the focus ring
    shows on the button. The 0 and 10 radios are named with their end labels
    ("0, Not likely"), and the group is described by both labels.

    Variables:
    - $field The FormField.
    - $value The score.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $npsType = \ArtisanPackUI\Forms\FieldTypes\NpsFieldType::class;
    $labels = $npsType::endLabels($field);
    $readOnly = $field->isReadOnly();
    $selected = is_numeric($value) ? (string) (int) $value : null;
    $endsId = $field->domId() . '-ends';
    $describedBy = trim(implode(' ', array_filter([$endsId, $field->getDescribedBy($error)])));
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
<div class="flex flex-col gap-1 {{ $field->css_classes }}">
    <div
        role="radiogroup"
        class="grid grid-cols-6 gap-1 sm:grid-cols-11"
        @if($field->label) aria-labelledby="{{ $field->domId('label') }}" @endif
        @if($field->is_required) aria-required="true" @endif
        @if($readOnly) aria-readonly="true" @endif
        @if(filled($error)) aria-invalid="true" @endif
        aria-describedby="{{ $describedBy }}"
    >
        @for($score = $npsType::MIN_SCORE; $score <= $npsType::MAX_SCORE; $score++)
            @php
                $endLabel = match ($score) {
                    $npsType::MIN_SCORE => $labels['low'],
                    $npsType::MAX_SCORE => $labels['high'],
                    default => null,
                };
            @endphp
            <label class="cursor-pointer">
                <input
                    type="radio"
                    wire:model.live="formData.{{ $field->name }}"
                    name="{{ $field->name }}"
                    value="{{ $score }}"
                    id="{{ $field->domId() }}-{{ $score }}"
                    class="peer sr-only"
                    @checked((string) $score === $selected)
                    @disabled($readOnly)
                />
                <span class="btn btn-sm btn-outline w-full tabular-nums peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-content peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary peer-disabled:cursor-not-allowed">
                    {{ $score }}@if(null !== $endLabel)<span class="sr-only">, {{ $endLabel }}</span>@endif
                </span>
            </label>
        @endfor
    </div>

    <div class="flex justify-between gap-4 text-xs text-base-content/70" id="{{ $endsId }}">
        <span>{{ __( ':score = :label', ['score' => $npsType::MIN_SCORE, 'label' => $labels['low']] ) }}</span>
        <span class="text-right">{{ __( ':score = :label', ['score' => $npsType::MAX_SCORE, 'label' => $labels['high']] ) }}</span>
    </div>
</div>
@endcomponent
