<div class="field-editor rounded-box bg-base-200 p-4">
    @if ($this->selectedField)
        @php
            $field = $this->selectedField;
            $fieldConfig = \ArtisanPackUI\Forms\Config\FieldTypes::getTypeConfig($field->type);
            $hasOptions = \ArtisanPackUI\Forms\Config\FieldTypes::hasOptions($field->type);
            $validationOptions = \ArtisanPackUI\Forms\Config\FieldTypes::getValidationOptions($field->type);
        @endphp

        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">
                {{ __( 'Edit Field' ) }}
            </h3>
            <button
                type="button"
                wire:click="deselectField"
                class="btn btn-ghost btn-xs"
                title="{{ __( 'Close editor' ) }}"
                aria-label="{{ __( 'Close field editor' ) }}"
            >
                <x-artisanpack-icon name="o-x-mark" class="h-4 w-4" />
            </button>
        </div>

        {{-- Field type indicator --}}
        <div class="mb-4 flex items-center gap-2 text-sm text-base-content/60">
            <x-artisanpack-icon name="{{ $fieldConfig['icon'] ?? 'o-document' }}" class="h-4 w-4" />
            <span>{{ $fieldConfig['label'] ?? ucfirst($field->type) }}</span>
        </div>

        {{-- Tabbed editor --}}
        <div
            x-data="{ activeTab: 'general' }"
            class="space-y-4"
        >
            {{-- Tab buttons --}}
            <div class="tabs tabs-boxed bg-base-300">
                <button
                    type="button"
                    @click="activeTab = 'general'"
                    :class="activeTab === 'general' ? 'tab-active' : ''"
                    class="tab tab-sm"
                >
                    {{ __( 'General' ) }}
                </button>
                <button
                    type="button"
                    @click="activeTab = 'validation'"
                    :class="activeTab === 'validation' ? 'tab-active' : ''"
                    class="tab tab-sm"
                >
                    {{ __( 'Validation Settings' ) }}
                </button>
                @if ($hasOptions)
                    <button
                        type="button"
                        @click="activeTab = 'options'"
                        :class="activeTab === 'options' ? 'tab-active' : ''"
                        class="tab tab-sm"
                    >
                        {{ __( 'Options' ) }}
                    </button>
                @endif
                <button
                    type="button"
                    @click="activeTab = 'advanced'"
                    :class="activeTab === 'advanced' ? 'tab-active' : ''"
                    class="tab tab-sm"
                >
                    {{ __( 'Advanced' ) }}
                </button>
            </div>

            {{-- General tab --}}
            <div x-show="activeTab === 'general'" x-cloak class="space-y-3">
                {{-- Custom settings for a field type registered by another package.
                     The `ap.forms.fieldSettings` filter is given the field and the
                     form, so an integration can render its own controls — a service
                     picker, mappings to other fields on the form — that persist via
                     `updateField({ field_config: ... })`. The controls appear above
                     the standard label so a type whose configuration is its point
                     leads with that configuration. --}}
                @php
                    $customFieldSettings = applyFilters( 'ap.forms.fieldSettings', '', $field, $this->form );
                @endphp
                @if ( '' !== trim( $customFieldSettings ) )
                    <div class="field-custom-settings">
                        {!! $customFieldSettings !!}
                    </div>
                    <div class="divider text-xs text-base-content/50">{{ __( 'Field Basics' ) }}</div>
                @endif

                {{-- Label --}}
                <div class="form-control">
                    <label class="label" for="field-label">
                        <span class="label-text text-xs">{{ __( 'Label' ) }}</span>
                    </label>
                    <input
                        type="text"
                        id="field-label"
                        class="input input-sm input-bordered w-full"
                        value="{{ $field->label }}"
                        wire:change="updateField({ label: $event.target.value })"
                    />
                </div>

                {{-- Placeholder (if applicable) --}}
                @if (\ArtisanPackUI\Forms\Config\FieldTypes::supportsPlaceholder($field->type))
                    <div class="form-control">
                        <label class="label" for="field-placeholder">
                            <span class="label-text text-xs">{{ __( 'Placeholder' ) }}</span>
                        </label>
                        <input
                            type="text"
                            id="field-placeholder"
                            class="input input-sm input-bordered w-full"
                            value="{{ $field->placeholder }}"
                            wire:change="updateField({ placeholder: $event.target.value })"
                        />
                    </div>
                @endif

                {{-- Help text --}}
                <div class="form-control">
                    <label class="label" for="field-help-text">
                        <span class="label-text text-xs">{{ __( 'Help Text' ) }}</span>
                    </label>
                    <input
                        type="text"
                        id="field-help-text"
                        class="input input-sm input-bordered w-full"
                        value="{{ $field->help_text }}"
                        wire:change="updateField({ help_text: $event.target.value })"
                    />
                </div>

                {{-- Default value --}}
                @if (\ArtisanPackUI\Forms\Config\FieldTypes::supportsDefaultValue($field->type))
                    <div class="form-control">
                        <label class="label" for="field-default">
                            <span class="label-text text-xs">{{ __( 'Default Value' ) }}</span>
                        </label>
                        <input
                            type="text"
                            id="field-default"
                            class="input input-sm input-bordered w-full"
                            value="{{ $field->default_value }}"
                            wire:change="updateField({ default_value: $event.target.value })"
                        />
                    </div>
                @endif

                {{-- Width --}}
                <div class="form-control">
                    <label class="label" for="field-width">
                        <span class="label-text text-xs">{{ __( 'Width' ) }}</span>
                    </label>
                    <select
                        id="field-width"
                        class="select select-sm select-bordered w-full"
                        wire:change="updateField({ width: $event.target.value })"
                    >
                        @foreach ($this->widthOptions as $key => $option)
                            <option value="{{ $key }}" {{ $field->width === $key ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Validation tab --}}
            <div x-show="activeTab === 'validation'" x-cloak class="space-y-3">
                {{-- Required --}}
                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input
                            type="checkbox"
                            class="checkbox checkbox-sm checkbox-primary"
                            {{ $field->is_required ? 'checked' : '' }}
                            wire:change="updateField({ is_required: $event.target.checked })"
                        />
                        <span class="label-text">{{ __( 'Required field' ) }}</span>
                    </label>
                </div>

                {{-- Min/Max for text fields --}}
                @if (in_array('min', $validationOptions) || in_array('max', $validationOptions))
                    <div class="grid grid-cols-2 gap-2">
                        @if (in_array('min', $validationOptions))
                            <div class="form-control">
                                <label class="label" for="field-min">
                                    <span class="label-text text-xs">{{ __( $field->type === 'number' ? 'Min Value' : 'Min Length' ) }}</span>
                                </label>
                                <input
                                    type="number"
                                    id="field-min"
                                    class="input input-sm input-bordered w-full"
                                    value="{{ $field->getValidationRule('min') }}"
                                    wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, min: $event.target.value ? parseInt($event.target.value) : null } })"
                                />
                            </div>
                        @endif
                        @if (in_array('max', $validationOptions))
                            <div class="form-control">
                                <label class="label" for="field-max">
                                    <span class="label-text text-xs">{{ __( $field->type === 'number' ? 'Max Value' : 'Max Length' ) }}</span>
                                </label>
                                <input
                                    type="number"
                                    id="field-max"
                                    class="input input-sm input-bordered w-full"
                                    value="{{ $field->getValidationRule('max') }}"
                                    wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, max: $event.target.value ? parseInt($event.target.value) : null } })"
                                />
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Pattern for text fields --}}
                @if (in_array('pattern', $validationOptions))
                    <div class="form-control">
                        <label class="label" for="field-pattern">
                            <span class="label-text text-xs">{{ __( 'Pattern (Regex)' ) }}</span>
                        </label>
                        <input
                            type="text"
                            id="field-pattern"
                            class="input input-sm input-bordered w-full font-mono text-xs"
                            value="{{ $field->getValidationRule('pattern') }}"
                            placeholder="/^[a-z]+$/i"
                            wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, pattern: $event.target.value || null } })"
                        />
                    </div>
                @endif

                {{-- Step for number fields --}}
                @if (in_array('step', $validationOptions))
                    <div class="form-control">
                        <label class="label" for="field-step">
                            <span class="label-text text-xs">{{ __( 'Step Increment' ) }}</span>
                        </label>
                        <input
                            type="number"
                            id="field-step"
                            class="input input-sm input-bordered w-full"
                            value="{{ $field->getValidationRule('step') }}"
                            placeholder="1"
                            step="any"
                            wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, step: $event.target.value ? parseFloat($event.target.value) : null } })"
                        />
                        <label class="label">
                            <span class="label-text-alt text-xs text-base-content/50">{{ __( 'e.g., 0.01 for decimals, 1 for integers' ) }}</span>
                        </label>
                    </div>
                @endif

                {{-- Max size for file fields --}}
                @if (in_array('max_size', $validationOptions))
                    <div class="form-control">
                        <label class="label" for="field-max-size">
                            <span class="label-text text-xs">{{ __( 'Max File Size (KB)' ) }}</span>
                        </label>
                        <input
                            type="number"
                            id="field-max-size"
                            class="input input-sm input-bordered w-full"
                            value="{{ $field->getValidationRule('max_size') }}"
                            placeholder="5120"
                            min="1"
                            wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, max_size: $event.target.value ? parseInt($event.target.value) : null } })"
                        />
                        <label class="label">
                            <span class="label-text-alt text-xs text-base-content/50">{{ __( 'Default: 5120 KB (5 MB)' ) }}</span>
                        </label>
                    </div>
                @endif

                {{-- Allowed types for file fields --}}
                @if (in_array('allowed_types', $validationOptions))
                    <div class="form-control">
                        <label class="label" for="field-allowed-types">
                            <span class="label-text text-xs">{{ __( 'Allowed File Types' ) }}</span>
                        </label>
                        <input
                            type="text"
                            id="field-allowed-types"
                            class="input input-sm input-bordered w-full font-mono text-xs"
                            value="{{ is_array($field->getValidationRule('allowed_types')) ? implode(', ', $field->getValidationRule('allowed_types')) : $field->getValidationRule('allowed_types') }}"
                            placeholder="pdf, doc, docx, jpg, png"
                            wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, allowed_types: $event.target.value ? $event.target.value.split(',').map(t => t.trim()).filter(t => t) : null } })"
                        />
                        <label class="label">
                            <span class="label-text-alt text-xs text-base-content/50">{{ __( 'Comma-separated file extensions' ) }}</span>
                        </label>
                    </div>
                @endif

                {{-- Min/Max date for date fields --}}
                @if (in_array('min_date', $validationOptions) || in_array('max_date', $validationOptions))
                    <div class="grid grid-cols-2 gap-2">
                        @if (in_array('min_date', $validationOptions))
                            <div class="form-control">
                                <label class="label" for="field-min-date">
                                    <span class="label-text text-xs">{{ __( 'Min Date' ) }}</span>
                                </label>
                                <input
                                    type="date"
                                    id="field-min-date"
                                    class="input input-sm input-bordered w-full"
                                    value="{{ $field->getValidationRule('min_date') }}"
                                    wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, min_date: $event.target.value || null } })"
                                />
                            </div>
                        @endif
                        @if (in_array('max_date', $validationOptions))
                            <div class="form-control">
                                <label class="label" for="field-max-date">
                                    <span class="label-text text-xs">{{ __( 'Max Date' ) }}</span>
                                </label>
                                <input
                                    type="date"
                                    id="field-max-date"
                                    class="input input-sm input-bordered w-full"
                                    value="{{ $field->getValidationRule('max_date') }}"
                                    wire:change="updateField({ validation_rules: { ...{{ json_encode((object) ($field->validation_rules ?? [])) }}, max_date: $event.target.value || null } })"
                                />
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Options tab (for select, radio, checkbox_group) --}}
            @if ($hasOptions)
                <div x-show="activeTab === 'options'" x-cloak>
                    @include('forms::livewire.form-builder.options-editor', ['field' => $field])
                </div>
            @endif

            {{-- Advanced tab --}}
            <div x-show="activeTab === 'advanced'" x-cloak class="space-y-3">
                {{-- Field name --}}
                <div class="form-control">
                    <label class="label" for="field-name">
                        <span class="label-text text-xs">{{ __( 'Field Name (for submissions)' ) }}</span>
                    </label>
                    <input
                        type="text"
                        id="field-name"
                        class="input input-sm input-bordered w-full font-mono text-xs"
                        value="{{ $field->name }}"
                        wire:change="updateField({ name: $event.target.value })"
                    />
                    <label class="label">
                        <span class="label-text-alt text-xs text-base-content/50">{{ __( 'Use snake_case, no spaces' ) }}</span>
                    </label>
                </div>

                {{-- CSS Classes --}}
                <div class="form-control">
                    <label class="label" for="field-css">
                        <span class="label-text text-xs">{{ __( 'CSS Classes' ) }}</span>
                    </label>
                    <input
                        type="text"
                        id="field-css"
                        class="input input-sm input-bordered w-full font-mono text-xs"
                        value="{{ $field->css_classes }}"
                        wire:change="updateField({ css_classes: $event.target.value })"
                    />
                </div>

                {{-- Conditional Logic --}}
                @if(!\ArtisanPackUI\Forms\Config\FieldTypes::isLayoutField($field->type))
                    <div class="divider text-xs text-base-content/50">{{ __( 'Conditional Logic' ) }}</div>
                    @include('forms::livewire.form-builder.condition-builder')
                @else
                    <div class="rounded-lg bg-base-300 p-3 text-xs">
                        <div class="flex items-center gap-2 text-base-content/60">
                            <x-artisanpack-icon name="o-information-circle" class="h-4 w-4" />
                            <span>{{ __( 'Layout fields do not support conditional logic' ) }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- No field selected --}}
        <div class="flex h-64 flex-col items-center justify-center text-center">
            <x-artisanpack-icon name="o-cursor-arrow-rays" class="mb-4 h-12 w-12 text-base-content/30" />
            <p class="mb-2 text-base-content/60">{{ __( 'No field selected' ) }}</p>
            <p class="text-sm text-base-content/40">
                {{ __( 'Click a field in the canvas to edit its settings' ) }}
            </p>
        </div>
    @endif
</div>
