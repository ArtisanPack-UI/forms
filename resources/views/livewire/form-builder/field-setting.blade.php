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
