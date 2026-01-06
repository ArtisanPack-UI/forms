<div class="form-canvas rounded-box min-h-96 border-2 border-dashed border-base-300 bg-base-100 p-4">
    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/70">
        Form Fields
        <span class="badge badge-neutral badge-sm ml-2">{{ $this->fields->count() }}</span>
    </h3>

    @if ($this->fields->isEmpty())
        <div class="flex h-64 flex-col items-center justify-center text-center">
            <x-artisanpack-icon name="o-squares-plus" class="mb-4 h-12 w-12 text-base-content/30" />
            <p class="mb-2 text-base-content/60">No fields yet</p>
            <p class="text-sm text-base-content/40">
                Click a field type from the palette to add it here
            </p>
        </div>
    @else
        <div
            x-drag-context
            @drag:end="$wire.dispatch('fields-reordered', { orderedUuids: $event.detail.orderedIds })"
            class="space-y-2"
            role="list"
            aria-label="Form fields"
        >
            @foreach ($this->fields as $field)
                @include('forms::livewire.form-builder.field-card', ['field' => $field])
            @endforeach
        </div>
    @endif
</div>
