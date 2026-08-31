<div class="notification-editor flex gap-6">
    {{-- Sidebar: Notification List --}}
    <div class="w-72 shrink-0">
        <x-artisanpack-card class="p-4">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70">
                    {{ __( 'Notifications' ) }}
                </h3>
                <x-artisanpack-dropdown>
                    <x-slot:trigger>
                        <x-artisanpack-button color="primary" class="btn-xs" icon="o-plus" :label="__( 'Add' )" />
                    </x-slot:trigger>
                    <x-artisanpack-menu class="w-56">
                        @foreach ($this->notificationTypes as $type => $config)
                            <x-artisanpack-menu-item
                                wire:click="addNotification('{{ $type }}')"
                                :icon="$config['icon']"
                            >
                                <div class="text-left">
                                    <div class="font-medium">{{ $config['label'] }}</div>
                                    <div class="text-xs opacity-60">{{ $config['description'] }}</div>
                                </div>
                            </x-artisanpack-menu-item>
                        @endforeach
                    </x-artisanpack-menu>
                </x-artisanpack-dropdown>
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
                                class="flex w-full cursor-pointer items-center gap-3 rounded-lg p-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary {{ $selectedNotificationId === $notification->id ? 'bg-primary/10 ring-1 ring-primary' : 'bg-base-100 hover:bg-base-300' }}"
                            >
                                {{-- Drag handle --}}
                                <span x-sortable-handle class="cursor-grab opacity-40 hover:opacity-60">
                                    <x-artisanpack-icon name="o-bars-2" class="h-4 w-4" />
                                </span>

                                {{-- Icon --}}
                                @php
                                    $typeConfig = $this->notificationTypes[$notification->type] ?? null;
                                @endphp
                                <x-artisanpack-icon
                                    name="{{ $typeConfig['icon'] ?? 'o-envelope' }}"
                                    class="h-5 w-5 {{ $notification->is_active ? 'text-primary' : 'opacity-40' }}"
                                />

                                {{-- Info --}}
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium {{ !$notification->is_active ? 'opacity-50' : '' }}">
                                        {{ $notification->name }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-artisanpack-badge
                                            :value="ucfirst($notification->type)"
                                            :color="$notification->type === 'admin' ? 'primary' : ($notification->type === 'autoresponder' ? 'secondary' : 'neutral')"
                                            class="badge-xs"
                                        />
                                        @if (!$notification->is_active)
                                            <x-artisanpack-badge :value="__( 'Inactive' )" color="ghost" class="badge-xs" />
                                        @endif
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    <x-artisanpack-button
                                        wire:click.stop="toggleActive({{ $notification->id }})"
                                        :icon="$notification->is_active ? 'o-eye' : 'o-eye-slash'"
                                        color="ghost"
                                        class="btn-xs"
                                        :title="$notification->is_active ? __( 'Deactivate' ) : __( 'Activate' )"
                                    />
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="py-8 text-center opacity-50">
                    <x-artisanpack-icon name="o-bell-slash" class="mx-auto mb-3 h-10 w-10" />
                    <p class="text-sm">{{ __( 'No notifications yet' ) }}</p>
                    <p class="text-xs">{{ __( 'Add a notification to get started' ) }}</p>
                </div>
            @endif
        </x-artisanpack-card>
    </div>

    {{-- Main Editor Panel --}}
    <div class="flex-1">
        @if ($this->selectedNotification)
            @php
                $notification = $this->selectedNotification;
                $typeConfig = $this->notificationTypes[$notification->type] ?? null;
            @endphp

            <x-artisanpack-card class="p-6">
                {{-- Header --}}
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <x-artisanpack-icon name="{{ $typeConfig['icon'] ?? 'o-envelope' }}" class="h-6 w-6 text-primary" />
                        <div>
                            <h2 class="text-lg font-semibold">{{ $notification->name }}</h2>
                            <p class="text-sm opacity-60">{{ $typeConfig['description'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-artisanpack-button
                            wire:click="duplicateNotification({{ $notification->id }})"
                            icon="o-document-duplicate"
                            color="ghost"
                            class="btn-sm"
                            :title="__( 'Duplicate' )"
                        />
                        <x-artisanpack-button
                            wire:click="deleteNotification({{ $notification->id }})"
                            :wire:confirm="__( 'Are you sure you want to delete this notification?' )"
                            icon="o-trash"
                            color="ghost"
                            class="btn-sm text-error"
                            :title="__( 'Delete' )"
                        />
                    </div>
                </div>

                {{-- Tabbed Editor --}}
                <div x-data="{ activeTab: 'basic' }" class="space-y-6">
                    {{-- Tab Buttons --}}
                    <div class="tabs tabs-boxed bg-base-300">
                        <button type="button" @click="activeTab = 'basic'" :class="activeTab === 'basic' ? 'tab-active' : ''" class="tab">
                            {{ __( 'Basic' ) }}
                        </button>
                        <button type="button" @click="activeTab = 'recipients'" :class="activeTab === 'recipients' ? 'tab-active' : ''" class="tab">
                            {{ __( 'Recipients' ) }}
                        </button>
                        <button type="button" @click="activeTab = 'sender'" :class="activeTab === 'sender' ? 'tab-active' : ''" class="tab">
                            {{ __( 'Sender' ) }}
                        </button>
                        <button type="button" @click="activeTab = 'content'" :class="activeTab === 'content' ? 'tab-active' : ''" class="tab">
                            {{ __( 'Content' ) }}
                        </button>
                        <button type="button" @click="activeTab = 'conditions'" :class="activeTab === 'conditions' ? 'tab-active' : ''" class="tab">
                            {{ __( 'Conditions' ) }}
                        </button>
                    </div>

                    {{-- Basic Tab --}}
                    <div x-show="activeTab === 'basic'" x-cloak class="space-y-4">
                        <x-artisanpack-input
                            :label="__( 'Notification Name' )"
                            :hint="__( 'Internal use only' )"
                            :value="$notification->name"
                            wire:change="updateNotification({ name: $event.target.value })"
                        />

                        <x-artisanpack-select
                            :label="__( 'Type' )"
                            :options="collect($this->notificationTypes)->map(fn($c, $t) => ['value' => $t, 'label' => $c['label']])->values()->toArray()"
                            option-value="value"
                            option-label="label"
                            :value="$notification->type"
                            wire:change="updateNotification({ type: $event.target.value })"
                        />

                        <x-artisanpack-toggle
                            :label="__( 'Active' )"
                            :hint="__( 'Enable or disable this notification' )"
                            :checked="$notification->is_active"
                            wire:change="updateNotification({ is_active: $event.target.checked })"
                        />
                    </div>

                    {{-- Recipients Tab --}}
                    <div x-show="activeTab === 'recipients'" x-cloak class="space-y-4">
                        <x-artisanpack-input
                            :label="__( 'To Email(s)' )"
                            :hint="__( 'Comma-separated' )"
                            :value="$notification->to_email"
                            placeholder="admin@example.com, team@example.com"
                            wire:change="updateNotification({ to_email: $event.target.value })"
                        />

                        @if ($this->emailFields->isNotEmpty())
                            <x-artisanpack-select
                                :label="__( 'Or send to email field' )"
                                :hint="__( 'Dynamic recipient - Email will be sent to the address entered in this field' )"
                                :placeholder="__( '-- Select an email field --' )"
                                :options="$this->emailFields->map(fn($f) => ['value' => $f->name, 'label' => $f->label . ' (' . $f->name . ')'])->toArray()"
                                option-value="value"
                                option-label="label"
                                :value="$notification->to_field"
                                wire:change="updateNotification({ to_field: $event.target.value || null })"
                            />
                        @endif

                        <div class="divider text-xs opacity-50">{{ __( 'Additional Recipients' ) }}</div>

                        <x-artisanpack-input
                            :label="__( 'CC' )"
                            :hint="__( 'Comma-separated' )"
                            :value="$notification->cc_emails"
                            placeholder="manager@example.com"
                            wire:change="updateNotification({ cc_emails: $event.target.value })"
                        />

                        <x-artisanpack-input
                            :label="__( 'BCC' )"
                            :hint="__( 'Comma-separated' )"
                            :value="$notification->bcc_emails"
                            placeholder="archive@example.com"
                            wire:change="updateNotification({ bcc_emails: $event.target.value })"
                        />

                        <div class="divider text-xs opacity-50">{{ __( 'Reply-To' ) }}</div>

                        <x-artisanpack-input
                            type="email"
                            :label="__( 'Reply-To Email' )"
                            :value="$notification->reply_to_email"
                            placeholder="support@example.com"
                            wire:change="updateNotification({ reply_to_email: $event.target.value })"
                        />

                        @if ($this->emailFields->isNotEmpty())
                            <x-artisanpack-select
                                :label="__( 'Or use email field as Reply-To' )"
                                :placeholder="__( '-- Select an email field --' )"
                                :options="$this->emailFields->map(fn($f) => ['value' => $f->name, 'label' => $f->label . ' (' . $f->name . ')'])->toArray()"
                                option-value="value"
                                option-label="label"
                                :value="$notification->reply_to_field"
                                wire:change="updateNotification({ reply_to_field: $event.target.value || null })"
                            />
                        @endif
                    </div>

                    {{-- Sender Tab --}}
                    <div x-show="activeTab === 'sender'" x-cloak class="space-y-4">
                        <x-artisanpack-input
                            :label="__( 'From Name' )"
                            :hint="__( 'Defaults to app name if empty' )"
                            :value="$notification->from_name"
                            :placeholder="config('app.name')"
                            wire:change="updateNotification({ from_name: $event.target.value })"
                        />

                        <x-artisanpack-input
                            type="email"
                            :label="__( 'From Email' )"
                            :hint="__( 'Defaults to mail config if empty' )"
                            :value="$notification->from_email"
                            :placeholder="config('mail.from.address')"
                            wire:change="updateNotification({ from_email: $event.target.value })"
                        />
                    </div>

                    {{-- Content Tab --}}
                    <div x-show="activeTab === 'content'" x-cloak class="space-y-4">
                        <x-artisanpack-input
                            :label="__( 'Subject Line' )"
                            :value="$notification->subject"
                            :placeholder="__( 'New Submission: {form_name}' )"
                            wire:change="updateNotification({ subject: $event.target.value })"
                        />

                        <x-artisanpack-textarea
                            :label="__( 'Message' )"
                            rows="8"
                            class="font-mono text-sm"
                            :placeholder="__( 'Enter your message...' )"
                            wire:change="updateNotification({ message: $event.target.value })"
                        >{{ $notification->message }}</x-artisanpack-textarea>

                        {{-- Available Placeholders --}}
                        <x-artisanpack-collapse :title="__( 'Available Placeholders' )">
                            <x-slot:icon>
                                <x-artisanpack-icon name="o-code-bracket" class="h-4 w-4" />
                            </x-slot:icon>
                            <div class="grid grid-cols-2 gap-2 pt-2">
                                @foreach ($this->availablePlaceholders as $placeholder => $description)
                                    <div class="flex items-start gap-2 text-xs">
                                        <code class="rounded bg-base-100 px-1.5 py-0.5 font-mono text-primary">{{ $placeholder }}</code>
                                        <span class="opacity-60">{{ $description }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </x-artisanpack-collapse>

                        <x-artisanpack-checkbox
                            :label="__( 'Include submission data' )"
                            :hint="__( 'Append a table of all submitted field values' )"
                            :checked="$notification->include_submission_data"
                            wire:change="updateNotification({ include_submission_data: $event.target.checked })"
                        />
                    </div>

                    {{-- Conditions Tab --}}
                    <div x-show="activeTab === 'conditions'" x-cloak class="space-y-4">
                        @include('forms::livewire.notification-editor.condition-builder', ['notification' => $notification])
                    </div>
                </div>
            </x-artisanpack-card>
        @else
            {{-- No notification selected --}}
            <x-artisanpack-card class="flex h-96 flex-col items-center justify-center">
                <x-artisanpack-icon name="o-bell" class="mb-4 h-16 w-16 opacity-20" />
                <p class="mb-2 text-lg opacity-60">{{ __( 'No notification selected' ) }}</p>
                <p class="text-sm opacity-40">
                    {{ __( 'Select a notification from the list or add a new one' ) }}
                </p>
            </x-artisanpack-card>
        @endif
    </div>
</div>
