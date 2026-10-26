{{--
    A Likert / matrix field (since 1.6.0; see MatrixFieldType).

    As a grid: a table with the scale's columns as column headers and each
    statement as a row header, in a `<fieldset>` whose `<legend>` is the
    field label. Each row's radios share a name, so arrow keys move along the
    row, and each radio is named by its row and column headers ("The service
    was fast Agree") with `aria-labelledby`. A row's error is shown under its
    statement and describes its radios. The table scrolls sideways on narrow
    screens.

    As a single scale: one radio group labelled by the field label, with the
    choices in a row.

    Variables:
    - $field The FormField.
    - $value A map of row keys to column values, or the chosen value of a single scale.
    - $error The field-level validation error, or null.
--}}
@props(['field', 'value' => [], 'error' => null])

@php
    $matrixType = \ArtisanPackUI\Forms\FieldTypes\MatrixFieldType::class;
    $columns = $matrixType::columns($field);
    $rows = $matrixType::rows($field);
    $readOnly = $field->isReadOnly();
    $descriptionPlacement = $field->getDescriptionPlacement();
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $rowErrorCount = collect($errorBag->keys())->filter(fn ($key) => str_starts_with((string) $key, 'formData.' . $field->name . '.'))->count();
    $hasErrors = filled($error) || $rowErrorCount > 0;
    $describedBy = trim(implode(' ', array_filter([
        filled($field->help_text) ? 'hint-' . $field->name : null,
        $hasErrors ? 'error-' . $field->name : null,
    ])));
@endphp

@if([] === $rows)
    @component('forms::components.field-layout', ['field' => $field, 'error' => $error])
    <div
        role="radiogroup"
        class="flex flex-wrap gap-x-6 gap-y-2 {{ $field->css_classes }}"
        @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
        @if($field->is_required) aria-required="true" @endif
        @if($readOnly) aria-readonly="true" @endif
        @if(filled($error)) aria-invalid="true" @endif
        @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
    >
        @foreach($columns as $index => $column)
            <label class="label cursor-pointer justify-start gap-2">
                <input
                    type="radio"
                    wire:model.live="formData.{{ $field->name }}"
                    name="{{ $field->name }}"
                    value="{{ $column['value'] }}"
                    id="field-{{ $field->name }}-{{ $index }}"
                    class="radio radio-primary"
                    @disabled($readOnly)
                />
                <span class="label-text">{{ $column['label'] }}</span>
            </label>
        @endforeach
    </div>
    @endcomponent
@else
<fieldset
    class="fieldset {{ $field->css_classes }}"
    id="field-{{ $field->name }}"
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
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
        <p class="fieldset-label" id="hint-{{ $field->name }}">{{ $field->help_text }}</p>
    @endif

    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <td><span class="sr-only">{{ __( 'Statement' ) }}</span></td>
                    @foreach($columns as $index => $column)
                        <th scope="col" id="field-{{ $field->name }}-col-{{ $index }}" class="text-center align-bottom font-medium">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $rowId = 'field-' . $field->name . '-row-' . $row['key'];
                        $rowError = $errorBag->first('formData.' . $field->name . '.' . $row['key']);
                        $answer = is_array($value) ? ($value[$row['key']] ?? '') : '';
                    @endphp
                    <tr wire:key="matrix-{{ $field->uuid }}-{{ $row['key'] }}">
                        <th scope="row" class="min-w-40 font-normal">
                            <span id="{{ $rowId }}">{{ $row['label'] }}</span>
                            @if(filled($rowError))
                                <span class="block text-xs text-error" id="{{ $rowId }}-error">{{ $rowError }}</span>
                            @endif
                        </th>
                        @foreach($columns as $index => $column)
                            <td class="text-center">
                                <input
                                    type="radio"
                                    wire:model.live="formData.{{ $field->name }}.{{ $row['key'] }}"
                                    name="{{ $field->name }}[{{ $row['key'] }}]"
                                    value="{{ $column['value'] }}"
                                    id="{{ $rowId }}-{{ $index }}"
                                    class="radio radio-primary"
                                    aria-labelledby="{{ $rowId }} field-{{ $field->name }}-col-{{ $index }}"
                                    @if(filled($rowError)) aria-invalid="true" aria-describedby="{{ $rowId }}-error" @endif
                                    @checked((string) $answer === $column['value'])
                                    @disabled($readOnly)
                                />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="hint-{{ $field->name }}">{{ $field->help_text }}</p>
    @endif

    @if($hasErrors)
        <p class="fieldset-label text-error" id="error-{{ $field->name }}" role="alert">
            {{ filled($error) ? $error : trans_choice( 'Please answer :count highlighted row in :field.|Please answer :count highlighted rows in :field.', $rowErrorCount, ['count' => $rowErrorCount, 'field' => $field->label] ) }}
        </p>
    @endif
</fieldset>
@endif
