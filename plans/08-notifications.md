# Notifications

**Purpose:** Define the email notification system, including admin notifications, auto-responders, and customizable templates.

---

## Overview

The notification system allows form creators to configure multiple email notifications per form, similar to Gravity Forms. Features include:

- **Multiple notifications per form** - No limit on notification count
- **Three notification types** - Admin, auto-responder, custom
- **Dynamic recipients** - Use form field values as email addresses
- **Placeholder support** - Insert form data into subject and message
- **Conditional sending** - Only send when conditions are met
- **Template customization** - Full control over email content

---

## Notification Types

| Type | Purpose | Typical Recipient |
|------|---------|-------------------|
| `admin` | Notify site administrators of new submissions | Static email addresses |
| `autoresponder` | Automatic reply to form submitter | Email field value |
| `custom` | Any custom notification | Static or dynamic |

---

## Data Model

### FormNotification Table

```php
// Key fields
[
    'form_id' => 1,
    'type' => 'admin',
    'name' => 'Admin Notification',

    // Recipients
    'to_email' => 'admin@example.com, manager@example.com',
    'to_field' => null,  // Field name for dynamic recipient
    'cc_emails' => null,
    'bcc_emails' => null,
    'reply_to_email' => null,
    'reply_to_field' => 'email',

    // From
    'from_name' => 'Contact Form',
    'from_email' => 'noreply@example.com',

    // Content
    'subject' => 'New Form Submission: {form_name}',
    'message' => 'A new submission was received...',

    // Options
    'conditional_logic' => null,
    'include_submission_data' => true,
    'is_active' => true,
    'sort_order' => 0,
]
```

---

## Notification Editor Component

### Form Builder Integration

```php
<?php

namespace ArtisanPackUI\Forms\Http\Livewire;

use Livewire\Component;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;

class NotificationEditor extends Component
{
    public Form $form;
    public ?FormNotification $selectedNotification = null;
    public array $notificationData = [];

    public function mount(Form $form): void
    {
        $this->form = $form->load('notifications');
    }

    public function addNotification(string $type = 'admin'): void
    {
        $defaults = $this->getDefaultsForType($type);

        $notification = $this->form->notifications()->create([
            'type' => $type,
            'name' => $defaults['name'],
            'subject' => $defaults['subject'],
            'message' => $defaults['message'],
            'to_email' => $defaults['to_email'],
            'to_field' => $defaults['to_field'],
            'include_submission_data' => true,
            'is_active' => true,
            'sort_order' => $this->form->notifications()->count(),
        ]);

        $this->selectNotification($notification);
        $this->form->refresh();
    }

    protected function getDefaultsForType(string $type): array
    {
        return match ($type) {
            'admin' => [
                'name' => 'Admin Notification',
                'subject' => 'New Submission: {form_name}',
                'message' => "A new form submission has been received.\n\n{all_fields}",
                'to_email' => config('mail.from.address'),
                'to_field' => null,
            ],
            'autoresponder' => [
                'name' => 'Auto-Responder',
                'subject' => 'Thank you for contacting us',
                'message' => "Thank you for your submission. We've received your message and will respond shortly.\n\nHere's a copy of your submission:\n\n{all_fields}",
                'to_email' => null,
                'to_field' => $this->form->fields()->where('type', 'email')->first()?->name,
            ],
            default => [
                'name' => 'Custom Notification',
                'subject' => 'Form Notification: {form_name}',
                'message' => '',
                'to_email' => '',
                'to_field' => null,
            ],
        };
    }

    public function selectNotification(FormNotification $notification): void
    {
        $this->selectedNotification = $notification;
        $this->notificationData = $notification->toArray();
    }

    public function deselectNotification(): void
    {
        $this->selectedNotification = null;
        $this->notificationData = [];
    }

    public function updateNotification(): void
    {
        $this->validate([
            'notificationData.name' => 'required|string|max:255',
            'notificationData.subject' => 'required|string|max:500',
            'notificationData.message' => 'required|string',
        ]);

        $this->selectedNotification->update($this->notificationData);
        $this->form->refresh();

        $this->dispatch('notify', message: 'Notification saved');
    }

    public function duplicateNotification(int $id): void
    {
        $original = FormNotification::findOrFail($id);
        $clone = $original->replicate();
        $clone->name = $original->name . ' (Copy)';
        $clone->sort_order = $this->form->notifications()->count();
        $clone->save();

        $this->selectNotification($clone);
        $this->form->refresh();
    }

    public function deleteNotification(int $id): void
    {
        FormNotification::destroy($id);

        if ($this->selectedNotification?->id === $id) {
            $this->deselectNotification();
        }

        $this->form->refresh();
    }

    public function toggleActive(int $id): void
    {
        $notification = FormNotification::findOrFail($id);
        $notification->update(['is_active' => !$notification->is_active]);
        $this->form->refresh();
    }

    public function render()
    {
        return view('forms::admin.notification-editor');
    }
}
```

