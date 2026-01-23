<div
    class="form-builder"
    x-data="{
        autoSaveTimeout: null,
        init() {
            this.$wire.on('auto-save-trigger', () => {
                clearTimeout(this.autoSaveTimeout);
                this.autoSaveTimeout = setTimeout(() => {
                    this.$wire.saveForm();
                }, 2000);
            });
            this.$wire.on('save-complete', () => {
                setTimeout(() => {
                    this.$wire.set('isSaving', false);
                }, 300);
            });
        }
    }"
>
    {{-- Header with save status --}}
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            @if ($isDirty)
                <span class="badge badge-warning badge-sm gap-1">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="saveForm"></span>
                    <span wire:loading.remove wire:target="saveForm">{{ __( 'Unsaved changes' ) }}</span>
                    <span wire:loading wire:target="saveForm">{{ __( 'Saving...' ) }}</span>
                </span>
            @elseif ($lastSavedAt)
                <span class="badge badge-success badge-sm gap-1">
                    <x-artisanpack-icon name="o-check" class="h-3 w-3" />
                    {{ __( 'Saved at :time', ['time' => $lastSavedAt] ) }}
                </span>
            @endif
        </div>

        <button
            type="button"
            class="btn btn-primary btn-sm"
            wire:click="saveForm"
            wire:loading.attr="disabled"
            :disabled="!$wire.isDirty"
        >
            <span wire:loading.remove wire:target="saveForm">{{ __( 'Save Changes' ) }}</span>
            <span wire:loading wire:target="saveForm">
                <span class="loading loading-spinner loading-xs"></span>
                {{ __( 'Saving...' ) }}
            </span>
        </button>
    </div>

    {{-- Multi-step toggle and tabs --}}
    <div class="mb-4 rounded-lg bg-base-200 p-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <label class="label cursor-pointer gap-2">
                    <span class="label-text font-medium">{{ __( 'Multi-Step Form' ) }}</span>
                    <input
                        type="checkbox"
                        class="toggle toggle-primary toggle-sm"
                        {{ $form->is_multi_step ? 'checked' : '' }}
                        wire:change="{{ $form->is_multi_step ? 'disableMultiStep' : 'enableMultiStep' }}"
                        wire:confirm="{{ $form->is_multi_step ? __( 'Disable multi-step mode? Steps will be removed but fields will be preserved.' ) : '' }}"
                    />
                </label>
                @if (!$form->is_multi_step)
                    <span class="text-xs text-base-content/50">{{ __( 'Enable to create wizard-style forms with multiple steps' ) }}</span>
                @endif
            </div>
        </div>

        @if ($form->is_multi_step)
            <div class="mt-3 border-t border-base-300 pt-3">
                @include('forms::livewire.form-builder.step-manager')
            </div>
        @endif
    </div>

    {{-- Three-column layout --}}
    <div class="grid grid-cols-12 gap-4">
        {{-- Left column: Field Palette --}}
        <div class="col-span-3">
            @include('forms::livewire.form-builder.field-palette')
        </div>

        {{-- Center column: Form Canvas --}}
        <div class="col-span-6">
            @include('forms::livewire.form-builder.form-canvas')
        </div>

        {{-- Right column: Field Editor --}}
        <div class="col-span-3">
            @include('forms::livewire.form-builder.field-editor')
        </div>
    </div>

    {{-- ARIA live region for accessibility announcements --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        x-data="{ message: '' }"
        @field-added.window="message = @js(__( 'Field added to form' ))"
        @field-deleted.window="message = @js(__( 'Field removed from form' ))"
        @field-duplicated.window="message = @js(__( 'Field duplicated' ))"
        @step-added.window="message = @js(__( 'Step added to form' ))"
        @step-deleted.window="message = @js(__( 'Step removed from form' ))"
        @form-saved.window="message = @js(__( 'Form saved successfully' ))"
        x-text="message"
    ></div>
</div>
