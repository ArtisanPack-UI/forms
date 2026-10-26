@php
    // Kept as stored (blank options included) so a just-added option survives the re-render.
    $options = collect($field->getConfig('options', []))
        ->map(fn ($option, $key) => is_array($option) ? $option : ['value' => is_string($key) ? $key : (string) $option, 'label' => (string) $option])
        ->values()
        ->all();
    $choicesSource = $field->getChoicesSource();
    $isMultiple = $field->isMultipleChoice();
    // A ranking's options are its choices to order, none selected (since 1.6.0).
    $hasDefaults = \ArtisanPackUI\Forms\FieldTypes\RankingFieldType::TYPE !== $field->type;
    $predefinedLists = collect(\ArtisanPackUI\Forms\Support\PredefinedChoices::all())
        ->map(fn ($list) => ['label' => $list['label'], 'text' => \ArtisanPackUI\Forms\Support\PredefinedChoices::toBulk($list['choices'])])
        ->all();
@endphp

@if (null !== $choicesSource)
    <div wire:key="options-source-{{ $field->uuid }}" class="space-y-2">
        <div class="rounded-lg bg-base-300 p-3 text-xs text-base-content/70">
            {{ __( 'Choices are loaded from ":source" when the form is shown. Change "Load Choices From" in the General tab to edit the options list.', ['source' => \Illuminate\Support\Arr::first(app(\ArtisanPackUI\Forms\Support\ChoiceSources::class)->options(), fn ($source) => $source['value'] === $choicesSource)['label'] ?? $choicesSource] ) }}
        </div>
        <ul class="max-h-48 space-y-1 overflow-auto text-xs" aria-label="{{ __( 'Loaded choices' ) }}">
            @forelse ($field->options as $option)
                <li class="rounded bg-base-100 px-2 py-1">{{ $option['label'] ?? $option['value'] ?? $option }}</li>
            @empty
                <li class="text-base-content/50">{{ __( 'The source returned no choices.' ) }}</li>
            @endforelse
        </ul>
    </div>
@else
<div
    wire:key="options-editor-{{ $field->uuid }}"
    class="options-editor space-y-3"
    x-data="{
        options: @js($options).map((opt, i) => ({ ...opt, default: opt.default === true, _id: i })),
        nextId: @js(count($options)),
        multiple: @js($isMultiple),
        bulkOpen: false,
        bulkText: '',
        predefined: @js($predefinedLists),
        selectedList: '',
        addOption() {
            this.options.push({ value: '', label: '', default: false, _id: this.nextId++ });
            this.saveOptions();
        },
        removeOption(index) {
            this.options.splice(index, 1);
            this.saveOptions();
        },
        toggleDefault(index, checked) {
            if (checked && ! this.multiple) {
                this.options.forEach((opt, i) => { opt.default = i === index; });
            } else {
                this.options[index].default = checked;
            }
            this.saveOptions();
        },
        saveOptions() {
            const cleanOptions = this.options.map(({ _id, default: isDefault, ...rest }) => (isDefault ? { ...rest, default: true } : rest));
            const fieldConfig = { ...@js($field->field_config ?? []), options: cleanOptions };
            this.$wire.updateField({ field_config: fieldConfig });
        },
        openBulk() {
            this.bulkText = this.options.map(opt => (opt.value === opt.label || opt.value === '' ? opt.label : `${opt.value}|${opt.label}`)).filter(line => line !== '').join('\n');
            this.bulkOpen = true;
            this.$nextTick(() => this.$refs.bulkText.focus());
        },
        insertList() {
            if (this.predefined[this.selectedList]) {
                this.bulkText = this.predefined[this.selectedList].text;
            }
        },
        applyBulk(mode) {
            this.$wire.applyBulkChoices(this.bulkText, mode);
            this.bulkOpen = false;
        }
    }"
