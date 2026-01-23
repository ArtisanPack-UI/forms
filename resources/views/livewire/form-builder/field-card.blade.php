@php
    $fieldConfig = \ArtisanPackUI\Forms\Config\FieldTypes::getTypeConfig($field->type);
    $isSelected = $selectedFieldId === $field->uuid;
@endphp

<div
    wire:key="field-{{ $field->uuid }}"
    x-drag-item="{{ json_encode(['id' => $field->uuid, 'label' => $field->label]) }}"
    role="listitem"
    class="field-card group rounded-lg border bg-base-100 p-3 transition-all {{ $isSelected ? 'border-primary ring-2 ring-primary/20' : 'border-base-300 hover:border-base-content/20' }}"
>
    <div class="flex items-start gap-3">
        {{-- Drag handle --}}
        <button
            type="button"
            class="drag-handle mt-1 cursor-grab text-base-content/40 hover:text-base-content/60 active:cursor-grabbing"
            title="{{ __( 'Drag to reorder' ) }}"
            aria-label="{{ __( 'Drag to reorder field' ) }}"
        >
            <x-artisanpack-icon name="o-bars-3" class="h-4 w-4" />
        </button>

        {{-- Field icon --}}
        <div class="mt-1 text-base-content/60">
            <x-artisanpack-icon name="{{ $fieldConfig['icon'] ?? 'o-document' }}" class="h-5 w-5" />
        </div>

        {{-- Field info --}}
        <div class="min-w-0 flex-1">
            <button
                type="button"
                wire:click="selectField('{{ $field->uuid }}')"
                class="block w-full text-left"
            >
                <div class="flex items-center gap-2">
                    <span class="truncate font-medium">{{ $field->label }}</span>
                    @if ($field->is_required)
                        <span class="text-error" title="{{ __( 'Required' ) }}">*</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 text-xs text-base-content/60">
                    <span>{{ $fieldConfig['label'] ?? ucfirst($field->type) }}</span>
                    <span>&middot;</span>
                    <span class="font-mono">{{ $field->name }}</span>
                    @if ($field->width !== 'full')
                        <span>&middot;</span>
                        <span>{{ \ArtisanPackUI\Forms\Config\FieldTypes::getWidths()[$field->width]['label'] ?? $field->width }}</span>
                    @endif
                </div>
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 {{ $isSelected ? 'opacity-100' : '' }}">
            <button
                type="button"
                wire:click="duplicateField('{{ $field->uuid }}')"
                class="btn btn-ghost btn-xs"
                title="{{ __( 'Duplicate field' ) }}"
                aria-label="{{ __( 'Duplicate :label', ['label' => $field->label] ) }}"
            >
                <x-artisanpack-icon name="o-document-duplicate" class="h-4 w-4" />
            </button>
            <button
                type="button"
                wire:click="deleteField('{{ $field->uuid }}')"
                wire:confirm="{{ __( 'Are you sure you want to delete this field?' ) }}"
                class="btn btn-ghost btn-xs text-error hover:bg-error/10"
                title="{{ __( 'Delete field' ) }}"
                aria-label="{{ __( 'Delete :label', ['label' => $field->label] ) }}"
            >
                <x-artisanpack-icon name="o-trash" class="h-4 w-4" />
            </button>
        </div>
    </div>

    {{-- Conditional logic indicator --}}
    @if ($field->has_conditional_logic)
        <div class="mt-2 flex items-center gap-1 text-xs text-warning">
            <x-artisanpack-icon name="o-adjustments-horizontal" class="h-3 w-3" />
            <span>{{ __( 'Has conditional logic' ) }}</span>
        </div>
    @endif
</div>
