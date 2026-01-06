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
                    <span wire:loading.remove wire:target="saveForm">Unsaved changes</span>
                    <span wire:loading wire:target="saveForm">Saving...</span>
                </span>
            @elseif ($lastSavedAt)
                <span class="badge badge-success badge-sm gap-1">
                    <x-artisanpack-icon name="o-check" class="h-3 w-3" />
                    Saved at {{ $lastSavedAt }}
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
            <span wire:loading.remove wire:target="saveForm">Save Changes</span>
            <span wire:loading wire:target="saveForm">
                <span class="loading loading-spinner loading-xs"></span>
                Saving...
            </span>
        </button>
    </div>

    {{-- Multi-step tabs (if applicable) --}}
    @if ($form->is_multi_step)
        @include('forms::livewire.form-builder.step-manager')
    @endif

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
        @field-added.window="message = 'Field added to form'"
        @field-deleted.window="message = 'Field removed from form'"
        @field-duplicated.window="message = 'Field duplicated'"
        @step-added.window="message = 'Step added to form'"
        @step-deleted.window="message = 'Step removed from form'"
        @form-saved.window="message = 'Form saved successfully'"
        x-text="message"
    ></div>
</div>
