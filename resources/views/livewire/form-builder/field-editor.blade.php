<div class="field-editor rounded-box bg-base-200 p-4">
    @if ($this->selectedField)
        @php
            $field = $this->selectedField;
            $fieldConfig = \ArtisanPackUI\Forms\Config\FieldTypes::getTypeConfig($field->type);
            $hasOptions = \ArtisanPackUI\Forms\Config\FieldTypes::hasOptions($field->type);
            $isContentLayoutField = in_array($field->type, ['heading', 'paragraph', 'html'], true);
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

                {{-- Label (a heading's label is its text) --}}
                <div class="form-control">
                    <label class="label" for="field-label">
                        <span class="label-text text-xs">{{ 'heading' === $field->type ? __( 'Heading Text' ) : __( 'Label' ) }}</span>
                    </label>
                    <input
                        type="text"
                        id="field-label"
                        class="input input-sm input-bordered w-full"
                        @if ('heading' === $field->type)
                            value="{{ $field->getLayoutContent() }}"
                            wire:change="updateLayoutContent($event.target.value)"
                        @else
                            value="{{ $field->label }}"
                            wire:change="updateField({ label: $event.target.value })"
                        @endif
                    />
                </div>

                {{-- Paragraph / HTML content --}}
                @if (in_array($field->type, ['paragraph', 'html'], true))
                    <div class="form-control">
                        <label class="label" for="field-content">
                            <span class="label-text text-xs">{{ 'html' === $field->type ? __( 'HTML Content' ) : __( 'Content' ) }}</span>
                        </label>
                        <textarea
                            id="field-content"
                            rows="{{ 'html' === $field->type ? 6 : 4 }}"
                            class="textarea textarea-sm textarea-bordered w-full @if ('html' === $field->type) font-mono text-xs @endif"
                            wire:change="updateLayoutContent($event.target.value)"
                        >{{ $field->getLayoutContent() }}</textarea>
                    </div>
                @endif

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

                {{-- A composite field's sub-inputs --}}
                @if ($field->isComposite())
                    @include('forms::livewire.form-builder.sub-inputs-editor', ['field' => $field])
                @endif

                {{-- Help text --}}
                @unless ($isContentLayoutField)
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
                @endunless

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
                            aria-describedby="field-default-hint"
                            wire:change="updateField({ default_value: $event.target.value })"
                        />
                        <label class="label" id="field-default-hint">
                            <span class="label-text-alt text-xs text-base-content/50">
                                {{ __( 'Merge tags: :tags', ['tags' => implode( ', ', \ArtisanPackUI\Forms\Support\MergeTags::available() )] ) }}
                            </span>
                        </label>
                    </div>
                @endif

                {{-- Type-specific settings from the field type's settings schema --}}
                @foreach (\ArtisanPackUI\Forms\Support\FieldSettings::forTab($field, 'general') as $setting)
                    @include('forms::livewire.form-builder.field-setting', ['field' => $field, 'setting' => $setting])
                @endforeach

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

                {{-- Type-specific validation settings from the field type's settings schema --}}
                @foreach (\ArtisanPackUI\Forms\Support\FieldSettings::forTab($field, 'validation') as $setting)
                    @include('forms::livewire.form-builder.field-setting', ['field' => $field, 'setting' => $setting])
                @endforeach
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

                {{-- Type-specific advanced settings, such as read-only --}}
                @foreach (\ArtisanPackUI\Forms\Support\FieldSettings::forTab($field, 'advanced') as $setting)
                    @include('forms::livewire.form-builder.field-setting', ['field' => $field, 'setting' => $setting])
                @endforeach

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
