<div>
    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded-lg" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded-lg" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header with Title and Actions -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        @if ($this->currentForm)
                            {{ $this->currentForm->name }} Submissions
                        @else
                            All Submissions
                        @endif
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->statusCounts['all'] }} total submissions
                        @if ($this->statusCounts['unread'] > 0)
                            <span class="inline-flex items-center px-2 py-0.5 ml-2 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                {{ $this->statusCounts['unread'] }} unread
                            </span>
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Bulk Actions Dropdown -->
                    @if (count($selected) > 0)
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open"
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ count($selected) }} selected
                                <svg class="ml-2 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div x-show="open"
                                 @click.away="open = false"
                                 x-transition
                                 class="absolute right-0 z-10 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <button wire:click="bulkMarkAsRead"
                                            class="block w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                            role="menuitem">
                                        Mark as read
                                    </button>
                                    <button wire:click="bulkMarkAsUnread"
                                            class="block w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                            role="menuitem">
                                        Mark as unread
                                    </button>
                                    <button wire:click="bulkMarkAsSpam"
                                            class="block w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                                            role="menuitem">
                                        Mark as spam
                                    </button>
                                    <hr class="my-1 border-gray-200 dark:border-gray-600">
                                    <button wire:click="bulkDelete"
                                            wire:confirm="Are you sure you want to delete {{ count($selected) }} submission(s)? This cannot be undone."
                                            class="block w-full px-4 py-2 text-sm text-left text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-600"
                                            role="menuitem">
                                        Delete selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Export Button -->
                    @if ($formId)
                        <button wire:click="exportCsv"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Export CSV
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Search Input -->
                <div class="flex-1">
                    <label for="search" class="sr-only">Search submissions</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               id="search"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Search by submission number or content...">
                    </div>
                </div>

                <!-- Form Filter (when viewing all) -->
                @if (!$formId)
                    <div class="sm:w-48">
                        <label for="formFilter" class="sr-only">Filter by form</label>
                        <select wire:model.live="formFilter"
                                id="formFilter"
                                class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="">All Forms</option>
                            @foreach ($this->forms as $form)
                                <option value="{{ $form->id }}">{{ $form->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- Date Range Filter -->
                <div class="sm:w-40">
                    <label for="dateRange" class="sr-only">Filter by date</label>
                    <select wire:model.live="dateRange"
                            id="dateRange"
                            class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-8 px-4 sm:px-6" aria-label="Tabs">
                <button wire:click="$set('status', 'all')"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $status === 'all' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                    All
                    <span class="ml-2 py-0.5 px-2 rounded-full text-xs {{ $status === 'all' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300' }}">
                        {{ $this->statusCounts['all'] }}
                    </span>
                </button>

                <button wire:click="$set('status', 'unread')"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $status === 'unread' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                    Unread
                    <span class="ml-2 py-0.5 px-2 rounded-full text-xs {{ $status === 'unread' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300' }}">
                        {{ $this->statusCounts['unread'] }}
                    </span>
                </button>

                <button wire:click="$set('status', 'read')"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $status === 'read' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                    Read
                    <span class="ml-2 py-0.5 px-2 rounded-full text-xs {{ $status === 'read' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300' }}">
                        {{ $this->statusCounts['read'] }}
                    </span>
                </button>

                <button wire:click="$set('status', 'starred')"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $status === 'starred' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                    Starred
                    <span class="ml-2 py-0.5 px-2 rounded-full text-xs {{ $status === 'starred' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300' }}">
                        {{ $this->statusCounts['starred'] }}
                    </span>
                </button>

                <button wire:click="$set('status', 'spam')"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $status === 'spam' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                    Spam
                    <span class="ml-2 py-0.5 px-2 rounded-full text-xs {{ $status === 'spam' ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300' }}">
                        {{ $this->statusCounts['spam'] }}
                    </span>
                </button>
            </nav>
        </div>
    </div>

    <!-- Submissions Table -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        @if ($this->submissions->count() > 0)
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <!-- Checkbox Column -->
                        <th scope="col" class="w-12 px-6 py-3">
                            <label class="sr-only">Select all</label>
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded"
                                   aria-label="Select all submissions">
                        </th>

                        <!-- Submission Number -->
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <button wire:click="sort('submission_number')" class="group inline-flex items-center">
                                Submission
                                @if ($sortBy === 'submission_number')
                                    <svg class="ml-1 h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                @else
                                    <svg class="ml-1 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>

                        <!-- Form Name (when viewing all) -->
                        @if (!$formId)
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Form
                            </th>
                        @endif

                        <!-- Summary -->
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Summary
                        </th>

                        <!-- Date -->
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <button wire:click="sort('created_at')" class="group inline-flex items-center">
                                Date
                                @if ($sortBy === 'created_at')
                                    <svg class="ml-1 h-4 w-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                @else
                                    <svg class="ml-1 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                @endif
                            </button>
                        </th>

                        <!-- Actions -->
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($this->submissions as $submission)
                        <tr wire:key="submission-{{ $submission->id }}"
                            class="{{ !$submission->is_read ? 'bg-indigo-50 dark:bg-indigo-900/20' : '' }}">
                            <!-- Checkbox -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox"
                                       wire:model.live="selected"
                                       value="{{ $submission->id }}"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded"
                                       aria-label="Select submission {{ $submission->submission_number }}">
                            </td>

                            <!-- Submission Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <!-- Status Indicators -->
                                    @if ($submission->is_starred)
                                        <svg class="h-4 w-4 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    @endif
                                    @if ($submission->is_spam)
                                        <span class="inline-flex items-center px-2 py-0.5 mr-2 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Spam
                                        </span>
                                    @endif
                                    <a href="{{ route('forms.submissions.show', [$submission->form, $submission]) }}"
                                       class="text-sm {{ !$submission->is_read ? 'font-semibold' : 'font-medium' }} text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $submission->submission_number }}
                                    </a>
                                </div>
                            </td>

                            <!-- Form Name (when viewing all) -->
                            @if (!$formId)
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $submission->form->name ?? 'Unknown' }}
                                </td>
                            @endif

                            <!-- Summary (first 2 field values) -->
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white max-w-md truncate">
                                    @php
                                        $summaryValues = $submission->values->take(2);
                                    @endphp
                                    @foreach ($summaryValues as $value)
                                        <span class="text-gray-500 dark:text-gray-400">{{ $value->field_label }}:</span>
                                        {{ \Illuminate\Support\Str::limit($value->display_value, 30) }}
                                        @if (!$loop->last)
                                            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>

                            <!-- Date -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <span title="{{ $submission->created_at->format('F j, Y g:i A') }}">
                                    {{ $submission->created_at->diffForHumans() }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Star Toggle -->
                                    <button wire:click="toggleStar({{ $submission->id }})"
                                            class="{{ $submission->is_starred ? 'text-yellow-400 hover:text-yellow-500' : 'text-gray-400 hover:text-yellow-400' }}"
                                            title="{{ $submission->is_starred ? 'Unstar' : 'Star' }}"
                                            aria-label="{{ $submission->is_starred ? 'Unstar' : 'Star' }} submission">
                                        <svg class="h-5 w-5" fill="{{ $submission->is_starred ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                        </svg>
                                    </button>

                                    <!-- Read/Unread Toggle -->
                                    @if ($submission->is_read)
                                        <button wire:click="markAsUnread({{ $submission->id }})"
                                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                                title="Mark as unread"
                                                aria-label="Mark as unread">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        </button>
                                    @else
                                        <button wire:click="markAsRead({{ $submission->id }})"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                title="Mark as read"
                                                aria-label="Mark as read">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        </button>
                                    @endif

                                    <!-- Spam Toggle -->
                                    <button wire:click="toggleSpam({{ $submission->id }})"
                                            class="{{ $submission->is_spam ? 'text-red-600 hover:text-red-800' : 'text-gray-400 hover:text-red-600' }}"
                                            title="{{ $submission->is_spam ? 'Not spam' : 'Mark as spam' }}"
                                            aria-label="{{ $submission->is_spam ? 'Mark as not spam' : 'Mark as spam' }}">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                    </button>

                                    <!-- Delete Button -->
                                    <button wire:click="delete({{ $submission->id }})"
                                            wire:confirm="Are you sure you want to delete this submission? This cannot be undone."
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            title="Delete"
                                            aria-label="Delete submission">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Pagination -->
            @if ($this->submissions->hasPages())
                <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
                    {{ $this->submissions->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No submissions found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($search || $status !== 'all' || $dateRange !== 'all')
                        Try adjusting your search or filter criteria.
                    @else
                        No submissions have been received yet.
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