### Notification Editor View

```blade
{{-- resources/views/admin/notification-editor.blade.php --}}

<div class="grid grid-cols-3 gap-6 h-full">
    {{-- Notification List --}}
    <div class="col-span-1 bg-base-100 rounded-lg border border-base-300">
        <div class="p-4 border-b border-base-300">
            <h3 class="font-semibold">Email Notifications</h3>
        </div>

        <div class="p-4 space-y-2">
            @foreach($form->notifications as $notification)
                <div
                    wire:click="selectNotification({{ $notification->id }})"
                    class="p-3 rounded-lg cursor-pointer transition
                           {{ $selectedNotification?->id === $notification->id
                               ? 'bg-primary/10 border-primary'
                               : 'bg-base-200 hover:bg-base-300' }} border"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ $notification->name }}</span>
                            <span class="badge badge-sm ml-2">
                                {{ ucfirst($notification->type) }}
                            </span>
                        </div>
                        <button
                            wire:click.stop="toggleActive({{ $notification->id }})"
                            class="btn btn-ghost btn-xs"
                        >
                            @if($notification->is_active)
                                <x-artisanpack-icon name="o-check-circle" class="w-4 h-4 text-success" />
                            @else
                                <x-artisanpack-icon name="o-x-circle" class="w-4 h-4 text-error" />
                            @endif
                        </button>
                    </div>
                    <div class="text-xs text-base-content/60 mt-1 truncate">
                        {{ $notification->to_email ?: '{' . $notification->to_field . '}' }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Add Notification Dropdown --}}
        <div class="p-4 border-t border-base-300">
            <div class="dropdown dropdown-top w-full">
                <x-artisanpack-button tabindex="0" class="w-full">
                    <x-artisanpack-icon name="o-plus" class="w-4 h-4" />
                    Add Notification
                </x-artisanpack-button>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box w-full shadow-lg">
                    <li><a wire:click="addNotification('admin')">Admin Notification</a></li>
                    <li><a wire:click="addNotification('autoresponder')">Auto-Responder</a></li>
                    <li><a wire:click="addNotification('custom')">Custom Notification</a></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Notification Editor --}}
    <div class="col-span-2">
        @if($selectedNotification)
            <div class="bg-base-100 rounded-lg border border-base-300 h-full overflow-auto">
                <div class="p-4 border-b border-base-300 flex items-center justify-between sticky top-0 bg-base-100 z-10">
                    <h3 class="font-semibold">Edit: {{ $selectedNotification->name }}</h3>
                    <div class="flex items-center gap-2">
                        <button
                            wire:click="duplicateNotification({{ $selectedNotification->id }})"
                            class="btn btn-ghost btn-sm"
                        >
                            <x-artisanpack-icon name="o-document-duplicate" class="w-4 h-4" />
                        </button>
                        <button
                            wire:click="deleteNotification({{ $selectedNotification->id }})"
                            wire:confirm="Are you sure you want to delete this notification?"
                            class="btn btn-ghost btn-sm text-error"
                        >
                            <x-artisanpack-icon name="o-trash" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Basic Settings --}}
                    <div class="grid grid-cols-2 gap-4">
                        <x-artisanpack-input
                            wire:model.blur="notificationData.name"
                            label="Notification Name"
                            hint="Internal name for this notification"
                        />

                        <x-artisanpack-select
                            wire:model.live="notificationData.type"
                            label="Type"
                            :options="['admin' => 'Admin', 'autoresponder' => 'Auto-Responder', 'custom' => 'Custom']"
                        />
                    </div>

                    {{-- Recipients --}}
                    <div class="border rounded-lg p-4 space-y-4">
                        <h4 class="font-medium">Recipients</h4>

                        <div class="grid grid-cols-2 gap-4">
                            <x-artisanpack-input
                                wire:model.blur="notificationData.to_email"
                                label="To Email(s)"
                                placeholder="email@example.com, other@example.com"
                                hint="Comma-separated for multiple"
                            />

                            <x-artisanpack-select
                                wire:model.live="notificationData.to_field"
                                label="Or Use Field Value"
                                placeholder="Select email field..."
                                :options="$form->fields->where('type', 'email')->pluck('label', 'name')->toArray()"
                            />
                        </div>

                        <x-artisanpack-input
                            wire:model.blur="notificationData.cc_emails"
                            label="CC"
                            placeholder="Optional CC recipients"
                        />

                        <x-artisanpack-input
                            wire:model.blur="notificationData.bcc_emails"
                            label="BCC"
                            placeholder="Optional BCC recipients"
                        />
                    </div>

                    {{-- From / Reply-To --}}
                    <div class="border rounded-lg p-4 space-y-4">
                        <h4 class="font-medium">Sender</h4>

                        <div class="grid grid-cols-2 gap-4">
                            <x-artisanpack-input
                                wire:model.blur="notificationData.from_name"
                                label="From Name"
                                placeholder="{{ config('app.name') }}"
                            />

                            <x-artisanpack-input
                                wire:model.blur="notificationData.from_email"
                                label="From Email"
                                placeholder="{{ config('mail.from.address') }}"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <x-artisanpack-input
                                wire:model.blur="notificationData.reply_to_email"
                                label="Reply-To Email"
                                placeholder="Optional static reply-to"
                            />

                            <x-artisanpack-select
                                wire:model.live="notificationData.reply_to_field"
                                label="Or Use Field Value"
                                placeholder="Select email field..."
                                :options="$form->fields->where('type', 'email')->pluck('label', 'name')->toArray()"
                            />
                        </div>
                    </div>

                    {{-- Email Content --}}
                    <div class="border rounded-lg p-4 space-y-4">
                        <h4 class="font-medium">Email Content</h4>

                        <x-artisanpack-input
                            wire:model.blur="notificationData.subject"
                            label="Subject"
                        />

                        <div>
                            <label class="label">
                                <span class="label-text">Message</span>
                            </label>
                            <textarea
                                wire:model.blur="notificationData.message"
                                class="textarea textarea-bordered w-full h-48 font-mono text-sm"
                                placeholder="Email message content..."
                            ></textarea>
                        </div>

                        {{-- Available Placeholders --}}
                        <div class="bg-base-200 rounded-lg p-3">
                            <p class="text-sm font-medium mb-2">Available Placeholders:</p>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <code class="px-2 py-1 bg-base-300 rounded">{form_name}</code>
                                <code class="px-2 py-1 bg-base-300 rounded">{submission_number}</code>
                                <code class="px-2 py-1 bg-base-300 rounded">{submission_date}</code>
                                <code class="px-2 py-1 bg-base-300 rounded">{all_fields}</code>
                                @foreach($form->fields as $field)
                                    <code class="px-2 py-1 bg-base-300 rounded">{!! '{' . $field->name . '}' !!}</code>
                                @endforeach
                            </div>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model.live="notificationData.include_submission_data"
                                class="checkbox checkbox-sm checkbox-primary"
                            />
                            <span class="label-text">Include full submission data as table</span>
                        </label>
                    </div>

                    {{-- Conditional Logic --}}
                    <div class="border rounded-lg p-4">
                        <label class="flex items-center justify-between cursor-pointer mb-4">
                            <span class="font-medium">Conditional Sending</span>
                            <input
                                type="checkbox"
                                wire:model.live="notificationData.conditional_logic.enabled"
                                class="toggle toggle-sm toggle-primary"
                            />
                        </label>

                        @if($notificationData['conditional_logic']['enabled'] ?? false)
                            {{-- Same conditional logic UI as field conditions --}}
                            @include('forms::admin.partials.notification-conditions')
                        @endif
                    </div>

                    {{-- Save Button --}}
                    <div class="flex justify-end">
                        <x-artisanpack-button
                            wire:click="updateNotification"
                            color="primary"
                        >
                            Save Notification
                        </x-artisanpack-button>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-base-100 rounded-lg border border-base-300 h-full flex items-center justify-center">
                <div class="text-center text-base-content/50">
                    <x-artisanpack-icon name="o-envelope" class="w-12 h-12 mx-auto mb-3" />
                    <p>Select a notification to edit</p>
                    <p class="text-sm mt-1">or add a new one</p>
                </div>
            </div>
        @endif
    </div>
</div>
```

