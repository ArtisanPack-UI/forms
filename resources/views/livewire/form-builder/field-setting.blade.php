{{-- One type-specific setting from a field type's settings schema
     (see ArtisanPackUI\Forms\Support\FieldSettings). --}}
@php
    $settingKey = $setting['key'];
    $settingId = 'field-setting-' . $settingKey;
    $settingValue = \ArtisanPackUI\Forms\Support\FieldSettings::inputValue($field, $setting);
    $hintId = filled($setting['hint'] ?? null) ? $settingId . '-hint' : null;
    $inputType = match ($setting['input']) {
        'integer', 'number' => 'number',
        'date' => 'date',
        'time' => 'time',
        default => 'text',
    };
@endphp

<div class="form-control" wire:key="setting-{{ $field->uuid }}-{{ $settingKey }}">
    @if ('toggle' === $setting['input'])
        <label class="label cursor-pointer justify-start gap-3" for="{{ $settingId }}">
            <input
                type="checkbox"
                id="{{ $settingId }}"
                class="checkbox checkbox-sm checkbox-primary"
                @checked($settingValue)
                @if ($hintId) aria-describedby="{{ $hintId }}" @endif
                wire:change="updateSetting(@js($settingKey), $event.target.checked)"
            />
            <span class="label-text text-xs">{{ $setting['label'] }}</span>
        </label>
    @else
        <label class="label" for="{{ $settingId }}">
            <span class="label-text text-xs">{{ $setting['label'] }}</span>
        </label>

        @if ('select' === $setting['input'])
            <select
                id="{{ $settingId }}"
                class="select select-sm select-bordered w-full"
                @if ($hintId) aria-describedby="{{ $hintId }}" @endif
                wire:change="updateSetting(@js($settingKey), $event.target.value)"
            >
                @foreach ($setting['options'] as $option)
                    <option value="{{ $option['value'] }}" @selected($settingValue === $option['value'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        @elseif ('formula' === $setting['input'])
            {{-- A calculation formula (since 1.6.0), with a picker that inserts a field's merge tag at the cursor --}}
            @php
                $formulaFields = \ArtisanPackUI\Forms\FieldTypes\CalculationFieldType::formulaFields($field, $field->form?->fields()->orderBy('sort_order')->get() ?? []);
                $formulaProblem = filled($settingValue) ? \ArtisanPackUI\Forms\Support\Calculation::check($settingValue, array_column($formulaFields, 'name')) : null;
                $formulaDescribedBy = trim(implode(' ', array_filter([$hintId, $formulaProblem ? $settingId . '-problem' : null])));
            @endphp
            <div
                x-data="{
                    insert(tag) {
                        const input = $refs.formula;
                        const start = input.selectionStart ?? input.value.length;
                        const end = input.selectionEnd ?? start;

                        input.value = input.value.slice(0, start) + tag + input.value.slice(end);
                        input.focus();
                        input.setSelectionRange(start + tag.length, start + tag.length);
                        input.dispatchEvent(new Event('change'));
                    },
                }"
            >
                <textarea
                    id="{{ $settingId }}"
                    x-ref="formula"
                    class="textarea textarea-sm textarea-bordered w-full font-mono text-xs @if ($formulaProblem) textarea-error @endif"
                    rows="3"
                    spellcheck="false"
                    @if (isset($setting['max_length'])) maxlength="{{ $setting['max_length'] }}" @endif
                    @if (filled($setting['placeholder'] ?? null)) placeholder="{{ $setting['placeholder'] }}" @endif
                    @if ($formulaProblem) aria-invalid="true" @endif
                    @if ($formulaDescribedBy) aria-describedby="{{ $formulaDescribedBy }}" @endif
                    wire:change="updateSetting(@js($settingKey), $event.target.value)"
                >{{ $settingValue }}</textarea>

                @if ([] !== $formulaFields)
                    <label class="sr-only" for="{{ $settingId }}-insert">{{ __( 'Insert a field' ) }}</label>
                    <select
                        id="{{ $settingId }}-insert"
                        class="select select-sm select-bordered mt-2 w-full"
                        x-on:change="if ($event.target.value !== '') { insert($event.target.value); $event.target.value = ''; }"
                    >
                        <option value="">{{ __( 'Insert a field…' ) }}</option>
                        @foreach ($formulaFields as $formulaField)
                            <option value="{{ '{' . $formulaField['name'] . '}' }}">{{ $formulaField['label'] }} ({{ '{' . $formulaField['name'] . '}' }})</option>
                        @endforeach
                    </select>
                @endif

                @if ($formulaProblem)
                    <p id="{{ $settingId }}-problem" class="mt-1 text-xs text-error">{{ $formulaProblem }}</p>
                @endif
            </div>
        @elseif ('textarea' === $setting['input'])
            <textarea
                id="{{ $settingId }}"
                class="textarea textarea-sm textarea-bordered w-full"
                rows="4"
                @if (isset($setting['max_length'])) maxlength="{{ $setting['max_length'] }}" @endif
                @if (filled($setting['placeholder'] ?? null)) placeholder="{{ $setting['placeholder'] }}" @endif
                @if ($hintId) aria-describedby="{{ $hintId }}" @endif
                wire:change="updateSetting(@js($settingKey), $event.target.value)"
            >{{ $settingValue }}</textarea>
        @else
            <input
                type="{{ $inputType }}"
                id="{{ $settingId }}"
                class="input input-sm input-bordered w-full @if ($setting['monospace'] ?? false) font-mono text-xs @endif"
                value="{{ $settingValue }}"
                @if (filled($setting['placeholder'] ?? null)) placeholder="{{ $setting['placeholder'] }}" @endif
                @if ('number' === $inputType && isset($setting['min'])) min="{{ $setting['min'] }}" @endif
                @if ('number' === $inputType && isset($setting['max'])) max="{{ $setting['max'] }}" @endif
                @if ('number' === $setting['input']) step="{{ $setting['step'] ?? 'any' }}" @endif
                @if ($hintId) aria-describedby="{{ $hintId }}" @endif
                wire:change="updateSetting(@js($settingKey), $event.target.value)"
            />
        @endif
    @endif

    @if ($hintId)
        <label class="label" id="{{ $hintId }}">
            <span class="label-text-alt text-xs text-base-content/50">{{ $setting['hint'] }}</span>
        </label>
    @endif
</div>
