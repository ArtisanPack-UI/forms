{{--
    A list (repeater) field (since 1.6.0): rows of the field's columns that
    visitors add and remove, in a `<fieldset>` whose `<legend>` is the field
    label (see ListFieldType).

    Each row is a group named for its row ("Attendee 2"), with one labelled
    input per column bound to `formData.{name}.{row}.{column}`. The column
    labels show as a header row from the `sm` breakpoint up, where the inputs'
    own labels are kept for screen readers only. The add and remove buttons
    call FormRenderer::addListRow() and removeListRow(); after a row is added
    its first input takes focus, after one is removed the add button does,
    and a polite live region announces each change.

    Variables:
    - $field The FormField.
    - $value The rows.
    - $error The field-level validation error, or null.
--}}
@props(['field', 'value' => [], 'error' => null])

@php
    $listType = \ArtisanPackUI\Forms\FieldTypes\ListFieldType::class;
    $columns = $listType::columns($field);
    $rows = is_array($value) && [] !== $value ? array_values(array_filter($value, 'is_array')) : $listType::initialRows($field);
    $maxRows = $listType::maxRows($field);
    $rowLabel = $listType::rowLabel($field);
    $descriptionPlacement = $field->getDescriptionPlacement();
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $cellErrorCount = collect($errorBag->keys())->filter(fn ($key) => str_starts_with((string) $key, 'formData.' . $field->name . '.'))->count();
    $hasErrors = filled($error) || $cellErrorCount > 0;
    $describedBy = trim(implode(' ', array_filter([
        filled($field->help_text) ? $field->domId('hint') : null,
        $hasErrors ? $field->domId('error') : null,
    ])));
    $gridStyle = 'grid-template-columns: repeat(' . max(1, count($columns)) . ', minmax(0, 1fr)) auto';
    $firstColumn = $columns[0]['key'] ?? null;
@endphp

<fieldset
    class="fieldset {{ $field->css_classes }}"
    id="{{ $field->domId() }}"
    data-forms-list="{{ $field->name }}"
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    x-data="{
        announcement: '',
        messages: @js([
            'added' => __( ':row :number added.' ),
            'removed' => __( ':row :number removed.' ),
        ]),
        say(message, number) {
            this.announcement = message.replace(':row', @js($rowLabel)).replace(':number', number);
        },
    }"
    x-on:forms-list-row-added.window="if ($event.detail.field === @js($field->name)) { say(messages.added, $event.detail.index + 1); $nextTick(() => document.getElementById(@js($field->domId() . '-') + $event.detail.index + @js('-' . $firstColumn))?.focus()); }"
    x-on:forms-list-row-removed.window="if ($event.detail.field === @js($field->name)) { say(messages.removed, $event.detail.index + 1); $nextTick(() => $refs.add?.focus()); }"
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

    <div class="hidden gap-2 sm:grid" style="{{ $gridStyle }}" aria-hidden="true">
        @foreach($columns as $column)
            <span class="label text-sm">{{ $column['label'] }}</span>
        @endforeach
        <span class="w-8"></span>
    </div>

    <div class="flex flex-col gap-3 sm:gap-2">
        @foreach($rows as $index => $row)
            @php
                $rowId = $field->domId() . '-row-' . $index;
            @endphp
            <div
                role="group"
                aria-labelledby="{{ $rowId }}-label"
                class="flex flex-col gap-2 rounded-box bg-base-200 p-3 sm:grid sm:items-start sm:bg-transparent sm:p-0"
                style="{{ $gridStyle }}"
                wire:key="list-{{ $field->uuid }}-row-{{ $index }}"
            >
                <span id="{{ $rowId }}-label" class="sr-only">{{ $rowLabel }} {{ $index + 1 }}</span>

                @foreach($columns as $column)
                    @php
                        $inputId = $field->domId() . '-' . $index . '-' . $column['key'];
                        $cellError = $errorBag->first('formData.' . $field->name . '.' . $index . '.' . $column['key']);
                    @endphp
                    <div class="flex flex-col gap-1">
                        <label for="{{ $inputId }}" class="label text-sm sm:sr-only">{{ $column['label'] }}</label>
                        @if('select' === $column['type'])
                            <select
                                id="{{ $inputId }}"
                                wire:model="formData.{{ $field->name }}.{{ $index }}.{{ $column['key'] }}"
                                @class(['select select-bordered w-full', 'select-error' => filled($cellError)])
                                @if(filled($cellError)) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                            >
                                <option value="">{{ __( 'Select…' ) }}</option>
                                @foreach($column['options'] as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                type="{{ $column['type'] }}"
                                id="{{ $inputId }}"
                                wire:model="formData.{{ $field->name }}.{{ $index }}.{{ $column['key'] }}"
                                @class(['input input-bordered w-full', 'input-error' => filled($cellError)])
                                @if('number' !== $column['type']) maxlength="{{ \ArtisanPackUI\Forms\FieldTypes\ListFieldType::CELL_MAX_LENGTH }}" @endif
                                @if(filled($cellError)) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
                            />
                        @endif
                        @if(filled($cellError))
                            <p class="text-xs text-error" id="{{ $inputId }}-error">{{ $cellError }}</p>
                        @endif
                    </div>
                @endforeach

                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square self-end sm:self-start"
                    wire:click="removeListRow(@js($field->name), {{ $index }})"
                    aria-label="{{ __( 'Remove :row :number', ['row' => $rowLabel, 'number' => $index + 1] ) }}"
                >
                    <x-artisanpack-icon name="o-x-mark" class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        @endforeach
    </div>

    <div class="mt-2 flex items-center gap-3">
        <button
            type="button"
            x-ref="add"
            class="btn btn-sm btn-outline"
            wire:click="addListRow(@js($field->name))"
            @disabled(count($rows) >= $maxRows)
        >
            <x-artisanpack-icon name="o-plus" class="h-4 w-4" aria-hidden="true" />
            {{ __( 'Add :row', ['row' => $rowLabel] ) }}
        </button>
        @if(count($rows) >= $maxRows)
            <span class="text-xs text-base-content/60">{{ trans_choice( 'Up to :count row.|Up to :count rows.', $maxRows, ['count' => $maxRows] ) }}</span>
        @endif
    </div>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    @if($hasErrors)
        <p class="fieldset-label text-error" id="{{ $field->domId('error') }}" role="alert">
            {{ filled($error) ? $error : trans_choice( 'Please fix :count highlighted entry in :field.|Please fix :count highlighted entries in :field.', $cellErrorCount, ['count' => $cellErrorCount, 'field' => $field->label] ) }}
        </p>
    @endif

    <div wire:ignore class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></div>
</fieldset>