---

## Notification Service

```php
<?php

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Jobs\SendFormNotification;

class NotificationService
{
    public function sendNotifications(FormSubmission $submission): void
    {
        $notifications = $submission->form
            ->notifications()
            ->active()
            ->get();

        foreach ($notifications as $notification) {
            if ($this->shouldSend($notification, $submission)) {
                SendFormNotification::dispatch($notification, $submission);
            }
        }
    }

    protected function shouldSend(FormNotification $notification, FormSubmission $submission): bool
    {
        // Check if notification has conditions
        if (!$notification->has_conditional_logic) {
            return true;
        }

        $logic = $notification->conditional_logic;
        $logicType = $logic['logic'] ?? 'all';
        $rules = $logic['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = [];

        foreach ($rules as $rule) {
            $fieldValue = $submission->getValue($rule['field_name'] ?? '');
            $ruleValue = $rule['value'] ?? '';
            $operator = $rule['operator'] ?? 'equals';

            $results[] = $this->evaluateRule($fieldValue, $operator, $ruleValue);
        }

        return $logicType === 'all'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);
    }

    protected function evaluateRule(mixed $fieldValue, string $operator, mixed $ruleValue): bool
    {
        // Same logic as conditional field evaluation
        return match ($operator) {
            'equals' => (string) $fieldValue === (string) $ruleValue,
            'not_equals' => (string) $fieldValue !== (string) $ruleValue,
            'contains' => str_contains((string) $fieldValue, (string) $ruleValue),
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            default => true,
        };
    }
}
```

