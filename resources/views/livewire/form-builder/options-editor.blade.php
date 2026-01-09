@php
    $options = $field->options ?? [];
@endphp

<div
    wire:key="options-editor-{{ $field->uuid }}"
    class="options-editor space-y-3"
    x-data="{
        options: @js($options).map((opt, i) => ({ ...opt, _id: i })),
        nextId: @js(count($options)),
        addOption() {
            this.options.push({ value: '', label: '', _id: this.nextId++ });
            this.saveOptions();
        },
        removeOption(index) {
            this.options.splice(index, 1);
            this.saveOptions();
        },
        saveOptions() {
            const cleanOptions = this.options.map(({ _id, ...rest }) => rest);
            const fieldConfig = { ...@js($field->field_config ?? []), options: cleanOptions };
            this.$wire.updateField({ field_config: fieldConfig });
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
                <div class="flex-1">
                    <input
                        type="text"
                        class="input input-sm input-bordered w-full"
                        placeholder="{{ __( 'Label' ) }}"
                        x-model="option.label"
                        @change="saveOptions()"
                    />
                </div>
                <div class="w-24">
                    <input
                        type="text"
                        class="input input-sm input-bordered w-full font-mono text-xs"
                        placeholder="{{ __( 'Value' ) }}"
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

    {{-- Add option button --}}
    <button
        type="button"
        class="btn btn-ghost btn-sm w-full"
        @click="addOption()"
    >
        <x-artisanpack-icon name="o-plus" class="h-4 w-4" />
        {{ __( 'Add Option' ) }}
    </button>

    {{-- Empty state --}}
    <div x-show="options.length === 0" class="rounded-lg bg-base-300 p-3 text-center text-xs text-base-content/50">
        {{ __( 'No options yet. Click "Add Option" to create one.' ) }}
    </div>
</div>
