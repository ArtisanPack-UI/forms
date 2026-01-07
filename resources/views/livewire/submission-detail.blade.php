<div>
    @if ($this->submission)
        <!-- Header with Navigation and Actions -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
            <div class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <!-- Left: Back link and title -->
                    <div>
                        <a href="{{ route('forms.submissions.index', $this->form->slug) }}"
                           class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-2">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to submissions
                        </a>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                            {{ $this->submission->submission_number }}
                            @if ($this->submission->is_starred)
                                <svg class="h-5 w-5 text-yellow-400 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            @endif
                            @if ($this->submission->is_spam)
                                <span class="inline-flex items-center px-2 py-0.5 ml-2 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    Spam
                                </span>
                            @endif
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->form->name }}
                        </p>
                    </div>

                    <!-- Right: Navigation and Actions -->
                    <div class="flex items-center gap-4">
                        <!-- Navigation Arrows -->
                        <div class="flex items-center gap-2">
                            <button wire:click="goToPrevious"
                                    @if (!$this->previousSubmission) disabled @endif
                                    class="p-2 rounded-md {{ $this->previousSubmission ? 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' : 'text-gray-300 dark:text-gray-600 cursor-not-allowed' }}"
                                    title="Previous submission"
                                    aria-label="Go to previous submission">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <button wire:click="goToNext"
                                    @if (!$this->nextSubmission) disabled @endif
                                    class="p-2 rounded-md {{ $this->nextSubmission ? 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' : 'text-gray-300 dark:text-gray-600 cursor-not-allowed' }}"
                                    title="Next submission"
                                    aria-label="Go to next submission">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2">
                            <!-- Star Toggle -->
                            <button wire:click="toggleStar"
                                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ $this->submission->is_starred ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }} hover:bg-opacity-80 transition"
                                    title="{{ $this->submission->is_starred ? 'Unstar' : 'Star' }}">
                                <svg class="w-4 h-4 mr-1" fill="{{ $this->submission->is_starred ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                                {{ $this->submission->is_starred ? 'Starred' : 'Star' }}
                            </button>

                            <!-- Spam Toggle -->
                            <button wire:click="toggleSpam"
                                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ $this->submission->is_spam ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }} hover:bg-opacity-80 transition"
                                    title="{{ $this->submission->is_spam ? 'Not spam' : 'Mark as spam' }}">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                {{ $this->submission->is_spam ? 'Spam' : 'Mark Spam' }}
                            </button>

                            <!-- Delete Button -->
                            <button wire:click="delete"
                                    wire:confirm="Are you sure you want to delete this submission? This cannot be undone."
                                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition"
                                    title="Delete submission">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content: Submission Data -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Submission Data Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Submission Data</h3>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->submission->values as $value)
                            <div class="px-4 py-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ $value->field_label }}
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                    @if ($value->upload_id && $value->upload)
                                        <!-- File Upload -->
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            <a href="{{ route('forms.uploads.download', $value->upload) }}"
                                               class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline"
                                               target="_blank">
                                                {{ $value->upload->original_name }}
                                            </a>
                                            <span class="text-gray-400 text-xs">
                                                ({{ $value->upload->humanFileSize() }})
                                            </span>
                                        </div>
                                    @elseif ($value->value_array)
                                        <!-- Array Value (checkbox groups, multi-select) -->
                                        <ul class="list-disc list-inside space-y-1">
                                            @foreach ($value->value_array as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif ($value->value !== null && $value->value !== '')
                                        <!-- Regular Value -->
                                        @if ($value->field_type === 'textarea')
                                            <div class="whitespace-pre-wrap">{{ $value->value }}</div>
                                        @elseif ($value->field_type === 'email')
                                            <a href="mailto:{{ $value->value }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline">
                                                {{ $value->value }}
                                            </a>
                                        @elseif ($value->field_type === 'url')
                                            <a href="{{ $value->value }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline">
                                                {{ $value->value }}
                                            </a>
                                        @elseif ($value->field_type === 'phone')
                                            <a href="tel:{{ $value->value }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline">
                                                {{ $value->value }}
                                            </a>
                                        @else
                                            {{ $value->value }}
                                        @endif
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 italic">No response</span>
                                    @endif
                                </dd>
                            </div>
                        @empty
                            <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No data submitted.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Admin Notes -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Admin Notes</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Internal notes about this submission (auto-saved)</p>
                    </div>
                    <div class="px-4 py-5 sm:px-6">
                        <textarea wire:model.live.debounce.500ms="adminNotes"
                                  rows="4"
                                  class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:text-white"
                                  placeholder="Add notes about this submission..."></textarea>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <span wire:loading.remove wire:target="adminNotes">Notes are saved automatically</span>
                            <span wire:loading wire:target="adminNotes">Saving...</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Metadata -->
            <div class="space-y-6">
                <!-- Metadata Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Details</h3>
                    </div>
                    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Submitted Date/Time -->
                        <div class="px-4 py-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ $this->submission->created_at->format('F j, Y') }}
                                <br>
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ $this->submission->created_at->format('g:i A') }}
                                    ({{ $this->submission->created_at->diffForHumans() }})
                                </span>
                            </dd>
                        </div>

                        <!-- Status -->
                        <div class="px-4 py-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1 text-sm">
                                @if ($this->submission->is_read)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Read
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Unread
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <!-- Page URL -->
                        @if ($this->submission->page_url)
                            <div class="px-4 py-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Page URL</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white break-all">
                                    <a href="{{ $this->submission->page_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline">
                                        {{ \Illuminate\Support\Str::limit($this->submission->page_url, 50) }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        <!-- Referrer URL -->
                        @if ($this->submission->referrer_url)
                            <div class="px-4 py-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Referrer</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white break-all">
                                    <a href="{{ $this->submission->referrer_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 underline">
                                        {{ \Illuminate\Support\Str::limit($this->submission->referrer_url, 50) }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        <!-- IP Address -->
                        @if ($this->submission->ip_address)
                            <div class="px-4 py-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">
                                    {{ $this->submission->ip_address }}
                                </dd>
                            </div>
                        @endif

                        <!-- User Agent -->
                        @if ($this->submission->user_agent)
                            <div class="px-4 py-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">User Agent</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white break-all text-xs">
                                    {{ $this->submission->user_agent }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    @else
        <!-- Submission Not Found -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Submission not found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                The submission you're looking for doesn't exist or has been deleted.
            </p>
        </div>
    @endif
</div>
