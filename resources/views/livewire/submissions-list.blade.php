<div>
    <!-- Flash Messages -->
    @if (session('success'))
        <x-artisanpack-alert type="success" :message="session('success')" dismissible class="mb-4" />
    @endif

    @if (session('error'))
        <x-artisanpack-alert type="error" :message="session('error')" dismissible class="mb-4" />
    @endif

    <!-- Header with Title and Actions -->
    <x-artisanpack-card class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">
                    @if ($this->currentForm)
                        {{ $this->currentForm->name }} Submissions
                    @else
                        All Submissions
                    @endif
                </h2>
                <p class="mt-1 text-sm opacity-60">
                    {{ $this->statusCounts['all'] }} total submissions
                    @if ($this->statusCounts['unread'] > 0)
                        <x-artisanpack-badge value="{{ $this->statusCounts['unread'] }} unread" color="info" class="ml-2 badge-sm" />
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2">
                <!-- Bulk Actions Dropdown -->
                @if (count($selected) > 0)
                    <x-artisanpack-dropdown>
                        <x-slot:trigger>
                            <x-artisanpack-button color="neutral" class="btn-sm">
                                {{ count($selected) }} selected
                                <x-artisanpack-icon name="o-chevron-down" class="w-4 h-4 ml-1" />
                            </x-artisanpack-button>
                        </x-slot:trigger>
                        <x-artisanpack-menu>
                            <x-artisanpack-menu-item wire:click="bulkMarkAsRead" label="Mark as read" />
                            <x-artisanpack-menu-item wire:click="bulkMarkAsUnread" label="Mark as unread" />
                            <x-artisanpack-menu-item wire:click="bulkMarkAsSpam" label="Mark as spam" />
                            <div class="divider my-1"></div>
                            <x-artisanpack-menu-item
                                wire:click="bulkDelete"
                                wire:confirm="Are you sure you want to delete {{ count($selected) }} submission(s)? This cannot be undone."
                                label="Delete selected"
                                class="text-error"
                            />
                        </x-artisanpack-menu>
                    </x-artisanpack-dropdown>
                @endif

                <!-- Export Button -->
                @if ($formId)
                    <x-artisanpack-button
                        wire:click="exportCsv"
                        label="Export CSV"
                        icon="o-arrow-down-tray"
                        color="ghost"
                        class="btn-sm"
                    />
                @endif
            </div>
        </div>
    </x-artisanpack-card>

    <!-- Filters -->
    <x-artisanpack-card class="mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <!-- Search Input -->
            <div class="flex-1">
                <x-artisanpack-input
                    wire:model.live.debounce.300ms="search"
                    icon="o-magnifying-glass"
                    placeholder="Search by submission number or content..."
                />
            </div>

            <!-- Form Filter (when viewing all) -->
            @if (!$formId)
                <div class="sm:w-48">
                    <x-artisanpack-select
                        wire:model.live="formFilter"
                        placeholder="All Forms"
                        :options="$this->forms->map(fn($f) => ['value' => $f->id, 'label' => $f->name])->toArray()"
                        option-value="value"
                        option-label="label"
                    />
                </div>
            @endif

            <!-- Date Range Filter -->
            <div class="sm:w-40">
                <x-artisanpack-select
                    wire:model.live="dateRange"
                    :options="[
                        ['value' => 'all', 'label' => 'All Time'],
                        ['value' => 'today', 'label' => 'Today'],
                        ['value' => 'week', 'label' => 'This Week'],
                        ['value' => 'month', 'label' => 'This Month'],
                        ['value' => 'year', 'label' => 'This Year'],
                    ]"
                    option-value="value"
                    option-label="label"
                />
            </div>
        </div>
    </x-artisanpack-card>

    <!-- Status Tabs -->
    <x-artisanpack-card class="mb-6 p-0">
        <div class="tabs tabs-bordered px-4">
            <button wire:click="$set('status', 'all')" class="tab {{ $status === 'all' ? 'tab-active' : '' }}">
                All
                <x-artisanpack-badge value="{{ $this->statusCounts['all'] }}" class="ml-2 badge-sm" />
            </button>
            <button wire:click="$set('status', 'unread')" class="tab {{ $status === 'unread' ? 'tab-active' : '' }}">
                Unread
                <x-artisanpack-badge value="{{ $this->statusCounts['unread'] }}" class="ml-2 badge-sm" />
            </button>
            <button wire:click="$set('status', 'read')" class="tab {{ $status === 'read' ? 'tab-active' : '' }}">
                Read
                <x-artisanpack-badge value="{{ $this->statusCounts['read'] }}" class="ml-2 badge-sm" />
            </button>
            <button wire:click="$set('status', 'starred')" class="tab {{ $status === 'starred' ? 'tab-active' : '' }}">
                Starred
                <x-artisanpack-badge value="{{ $this->statusCounts['starred'] }}" class="ml-2 badge-sm" />
            </button>
            <button wire:click="$set('status', 'spam')" class="tab {{ $status === 'spam' ? 'tab-active' : '' }}">
                Spam
                <x-artisanpack-badge value="{{ $this->statusCounts['spam'] }}" class="ml-2 badge-sm" />
            </button>
        </div>
    </x-artisanpack-card>

    <!-- Submissions Table -->
    <x-artisanpack-card class="overflow-hidden">
        @if ($this->submissions->count() > 0)
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-12">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectAll"
                                    class="checkbox checkbox-sm"
                                    aria-label="Select all submissions"
                                />
                            </th>
                            <th>
                                <button wire:click="sort('submission_number')" class="group inline-flex items-center gap-1">
                                    Submission
                                    @if ($sortBy === 'submission_number')
                                        <x-artisanpack-icon name="o-chevron-up" class="w-4 h-4 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                    @endif
                                </button>
                            </th>
                            @if (!$formId)
                                <th>Form</th>
                            @endif
                            <th>Summary</th>
                            <th>
                                <button wire:click="sort('created_at')" class="group inline-flex items-center gap-1">
                                    Date
                                    @if ($sortBy === 'created_at')
                                        <x-artisanpack-icon name="o-chevron-up" class="w-4 h-4 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                    @endif
                                </button>
                            </th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->submissions as $submission)
                            <tr wire:key="submission-{{ $submission->id }}" class="{{ !$submission->is_read ? 'bg-info/10' : '' }}">
                                <td>
                                    <input
                                        type="checkbox"
                                        wire:model.live="selected"
                                        value="{{ $submission->id }}"
                                        class="checkbox checkbox-sm"
                                        aria-label="Select submission {{ $submission->submission_number }}"
                                    />
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if ($submission->is_starred)
                                            <x-artisanpack-icon name="s-star" class="w-4 h-4 text-warning" />
                                        @endif
                                        @if ($submission->is_spam)
                                            <x-artisanpack-badge value="Spam" color="error" class="badge-sm" />
                                        @endif
                                        <a href="{{ route('forms.submissions.show', [$submission->form, $submission]) }}"
                                           class="{{ !$submission->is_read ? 'font-semibold' : 'font-medium' }} link link-hover link-primary">
                                            {{ $submission->submission_number }}
                                        </a>
                                    </div>
                                </td>
                                @if (!$formId)
                                    <td class="opacity-60">{{ $submission->form->name ?? 'Unknown' }}</td>
                                @endif
                                <td>
                                    <div class="max-w-md truncate">
                                        @php
                                            $summaryValues = $submission->values->take(2);
                                        @endphp
                                        @foreach ($summaryValues as $value)
                                            <span class="opacity-60">{{ $value->field_label }}:</span>
                                            {{ \Illuminate\Support\Str::limit($value->display_value, 30) }}
                                            @if (!$loop->last)
                                                <span class="opacity-30 mx-1">|</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <span title="{{ $submission->created_at->format('F j, Y g:i A') }}">
                                        {{ $submission->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-artisanpack-button
                                            wire:click="toggleStar({{ $submission->id }})"
                                            icon="{{ $submission->is_starred ? 's-star' : 'o-star' }}"
                                            color="ghost"
                                            class="btn-sm {{ $submission->is_starred ? 'text-warning' : '' }}"
                                            title="{{ $submission->is_starred ? 'Unstar' : 'Star' }}"
                                        />
                                        @if ($submission->is_read)
                                            <x-artisanpack-button
                                                wire:click="markAsUnread({{ $submission->id }})"
                                                icon="o-envelope"
                                                color="ghost"
                                                class="btn-sm"
                                                title="Mark as unread"
                                            />
                                        @else
                                            <x-artisanpack-button
                                                wire:click="markAsRead({{ $submission->id }})"
                                                icon="o-envelope-open"
                                                color="ghost"
                                                class="btn-sm text-primary"
                                                title="Mark as read"
                                            />
                                        @endif
                                        <x-artisanpack-button
                                            wire:click="toggleSpam({{ $submission->id }})"
                                            icon="o-exclamation-triangle"
                                            color="ghost"
                                            class="btn-sm {{ $submission->is_spam ? 'text-error' : '' }}"
                                            title="{{ $submission->is_spam ? 'Not spam' : 'Mark as spam' }}"
                                        />
                                        <x-artisanpack-button
                                            wire:click="delete({{ $submission->id }})"
                                            wire:confirm="Are you sure you want to delete this submission? This cannot be undone."
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
            @if ($this->submissions->hasPages())
                <div class="border-t border-base-300 px-4 py-3">
                    {{ $this->submissions->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <x-artisanpack-icon name="o-inbox" class="mx-auto h-12 w-12 opacity-40" />
                <h3 class="mt-2 text-sm font-medium">No submissions found</h3>
                <p class="mt-1 text-sm opacity-60">
                    @if ($search || $status !== 'all' || $dateRange !== 'all')
                        Try adjusting your search or filter criteria.
                    @else
                        No submissions have been received yet.
                    @endif
                </p>
            </div>
        @endif
    </x-artisanpack-card>
</div>
