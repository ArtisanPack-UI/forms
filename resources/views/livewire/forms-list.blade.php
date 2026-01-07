<div>
    <!-- Search and Filters -->
    <x-artisanpack-card class="mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <!-- Search Input -->
            <div class="flex-1">
                <x-artisanpack-input
                    wire:model.live.debounce.300ms="search"
                    icon="o-magnifying-glass"
                    placeholder="Search forms by name or slug..."
                />
            </div>

            <!-- Status Filter -->
            <div class="sm:w-48">
                <x-artisanpack-select
                    wire:model.live="statusFilter"
                    :options="[
                        ['value' => 'all', 'label' => 'All Forms'],
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'inactive', 'label' => 'Inactive'],
                    ]"
                    option-value="value"
                    option-label="label"
                />
            </div>
        </div>
    </x-artisanpack-card>

    <!-- Forms Table -->
    <x-artisanpack-card class="overflow-hidden">
        @if ($this->forms->count() > 0)
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <button wire:click="sort('name')" class="group inline-flex items-center gap-1">
                                    Name
                                    @if ($sortBy === 'name')
                                        <x-artisanpack-icon name="o-chevron-up" class="w-4 h-4 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                    @endif
                                </button>
                            </th>
                            <th>Fields</th>
                            <th>
                                <button wire:click="sort('submissions')" class="group inline-flex items-center gap-1">
                                    Submissions
                                    @if ($sortBy === 'submissions')
                                        <x-artisanpack-icon name="o-chevron-up" class="w-4 h-4 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                    @endif
                                </button>
                            </th>
                            <th>Status</th>
                            <th>
                                <button wire:click="sort('created_at')" class="group inline-flex items-center gap-1">
                                    Created
                                    @if ($sortBy === 'created_at')
                                        <x-artisanpack-icon name="o-chevron-up" class="w-4 h-4 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->forms as $form)
                            <tr wire:key="form-{{ $form->id }}">
                                <td>
                                    <div>
                                        <a href="{{ route('forms.edit', $form) }}" class="font-medium link link-hover link-primary">
                                            {{ $form->name }}
                                        </a>
                                        <div class="text-sm opacity-60">{{ $form->slug }}</div>
                                    </div>
                                </td>
                                <td>{{ $form->fields_count }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        {{ $form->submissions_count }}
                                        @if ($form->unread_count > 0)
                                            <x-artisanpack-badge value="{{ $form->unread_count }} new" color="info" class="badge-sm" />
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($form->is_active)
                                        <x-artisanpack-badge value="Active" color="success" />
                                    @else
                                        <x-artisanpack-badge value="Inactive" color="neutral" />
                                    @endif
                                </td>
                                <td>{{ $form->created_at->format('M j, Y') }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-artisanpack-button
                                            link="{{ route('forms.edit', $form) }}"
                                            icon="o-pencil-square"
                                            color="ghost"
                                            class="btn-sm"
                                            title="Edit"
                                        />
                                        <x-artisanpack-button
                                            wire:click="togglePublish({{ $form->id }})"
                                            icon="{{ $form->is_active ? 'o-no-symbol' : 'o-check-circle' }}"
                                            color="ghost"
                                            class="btn-sm {{ $form->is_active ? 'text-warning' : 'text-success' }}"
                                            title="{{ $form->is_active ? 'Unpublish' : 'Publish' }}"
                                        />
                                        <x-artisanpack-button
                                            wire:click="duplicate({{ $form->id }})"
                                            wire:confirm="Are you sure you want to duplicate this form?"
                                            icon="o-document-duplicate"
                                            color="ghost"
                                            class="btn-sm"
                                            title="Duplicate"
                                        />
                                        <x-artisanpack-button
                                            wire:click="delete({{ $form->id }})"
                                            wire:confirm="Are you sure you want to delete this form? This will also delete all submissions and cannot be undone."
                                            icon="o-trash"
                                            color="ghost"
                                            class="btn-sm text-error"
                                            title="Delete"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($this->forms->hasPages())
                <div class="border-t border-base-300 px-4 py-3">
                    {{ $this->forms->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <x-artisanpack-icon name="o-document-text" class="mx-auto h-12 w-12 opacity-40" />
                <h3 class="mt-2 text-sm font-medium">No forms found</h3>
                <p class="mt-1 text-sm opacity-60">
                    @if ($search || $statusFilter !== 'all')
                        Try adjusting your search or filter criteria.
                    @else
                        Get started by creating your first form.
                    @endif
                </p>
                @if (!$search && $statusFilter === 'all')
                    <div class="mt-6">
                        <x-artisanpack-button
                            link="{{ route('forms.create') }}"
                            label="Create Form"
                            icon="o-plus"
                            color="primary"
                        />
                    </div>
                @endif
            </div>
        @endif
    </x-artisanpack-card>
</div>
