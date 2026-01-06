<div class="notification-editor flex gap-6">
    {{-- Sidebar: Notification List --}}
    <div class="w-72 shrink-0">
        <div class="rounded-box bg-base-200 p-4">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">
                    Notifications
                </h3>
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-xs btn-primary">
                        <x-artisanpack-icon name="o-plus" class="h-3 w-3" />
                        Add
                    </label>
                    <ul tabindex="0" class="dropdown-content menu rounded-box z-50 mt-1 w-56 bg-base-100 p-2 shadow-lg">
                        @foreach ($this->notificationTypes as $type => $config)
                            <li>
                                <button
                                    type="button"
                                    wire:click="addNotification('{{ $type }}')"
                                    class="flex items-start gap-3"
                                >
                                    <x-artisanpack-icon name="{{ $config['icon'] }}" class="mt-0.5 h-4 w-4" />
                                    <div class="text-left">
                                        <div class="font-medium">{{ $config['label'] }}</div>
                                        <div class="text-xs text-base-content/60">{{ $config['description'] }}</div>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Notification List --}}
            @if ($this->notifications->isNotEmpty())
                <ul
                    class="space-y-2"
                    x-data
                    x-sortable
                    x-on:sortable-end="$wire.dispatch('notifications-reordered', { orderedIds: $event.detail.items.map(i => parseInt(i)) })"
                >
                    @foreach ($this->notifications as $notification)
                        <li
                            x-sortable-item="{{ $notification->id }}"
                            wire:key="notification-{{ $notification->id }}"
                            class="group"
                        >
                            <button
                                type="button"
                                wire:click="selectNotification({{ $notification->id }})"
                                class="flex w-full items-center gap-3 rounded-lg p-3 text-left transition-colors {{ $selectedNotificationId === $notification->id ? 'bg-primary/10 ring-1 ring-primary' : 'bg-base-100 hover:bg-base-300' }}"
                            >
                                {{-- Drag handle --}}
                                <span x-sortable-handle class="cursor-grab text-base-content/40 hover:text-base-content/60">
                                    <x-artisanpack-icon name="o-bars-2" class="h-4 w-4" />
                                </span>

                                {{-- Icon --}}
                                @php
                                    $typeConfig = $this->notificationTypes[$notification->type] ?? null;
                                @endphp
                                <x-artisanpack-icon
                                    name="{{ $typeConfig['icon'] ?? 'o-envelope' }}"
                                    class="h-5 w-5 {{ $notification->is_active ? 'text-primary' : 'text-base-content/40' }}"
                                />

                                {{-- Info --}}
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium {{ !$notification->is_active ? 'text-base-content/50' : '' }}">
                                        {{ $notification->name }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="badge badge-xs {{ $notification->type === 'admin' ? 'badge-primary' : ($notification->type === 'autoresponder' ? 'badge-secondary' : 'badge-neutral') }}">
                                            {{ ucfirst($notification->type) }}
                                        </span>
                                        @if (!$notification->is_active)
                                            <span class="badge badge-xs badge-ghost">Inactive</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        type="button"
                                        wire:click.stop="toggleActive({{ $notification->id }})"
                                        class="btn btn-ghost btn-xs"
                                        title="{{ $notification->is_active ? 'Deactivate' : 'Activate' }}"
                                    >
                                        <x-artisanpack-icon
                                            name="{{ $notification->is_active ? 'o-eye' : 'o-eye-slash' }}"
                                            class="h-4 w-4"
                                        />
                                    </button>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="py-8 text-center text-base-content/50">
                    <x-artisanpack-icon name="o-bell-slash" class="mx-auto mb-3 h-10 w-10" />
                    <p class="text-sm">No notifications yet</p>
                    <p class="text-xs">Add a notification to get started</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Main Editor Panel --}}
    <div class="flex-1">
        @if ($this->selectedNotification)
            @php
                $notification = $this->selectedNotification;
                $typeConfig = $this->notificationTypes[$notification->type] ?? null;
            @endphp

            <div class="rounded-box bg-base-200 p-6">
                {{-- Header --}}
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <x-artisanpack-icon name="{{ $typeConfig['icon'] ?? 'o-envelope' }}" class="h-6 w-6 text-primary" />
                        <div>
                            <h2 class="text-lg font-semibold">{{ $notification->name }}</h2>
                            <p class="text-sm text-base-content/60">{{ $typeConfig['description'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="duplicateNotification({{ $notification->id }})"
                            class="btn btn-ghost btn-sm"
                            title="Duplicate"
                        >
                            <x-artisanpack-icon name="o-document-duplicate" class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            wire:click="deleteNotification({{ $notification->id }})"
                            wire:confirm="Are you sure you want to delete this notification?"
                            class="btn btn-ghost btn-sm text-error"
                            title="Delete"
                        >
                            <x-artisanpack-icon name="o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {{-- Tabbed Editor --}}
                <div x-data="{ activeTab: 'basic' }" class="space-y-6">
                    {{-- Tab Buttons --}}
                    <div class="tabs tabs-boxed bg-base-300">
                        <button type="button" @click="activeTab = 'basic'" :class="activeTab === 'basic' ? 'tab-active' : ''" class="tab">
                            Basic
                        </button>
                        <button type="button" @click="activeTab = 'recipients'" :class="activeTab === 'recipients' ? 'tab-active' : ''" class="tab">
                            Recipients
                        </button>
                        <button type="button" @click="activeTab = 'sender'" :class="activeTab === 'sender' ? 'tab-active' : ''" class="tab">
                            Sender
                        </button>
                        <button type="button" @click="activeTab = 'content'" :class="activeTab === 'content' ? 'tab-active' : ''" class="tab">
                            Content
                        </button>
                        <button type="button" @click="activeTab = 'conditions'" :class="activeTab === 'conditions' ? 'tab-active' : ''" class="tab">
                            Conditions
                        </button>
                    </div>

                    {{-- Basic Tab --}}
                    <div x-show="activeTab === 'basic'" x-cloak class="space-y-4">
                        {{-- Notification Name --}}
                        <div class="form-control">
                            <label class="label" for="notification-name">
                                <span class="label-text">Notification Name</span>
                                <span class="label-text-alt text-base-content/50">Internal use only</span>
                            </label>
                            <input
                                type="text"
                                id="notification-name"
                                class="input input-bordered w-full"
                                value="{{ $notification->name }}"
                                wire:change="updateNotification({ name: $event.target.value })"
                            />
                        </div>

                        {{-- Type --}}
                        <div class="form-control">
                            <label class="label" for="notification-type">
                                <span class="label-text">Type</span>
                            </label>
                            <select
                                id="notification-type"
                                class="select select-bordered w-full"
                                wire:change="updateNotification({ type: $event.target.value })"
                            >
                                @foreach ($this->notificationTypes as $type => $config)
                                    <option value="{{ $type }}" {{ $notification->type === $type ? 'selected' : '' }}>
                                        {{ $config['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Active Toggle --}}
                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-4">
                                <input
                                    type="checkbox"
                                    class="toggle toggle-primary"
                                    {{ $notification->is_active ? 'checked' : '' }}
                                    wire:change="updateNotification({ is_active: $event.target.checked })"
                                />
                                <div>
                                    <span class="label-text font-medium">Active</span>
                                    <p class="text-xs text-base-content/60">Enable or disable this notification</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Recipients Tab --}}
                    <div x-show="activeTab === 'recipients'" x-cloak class="space-y-4">
                        {{-- To Email(s) --}}
                        <div class="form-control">
                            <label class="label" for="notification-to-email">
                                <span class="label-text">To Email(s)</span>
                                <span class="label-text-alt text-base-content/50">Comma-separated</span>
                            </label>
                            <input
                                type="text"
                                id="notification-to-email"
                                class="input input-bordered w-full"
                                value="{{ $notification->to_email }}"
                                placeholder="admin@example.com, team@example.com"
                                wire:change="updateNotification({ to_email: $event.target.value })"
                            />
                        </div>

                        {{-- To Field (Dynamic) --}}
                        @if ($this->emailFields->isNotEmpty())
                            <div class="form-control">
                                <label class="label" for="notification-to-field">
                                    <span class="label-text">Or send to email field</span>
                                    <span class="label-text-alt text-base-content/50">Dynamic recipient</span>
                                </label>
                                <select
                                    id="notification-to-field"
                                    class="select select-bordered w-full"
                                    wire:change="updateNotification({ to_field: $event.target.value || null })"
                                >
                                    <option value="">-- Select an email field --</option>
                                    @foreach ($this->emailFields as $field)
                                        <option value="{{ $field->name }}" {{ $notification->to_field === $field->name ? 'selected' : '' }}>
                                            {{ $field->label }} ({!! e($field->name) !!})
                                        </option>
                                    @endforeach
                                </select>
                                <label class="label">
                                    <span class="label-text-alt text-base-content/50">Email will be sent to the address entered in this field</span>
                                </label>
                            </div>
                        @endif

                        <div class="divider text-xs text-base-content/50">Additional Recipients</div>

                        {{-- CC Emails --}}
                        <div class="form-control">
                            <label class="label" for="notification-cc">
                                <span class="label-text">CC</span>
                                <span class="label-text-alt text-base-content/50">Comma-separated</span>
                            </label>
                            <input
                                type="text"
                                id="notification-cc"
                                class="input input-bordered w-full"
                                value="{{ $notification->cc_emails }}"
                                placeholder="manager@example.com"
                                wire:change="updateNotification({ cc_emails: $event.target.value })"
                            />
                        </div>

                        {{-- BCC Emails --}}
                        <div class="form-control">
                            <label class="label" for="notification-bcc">
                                <span class="label-text">BCC</span>
                                <span class="label-text-alt text-base-content/50">Comma-separated</span>
                            </label>
                            <input
                                type="text"
                                id="notification-bcc"
                                class="input input-bordered w-full"
                                value="{{ $notification->bcc_emails }}"
                                placeholder="archive@example.com"
                                wire:change="updateNotification({ bcc_emails: $event.target.value })"
                            />
                        </div>

                        <div class="divider text-xs text-base-content/50">Reply-To</div>

                        {{-- Reply-To Email --}}
                        <div class="form-control">
                            <label class="label" for="notification-reply-to-email">
                                <span class="label-text">Reply-To Email</span>
                            </label>
                            <input
                                type="email"
                                id="notification-reply-to-email"
                                class="input input-bordered w-full"
                                value="{{ $notification->reply_to_email }}"
                                placeholder="support@example.com"
                                wire:change="updateNotification({ reply_to_email: $event.target.value })"
                            />
                        </div>

                        {{-- Reply-To Field (Dynamic) --}}
                        @if ($this->emailFields->isNotEmpty())
                            <div class="form-control">
                                <label class="label" for="notification-reply-to-field">
                                    <span class="label-text">Or use email field as Reply-To</span>
                                </label>
                                <select
                                    id="notification-reply-to-field"
                                    class="select select-bordered w-full"
                                    wire:change="updateNotification({ reply_to_field: $event.target.value || null })"
                                >
                                    <option value="">-- Select an email field --</option>
                                    @foreach ($this->emailFields as $field)
                                        <option value="{{ $field->name }}" {{ $notification->reply_to_field === $field->name ? 'selected' : '' }}>
                                            {{ $field->label }} ({!! e($field->name) !!})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    {{-- Sender Tab --}}
                    <div x-show="activeTab === 'sender'" x-cloak class="space-y-4">
                        {{-- From Name --}}
                        <div class="form-control">
                            <label class="label" for="notification-from-name">
                                <span class="label-text">From Name</span>
                            </label>
                            <input
                                type="text"
                                id="notification-from-name"
                                class="input input-bordered w-full"
                                value="{{ $notification->from_name }}"
                                placeholder="{{ config('app.name') }}"
                                wire:change="updateNotification({ from_name: $event.target.value })"
                            />
                            <label class="label">
                                <span class="label-text-alt text-base-content/50">Defaults to app name if empty</span>
                            </label>
                        </div>

                        {{-- From Email --}}
                        <div class="form-control">
                            <label class="label" for="notification-from-email">
                                <span class="label-text">From Email</span>
                            </label>
                            <input
                                type="email"
                                id="notification-from-email"
                                class="input input-bordered w-full"
                                value="{{ $notification->from_email }}"
                                placeholder="{{ config('mail.from.address') }}"
                                wire:change="updateNotification({ from_email: $event.target.value })"
                            />
                            <label class="label">
                                <span class="label-text-alt text-base-content/50">Defaults to mail config if empty</span>
                            </label>
                        </div>
                    </div>

                    {{-- Content Tab --}}
                    <div x-show="activeTab === 'content'" x-cloak class="space-y-4">
                        {{-- Subject --}}
                        <div class="form-control">
                            <label class="label" for="notification-subject">
                                <span class="label-text">Subject Line</span>
                            </label>
                            <input
                                type="text"
                                id="notification-subject"
                                class="input input-bordered w-full"
                                value="{{ $notification->subject }}"
                                placeholder="New Submission: {form_name}"
                                wire:change="updateNotification({ subject: $event.target.value })"
                            />
                        </div>

                        {{-- Message --}}
                        <div class="form-control">
                            <label class="label" for="notification-message">
                                <span class="label-text">Message</span>
                            </label>
                            <textarea
                                id="notification-message"
                                class="textarea textarea-bordered w-full font-mono text-sm"
                                rows="8"
                                placeholder="Enter your message..."
                                wire:change="updateNotification({ message: $event.target.value })"
                            >{{ $notification->message }}</textarea>
                        </div>

                        {{-- Available Placeholders --}}
                        <div class="collapse collapse-arrow rounded-lg bg-base-300">
                            <input type="checkbox" />
                            <div class="collapse-title text-sm font-medium">
                                <x-artisanpack-icon name="o-code-bracket" class="mr-2 inline-block h-4 w-4" />
                                Available Placeholders
                            </div>
                            <div class="collapse-content">
                                <div class="grid grid-cols-2 gap-2 pt-2">
                                    @foreach ($this->availablePlaceholders as $placeholder => $description)
                                        <div class="flex items-start gap-2 text-xs">
                                            <code class="rounded bg-base-100 px-1.5 py-0.5 font-mono text-primary">{{ $placeholder }}</code>
                                            <span class="text-base-content/60">{{ $description }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Include Submission Data --}}
                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-4">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-primary"
                                    {{ $notification->include_submission_data ? 'checked' : '' }}
                                    wire:change="updateNotification({ include_submission_data: $event.target.checked })"
                                />
                                <div>
                                    <span class="label-text font-medium">Include submission data</span>
                                    <p class="text-xs text-base-content/60">Append a table of all submitted field values</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Conditions Tab --}}
                    <div x-show="activeTab === 'conditions'" x-cloak class="space-y-4">
                        @include('forms::livewire.notification-editor.condition-builder', ['notification' => $notification])
                    </div>
                </div>
            </div>
        @else
            {{-- No notification selected --}}
            <div class="flex h-96 flex-col items-center justify-center rounded-box bg-base-200">
                <x-artisanpack-icon name="o-bell" class="mb-4 h-16 w-16 text-base-content/20" />
                <p class="mb-2 text-lg text-base-content/60">No notification selected</p>
                <p class="text-sm text-base-content/40">
                    Select a notification from the list or add a new one
                </p>
            </div>
        @endif
    </div>
</div>
