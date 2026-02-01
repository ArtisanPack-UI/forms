---
title: NotificationEditor Component
---

# NotificationEditor Component

The NotificationEditor component provides an interface for configuring form email notifications.

## Basic Usage

```blade
<livewire:forms::notification-editor :form="$form" />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `form` | Form | required | Form to configure |

## Features

- Create admin and autoresponder notifications
- Configure recipients (static or from field)
- Set from address and reply-to
- Configure CC and BCC
- Edit subject and message with placeholders
- Set conditional send rules
- Test notifications

## Component Structure

```blade
<div class="notification-editor">
    {{-- Notifications List --}}
    <div class="notifications-list">
        @foreach ($form->notifications as $notification)
            <div class="notification-card">
                <div class="header">
                    <span class="type">{{ $notification->type }}</span>
                    <span class="name">{{ $notification->name }}</span>
                    <span class="status">{{ $notification->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <div class="actions">
                    <button wire:click="edit({{ $notification->id }})">Edit</button>
                    <button wire:click="toggleStatus({{ $notification->id }})">
                        {{ $notification->is_active ? 'Disable' : 'Enable' }}
                    </button>
                    <button wire:click="delete({{ $notification->id }})">Delete</button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Add Notification --}}
    <div class="add-notification">
        <button wire:click="addNotification('admin')">Add Admin Notification</button>
        <button wire:click="addNotification('autoresponder')">Add Autoresponder</button>
    </div>

    {{-- Edit Modal --}}
    @if ($editingNotification)
        <div class="notification-form">
            {{-- Basic Settings --}}
            <div class="section">
                <h3>Basic Settings</h3>
                <input wire:model="notification.name" placeholder="Notification Name">
                <label>
                    <input type="checkbox" wire:model="notification.is_active">
                    Active
                </label>
            </div>

            {{-- Recipients --}}
            <div class="section">
                <h3>Recipients</h3>
                <select wire:model.live="notification.recipient_type">
                    <option value="static">Static Email(s)</option>
                    <option value="field">From Form Field</option>
                </select>

                @if ($notification['recipient_type'] === 'static')
                    <input wire:model="notification.recipients" placeholder="email@example.com, email2@example.com">
                @else
                    <select wire:model="notification.recipient_field">
                        @foreach ($emailFields as $field)
                            <option value="{{ $field->name }}">{{ $field->label }}</option>
                        @endforeach
                    </select>
                @endif

                <input wire:model="notification.cc" placeholder="CC (optional)">
                <input wire:model="notification.bcc" placeholder="BCC (optional)">
            </div>

            {{-- From Address --}}
            <div class="section">
                <h3>From Address</h3>
                <input wire:model="notification.from_email" placeholder="From Email">
                <input wire:model="notification.from_name" placeholder="From Name">
            </div>

            {{-- Reply-To --}}
            <div class="section">
                <h3>Reply-To</h3>
                <select wire:model.live="notification.reply_to_type">
                    <option value="static">Static Email</option>
                    <option value="field">From Form Field</option>
                </select>

                @if ($notification['reply_to_type'] === 'static')
                    <input wire:model="notification.reply_to_email" placeholder="reply@example.com">
                @else
                    <select wire:model="notification.reply_to_field">
                        @foreach ($emailFields as $field)
                            <option value="{{ $field->name }}">{{ $field->label }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Content --}}
            <div class="section">
                <h3>Email Content</h3>
                <input wire:model="notification.subject" placeholder="Subject">
                <textarea wire:model="notification.message" rows="10" placeholder="Message"></textarea>

                <div class="placeholders">
                    <strong>Available Placeholders:</strong>
                    <code>{form_name}</code>, <code>{submission_number}</code>,
                    @foreach ($form->fields as $field)
                        <code>{{ '{' . $field->name . '}' }}</code>,
                    @endforeach
                </div>

                <label>
                    <input type="checkbox" wire:model="notification.include_submission_data">
                    Include all submission data
                </label>
            </div>

            {{-- Conditions --}}
            <div class="section">
                <h3>Conditions (Optional)</h3>
                @include('forms::partials.condition-builder', ['conditions' => $notification['conditions']])
            </div>

            {{-- Actions --}}
            <div class="actions">
                <button wire:click="save">Save</button>
                <button wire:click="sendTest">Send Test</button>
                <button wire:click="cancel">Cancel</button>
            </div>
        </div>
    @endif
</div>
```

## Methods

### addNotification

Add a new notification:

```php
public function addNotification(string $type): void
{
    $this->editingNotification = true;
    $this->notification = [
        'type' => $type,
        'name' => $type === 'admin' ? 'Admin Notification' : 'Confirmation Email',
        'is_active' => true,
        'recipient_type' => $type === 'admin' ? 'static' : 'field',
        'recipients' => '',
        'recipient_field' => '',
        'cc' => '',
        'bcc' => '',
        'from_email' => config('artisanpack.forms.notifications.from_email'),
        'from_name' => config('artisanpack.forms.notifications.from_name'),
        'reply_to_type' => 'field',
        'reply_to_email' => '',
        'reply_to_field' => 'email',
        'subject' => $type === 'admin'
            ? 'New submission: {form_name}'
            : 'Thank you for your submission',
        'message' => '',
        'include_submission_data' => $type === 'admin',
        'conditions' => null,
    ];
}
```

### save

Save the notification:

```php
public function save(): void
{
    $this->validate([
        'notification.name' => 'required|string|max:255',
        'notification.subject' => 'required|string|max:255',
        'notification.recipients' => 'required_if:notification.recipient_type,static',
        'notification.recipient_field' => 'required_if:notification.recipient_type,field',
    ]);

    if (isset($this->notification['id'])) {
        $notificationModel = FormNotification::find($this->notification['id']);
        $notificationModel->update($this->notification);
    } else {
        $this->notification['form_id'] = $this->form->id;
        FormNotification::create($this->notification);
    }

    $this->form->refresh();
    $this->editingNotification = false;
    session()->flash('success', 'Notification saved.');
}
```

### sendTest

Send a test email:

```php
public function sendTest(): void
{
    $this->validate([
        'testEmail' => 'required|email',
    ]);

    $this->notificationService->sendTest(
        new FormNotification($this->notification),
        $this->testEmail
    );

    session()->flash('success', 'Test email sent.');
}
```

### delete

Delete a notification:

```php
public function delete(int $id): void
{
    $notification = FormNotification::findOrFail($id);
    $notification->delete();

    $this->form->refresh();
    session()->flash('success', 'Notification deleted.');
}
```

## Events Emitted

| Event | Payload | Description |
|-------|---------|-------------|
| `notification-saved` | `{ id }` | Notification saved |
| `notification-deleted` | `{ id }` | Notification deleted |
| `test-sent` | `{ email }` | Test email sent |

## Customizing the View

Publish views:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/notification-editor.blade.php`.

## Next Steps

- [Notifications Usage](Usage-Notifications) - Email notification guide
- [FormBuilder](Form-Builder) - Form builder component
