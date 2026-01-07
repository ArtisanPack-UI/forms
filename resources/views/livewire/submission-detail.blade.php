<div>
    @if ($this->submission)
        <!-- Header with Navigation and Actions -->
        <x-artisanpack-card class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <!-- Left: Back link and title -->
                <div>
                    <a href="{{ route('forms.submissions.index', $this->form->slug) }}"
                       class="inline-flex items-center text-sm opacity-60 hover:opacity-100 mb-2">
                        <x-artisanpack-icon name="o-arrow-left" class="w-4 h-4 mr-1" />
                        Back to submissions
                    </a>
                    <h2 class="text-lg font-semibold flex items-center gap-2">
                        {{ $this->submission->submission_number }}
                        @if ($this->submission->is_starred)
                            <x-artisanpack-icon name="s-star" class="w-5 h-5 text-warning" />
                        @endif
                        @if ($this->submission->is_spam)
                            <x-artisanpack-badge value="Spam" color="error" class="badge-sm" />
                        @endif
                    </h2>
                    <p class="text-sm opacity-60">{{ $this->form->name }}</p>
                </div>

                <!-- Right: Navigation and Actions -->
                <div class="flex items-center gap-4">
                    <!-- Navigation Arrows -->
                    <div class="flex items-center gap-1">
                        <x-artisanpack-button
                            wire:click="goToPrevious"
                            icon="o-chevron-left"
                            color="ghost"
                            class="btn-sm"
                            :disabled="!$this->previousSubmission"
                            title="Previous submission"
                        />
                        <x-artisanpack-button
                            wire:click="goToNext"
                            icon="o-chevron-right"
                            color="ghost"
                            class="btn-sm"
                            :disabled="!$this->nextSubmission"
                            title="Next submission"
                        />
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2">
                        <x-artisanpack-button
                            wire:click="toggleStar"
                            :icon="$this->submission->is_starred ? 's-star' : 'o-star'"
                            :label="$this->submission->is_starred ? 'Starred' : 'Star'"
                            :color="$this->submission->is_starred ? 'warning' : 'ghost'"
                            class="btn-sm"
                        />
                        <x-artisanpack-button
                            wire:click="toggleSpam"
                            icon="o-exclamation-triangle"
                            :label="$this->submission->is_spam ? 'Spam' : 'Mark Spam'"
                            :color="$this->submission->is_spam ? 'error' : 'ghost'"
                            class="btn-sm"
                        />
                        <x-artisanpack-button
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this submission? This cannot be undone."
                            icon="o-trash"
                            label="Delete"
                            color="error"
                            class="btn-sm"
                        />
                    </div>
                </div>
            </div>
        </x-artisanpack-card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content: Submission Data -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Submission Data Card -->
                <x-artisanpack-card title="Submission Data" separator>
                    <div class="divide-y divide-base-300">
                        @forelse ($this->submission->values as $value)
                            <div class="py-4 first:pt-0 last:pb-0">
                                <dt class="text-sm font-medium opacity-60">{{ $value->field_label }}</dt>
                                <dd class="mt-1 text-sm">
                                    @if ($value->upload_id && $value->upload)
                                        <!-- File Upload -->
                                        <div class="flex items-center gap-2">
                                            <x-artisanpack-icon name="o-paper-clip" class="w-5 h-5 opacity-40" />
                                            <a href="{{ route('forms.uploads.download', $value->upload) }}"
                                               class="link link-primary"
                                               target="_blank">
                                                {{ $value->upload->original_name }}
                                            </a>
                                            <span class="opacity-40 text-xs">({{ $value->upload->humanFileSize() }})</span>
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
                                            <a href="mailto:{{ $value->value }}" class="link link-primary">{{ $value->value }}</a>
                                        @elseif ($value->field_type === 'url')
                                            <a href="{{ $value->value }}" target="_blank" rel="noopener" class="link link-primary">{{ $value->value }}</a>
                                        @elseif ($value->field_type === 'phone')
                                            <a href="tel:{{ $value->value }}" class="link link-primary">{{ $value->value }}</a>
                                        @else
                                            {{ $value->value }}
                                        @endif
                                    @else
                                        <span class="italic opacity-40">No response</span>
                                    @endif
                                </dd>
                            </div>
                        @empty
                            <div class="py-8 text-center opacity-60">No data submitted.</div>
                        @endforelse
                    </div>
                </x-artisanpack-card>

                <!-- Admin Notes -->
                <x-artisanpack-card title="Admin Notes" separator>
                    <x-slot:subtitle>Internal notes about this submission (auto-saved)</x-slot:subtitle>
                    <x-artisanpack-textarea
                        wire:model.live.debounce.500ms="adminNotes"
                        rows="4"
                        placeholder="Add notes about this submission..."
                    />
                    <p class="mt-2 text-xs opacity-60">
                        <span wire:loading.remove wire:target="adminNotes">Notes are saved automatically</span>
                        <span wire:loading wire:target="adminNotes">Saving...</span>
                    </p>
                </x-artisanpack-card>
            </div>

            <!-- Sidebar: Metadata -->
            <div class="space-y-6">
                <!-- Metadata Card -->
                <x-artisanpack-card title="Details" separator>
                    <dl class="space-y-4">
                        <!-- Submitted Date/Time -->
                        <div>
                            <dt class="text-sm font-medium opacity-60">Submitted</dt>
                            <dd class="mt-1 text-sm">
                                {{ $this->submission->created_at->format('F j, Y') }}
                                <br>
                                <span class="opacity-60">
                                    {{ $this->submission->created_at->format('g:i A') }}
                                    ({{ $this->submission->created_at->diffForHumans() }})
                                </span>
                            </dd>
                        </div>

                        <!-- Status -->
                        <div>
                            <dt class="text-sm font-medium opacity-60">Status</dt>
                            <dd class="mt-1">
                                @if ($this->submission->is_read)
                                    <x-artisanpack-badge value="Read" color="success" />
                                @else
                                    <x-artisanpack-badge value="Unread" color="info" />
                                @endif
                            </dd>
                        </div>

                        <!-- Page URL -->
                        @if ($this->submission->page_url)
                            <div>
                                <dt class="text-sm font-medium opacity-60">Page URL</dt>
                                <dd class="mt-1 text-sm break-all">
                                    <a href="{{ $this->submission->page_url }}" target="_blank" rel="noopener" class="link link-primary">
                                        {{ \Illuminate\Support\Str::limit($this->submission->page_url, 50) }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        <!-- Referrer URL -->
                        @if ($this->submission->referrer_url)
                            <div>
                                <dt class="text-sm font-medium opacity-60">Referrer</dt>
                                <dd class="mt-1 text-sm break-all">
                                    <a href="{{ $this->submission->referrer_url }}" target="_blank" rel="noopener" class="link link-primary">
                                        {{ \Illuminate\Support\Str::limit($this->submission->referrer_url, 50) }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        <!-- IP Address -->
                        @if ($this->submission->ip_address)
                            <div>
                                <dt class="text-sm font-medium opacity-60">IP Address</dt>
                                <dd class="mt-1 text-sm font-mono">{{ $this->submission->ip_address }}</dd>
                            </div>
                        @endif

                        <!-- User Agent -->
                        @if ($this->submission->user_agent)
                            <div>
                                <dt class="text-sm font-medium opacity-60">User Agent</dt>
                                <dd class="mt-1 text-xs break-all">{{ $this->submission->user_agent }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-artisanpack-card>
            </div>
        </div>
    @else
        <!-- Submission Not Found -->
        <x-artisanpack-card class="p-12 text-center">
            <x-artisanpack-icon name="o-face-frown" class="mx-auto h-12 w-12 opacity-40" />
            <h3 class="mt-2 text-sm font-medium">Submission not found</h3>
            <p class="mt-1 text-sm opacity-60">
                The submission you're looking for doesn't exist or has been deleted.
            </p>
        </x-artisanpack-card>
    @endif
</div>