>
    <div class="text-xs text-base-content/60">
        {{ __( 'Add options for users to choose from:' ) }}
    </div>

    {{-- Options list --}}
    <div class="space-y-2">
        <template x-for="(option, index) in options" :key="option._id">
            <div class="flex items-center gap-2">
                @if ($hasDefaults)
                    <input
                        type="checkbox"
                        class="checkbox checkbox-xs checkbox-primary"
                        :checked="option.default"
                        :aria-label="@js(__( 'Selected by default: :label' )).replace(':label', option.label || option.value)"
                        title="{{ __( 'Selected by default' ) }}"
                        @change="toggleDefault(index, $event.target.checked)"
                    />
                @endif
                <div class="flex-1">
                    <input
                        type="text"
                        class="input input-sm input-bordered w-full"
                        placeholder="{{ __( 'Label' ) }}"
                        aria-label="{{ __( 'Label' ) }}"
                        x-model="option.label"
                        @change="saveOptions()"
                    />
                </div>
                <div class="w-24">
                    <input
                        type="text"
                        class="input input-sm input-bordered w-full font-mono text-xs"
                        placeholder="{{ __( 'Value' ) }}"
                        aria-label="{{ __( 'Value' ) }}"
                        x-model="option.value"
                        @change="saveOptions()"
                    />
                </div>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs text-error"
                    @click="removeOption(index)"
                    title="{{ __( 'Remove option' ) }}"
                    aria-label="{{ __( 'Remove option' ) }}"
                >
                    <x-artisanpack-icon name="o-x-mark" class="h-4 w-4" />
                </button>
            </div>
        </template>
    </div>

    @if ($hasDefaults)
        <div x-show="options.length > 0" class="text-xs text-base-content/50">
            {{ $isMultiple ? __( 'Check the options that start selected.' ) : __( 'Check the option that starts selected.' ) }}
        </div>
    @endif

    {{-- Add option and bulk edit buttons --}}
    <div class="flex gap-2">
        <button
            type="button"
            class="btn btn-ghost btn-sm flex-1"
            @click="addOption()"
        >
            <x-artisanpack-icon name="o-plus" class="h-4 w-4" />
            {{ __( 'Add Option' ) }}
        </button>
        <button
            type="button"
            class="btn btn-ghost btn-sm flex-1"
            :aria-expanded="bulkOpen ? 'true' : 'false'"
            aria-controls="bulk-choices-{{ $field->uuid }}"
            @click="bulkOpen ? bulkOpen = false : openBulk()"
        >
            <x-artisanpack-icon name="o-queue-list" class="h-4 w-4" />
            {{ __( 'Bulk Add / Predefined Choices' ) }}
        </button>
    </div>

    {{-- Bulk editor --}}
    <div
        id="bulk-choices-{{ $field->uuid }}"
        x-show="bulkOpen"
        x-cloak
        class="space-y-2 rounded-lg bg-base-300 p-3"
    >
        <div class="form-control">
            <label class="label" for="bulk-choices-list-{{ $field->uuid }}">
                <span class="label-text text-xs">{{ __( 'Predefined Choices' ) }}</span>
            </label>
            <div class="flex gap-2">
                <select id="bulk-choices-list-{{ $field->uuid }}" class="select select-sm select-bordered flex-1" x-model="selectedList">
                    <option value="">{{ __( 'Choose a list...' ) }}</option>
                    <template x-for="(list, key) in predefined" :key="key">
                        <option :value="key" x-text="list.label"></option>
                    </template>
                </select>
                <button type="button" class="btn btn-sm" :disabled="! selectedList" @click="insertList()">
                    {{ __( 'Insert' ) }}
                </button>
            </div>
        </div>

        <div class="form-control">
            <label class="label" for="bulk-choices-text-{{ $field->uuid }}">
                <span class="label-text text-xs">{{ __( 'Choices' ) }}</span>
            </label>
            <textarea
                id="bulk-choices-text-{{ $field->uuid }}"
                x-ref="bulkText"
                rows="8"
                class="textarea textarea-sm textarea-bordered w-full font-mono text-xs"
                aria-describedby="bulk-choices-hint-{{ $field->uuid }}"
                x-model="bulkText"
            ></textarea>
            <label class="label" id="bulk-choices-hint-{{ $field->uuid }}">
                <span class="label-text-alt text-xs text-base-content/50">{{ __( 'One choice per line. Use value|label to store a value that differs from its label.' ) }}</span>
            </label>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="applyBulk('replace')">{{ __( 'Replace Options' ) }}</button>
            <button type="button" class="btn btn-sm" @click="applyBulk('append')">{{ __( 'Add to Options' ) }}</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="bulkOpen = false">{{ __( 'Cancel' ) }}</button>
        </div>
    </div>

    {{-- Empty state --}}
    <div x-show="options.length === 0" class="rounded-lg bg-base-300 p-3 text-center text-xs text-base-content/50">
        {{ __( 'No options yet. Click "Add Option" to create one.' ) }}
    </div>
</div>
@endif
