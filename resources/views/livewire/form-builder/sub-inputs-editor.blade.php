{{-- A composite field's sub-inputs: turn each on or off, relabel it, set its
     placeholder, default value and population parameter, and whether a
     required field requires it (see CompositeFieldType::updateSubInputConfig()). --}}
@php
    $subInputs = $field->getSubInputs();
    $enabledCount = count($field->getEnabledSubInputs());
@endphp

<div class="form-control" wire:key="sub-inputs-{{ $field->uuid }}">
    <span class="label">
        <span class="label-text text-xs" id="sub-inputs-label-{{ $field->uuid }}">{{ __( 'Inputs' ) }}</span>
    </span>

    <div class="overflow-x-auto">
        <table class="table table-xs" aria-labelledby="sub-inputs-label-{{ $field->uuid }}" aria-describedby="sub-inputs-hint-{{ $field->uuid }}">
            <thead>
                <tr>
                    <th scope="col">{{ __( 'Show' ) }}</th>
                    <th scope="col">{{ __( 'Sub-Label' ) }}</th>
                    <th scope="col">{{ __( 'Placeholder' ) }}</th>
                    <th scope="col">{{ __( 'Default' ) }}</th>
                    <th scope="col">{{ __( 'Parameter' ) }}</th>
                    <th scope="col">{{ __( 'Required' ) }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subInputs as $key => $subInput)
                    <tr wire:key="sub-input-row-{{ $field->uuid }}-{{ $key }}">
                        <td>
                            <input
                                type="checkbox"
                                class="checkbox checkbox-xs checkbox-primary"
                                aria-label="{{ __( 'Show :input', ['input' => $subInput['default_label']] ) }}"
                                @checked($subInput['enabled'])
                                @disabled($subInput['enabled'] && 1 === $enabledCount)
                                wire:change="updateSubInput(@js($key), 'enabled', $event.target.checked)"
                            />
                        </td>
                        <td>
                            <input
                                type="text"
                                class="input input-xs input-bordered w-full"
                                aria-label="{{ __( ':input sub-label', ['input' => $subInput['default_label']] ) }}"
                                placeholder="{{ $subInput['default_label'] }}"
                                value="{{ $subInput['label'] === $subInput['default_label'] ? '' : $subInput['label'] }}"
                                maxlength="255"
                                wire:change="updateSubInput(@js($key), 'label', $event.target.value)"
                            />
                        </td>
                        <td>
                            <input
                                type="text"
                                class="input input-xs input-bordered w-full"
                                aria-label="{{ __( ':input placeholder', ['input' => $subInput['default_label']] ) }}"
                                value="{{ $subInput['placeholder'] }}"
                                maxlength="255"
                                wire:change="updateSubInput(@js($key), 'placeholder', $event.target.value)"
                            />
                        </td>
                        <td>
                            @if ('select' === $subInput['type'])
                                <select
                                    class="select select-xs select-bordered w-full"
                                    aria-label="{{ __( ':input default value', ['input' => $subInput['default_label']] ) }}"
                                    wire:change="updateSubInput(@js($key), 'default', $event.target.value)"
                                >
                                    <option value="">{{ __( 'None' ) }}</option>
                                    @foreach ($subInput['options'] as $option)
                                        <option value="{{ $option['value'] }}" @selected($subInput['default'] === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    type="text"
                                    class="input input-xs input-bordered w-full"
                                    aria-label="{{ __( ':input default value', ['input' => $subInput['default_label']] ) }}"
                                    value="{{ $subInput['default'] }}"
                                    maxlength="255"
                                    wire:change="updateSubInput(@js($key), 'default', $event.target.value)"
                                />
                            @endif
                        </td>
                        <td>
                            <input
                                type="text"
                                class="input input-xs input-bordered w-full font-mono"
                                aria-label="{{ __( ':input population parameter', ['input' => $subInput['default_label']] ) }}"
                                value="{{ $subInput['parameter'] }}"
                                maxlength="100"
                                wire:change="updateSubInput(@js($key), 'parameter', $event.target.value)"
                            />
                        </td>
                        <td>
                            <input
                                type="checkbox"
                                class="checkbox checkbox-xs checkbox-primary"
                                aria-label="{{ __( 'Require :input', ['input' => $subInput['default_label']] ) }}"
                                @checked($subInput['required'])
                                wire:change="updateSubInput(@js($key), 'required', $event.target.checked)"
                            />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <span class="label" id="sub-inputs-hint-{{ $field->uuid }}">
        <span class="label-text-alt text-xs text-base-content/50">
            {{ __( 'Required inputs apply when the field is required. With none checked, every shown input is required.' ) }}
            {{ __( 'A parameter fills the input from the renderer prefill or the query string.' ) }}
        </span>
    </span>
</div>
