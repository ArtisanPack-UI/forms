<div class="field-palette rounded-box bg-base-200 p-4">
    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/70">
        {{ __( 'Add Fields' ) }}
    </h3>

    <div class="space-y-4">
        @foreach ($this->fieldCategories as $categoryKey => $category)
            <div class="field-category" wire:key="category-{{ $categoryKey }}">
                <h4 class="mb-2 text-xs font-medium text-base-content/60">
                    {{ $category['label'] }}
                </h4>

                <div class="grid grid-cols-2 gap-2">
                    @foreach ($category['fields'] as $type => $config)
                        <button
                            type="button"
                            wire:key="field-{{ $categoryKey }}-{{ $type }}"
                            wire:click="addField('{{ $type }}')"
                            wire:loading.attr="disabled"
                            class="btn btn-ghost btn-sm h-auto flex-col gap-1 py-2 text-xs hover:bg-base-300"
                            title="{{ __( 'Add :field', ['field' => $config['label']] ) }}"
                        >
                            <x-artisanpack-icon name="{{ $config['icon'] }}" class="h-5 w-5" />
                            <span class="truncate">{{ $config['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="divider my-4"></div>

    {{-- Quick tips --}}
    <div class="text-xs text-base-content/60">
        <p class="mb-1">
            <strong>{{ __( 'Tip:' ) }}</strong> {{ __( 'Click a field type to add it to your form.' ) }}
        </p>
        <p>
            {{ __( 'Drag fields in the canvas to reorder them.' ) }}
        </p>
    </div>
</div>