---

## Email Mailable

```php
<?php

namespace ArtisanPackUI\Forms\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormNotification;

class FormSubmissionNotification extends Mailable
{
    public function __construct(
        public FormNotification $notification,
        public FormSubmission $submission
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->notification->from_email ?? config('mail.from.address'),
                $this->notification->from_name ?? config('mail.from.name')
            ),
            replyTo: $this->getReplyTo(),
            subject: $this->notification->parseSubject($this->submission),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'forms::emails.notification',
            with: [
                'notification' => $this->notification,
                'submission' => $this->submission,
                'message' => $this->notification->parseMessage($this->submission),
                'includeData' => $this->notification->include_submission_data,
            ],
        );
    }

    protected function getReplyTo(): array
    {
        $email = $this->notification->getReplyToEmail($this->submission);

        if ($email) {
            return [new Address($email)];
        }

        return [];
    }
}
```

---

## Email Template

```blade
{{-- resources/views/emails/notification.blade.php --}}

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->parseSubject($submission) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { margin-bottom: 30px; }
        .content { margin-bottom: 30px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: #f5f5f5; font-weight: 600; width: 30%; }
        .footer { font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">{{ $submission->form->name }}</h1>
            <p style="margin: 5px 0 0; color: #666;">
                Submission #{{ $submission->submission_number }} &middot;
                {{ $submission->created_at->format('F j, Y \a\t g:i A') }}
            </p>
        </div>

        <div class="content">
            {!! nl2br(e($message)) !!}
        </div>

        @if($includeData)
            <table class="data-table">
                <tbody>
                    @foreach($submission->values as $value)
                        <tr>
                            <th>{{ $value->field_label }}</th>
                            <td>
                                @if($value->is_file && $value->upload)
                                    <a href="{{ $value->upload->url }}">
                                        {{ $value->upload->original_name }}
                                    </a>
                                @else
                                    {{ $value->display_value }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="footer">
            <p>This email was sent from {{ config('app.name') }}.</p>
            @if($submission->page_url)
                <p>Submitted from: {{ $submission->page_url }}</p>
            @endif
        </div>
    </div>
</body>
</html>
```

---

## Placeholder Reference

| Placeholder | Description |
|-------------|-------------|
| `{form_name}` | Name of the form |
| `{submission_number}` | Unique submission ID |
| `{submission_date}` | Date of submission |
| `{submission_time}` | Time of submission |
| `{all_fields}` | All field values as list |
| `{field_name}` | Value of specific field |
| `{page_url}` | URL where form was submitted |
| `{ip_address}` | Submitter's IP address |

---

## Related Documents

- [02-models-and-relationships.md](02-models-and-relationships.md) - FormNotification model
- [04-form-renderer.md](04-form-renderer.md) - Triggers notifications
- [09-integrations.md](09-integrations.md) - Hook for external services
