---
title: SubmissionDetail Component
---

# SubmissionDetail Component

The SubmissionDetail component displays a single submission's complete details.

## Basic Usage

```blade
<livewire:forms::submission-detail :submission="$submission" />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `submission` | FormSubmission | required | Submission to display |

## Features

- Display all field values
- Show submission metadata
- Download uploaded files
- Delete submission
- Resend notifications

## Component Structure

```blade
<div class="submission-detail">
    {{-- Header --}}
    <div class="header">
        <h2>Submission {{ $submission->submission_number }}</h2>
        <p>Submitted: {{ $submission->created_at->format('F j, Y g:i A') }}</p>
    </div>

    {{-- Metadata --}}
    <div class="metadata">
        @if ($submission->ip_address)
            <p><strong>IP Address:</strong> {{ $submission->ip_address }}</p>
        @endif
        @if ($submission->user_agent)
            <p><strong>User Agent:</strong> {{ $submission->user_agent }}</p>
        @endif
    </div>

    {{-- Field Values --}}
    <div class="field-values">
        <h3>Submitted Data</h3>
        <table>
            @foreach ($submission->values as $value)
                <tr>
                    <th>{{ $value->field_label }}</th>
                    <td>
                        @if (is_array($value->value))
                            {{ implode(', ', $value->value) }}
                        @else
                            {{ $value->value }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    {{-- Uploaded Files --}}
    @if ($submission->uploads->count() > 0)
        <div class="uploads">
            <h3>Uploaded Files</h3>
            @foreach ($submission->uploads as $upload)
                <div class="upload">
                    <span>{{ $upload->original_name }}</span>
                    <span>({{ $upload->humanFileSize() }})</span>
                    <a href="{{ route('forms.uploads.download', [$submission->form, $submission, $upload]) }}"
                       download>
                        Download
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Actions --}}
    <div class="actions">
        <button wire:click="resendNotifications">Resend Notifications</button>
        <button wire:click="delete" wire:confirm="Are you sure?">Delete</button>
    </div>
</div>
```

## Methods

### delete

Delete the submission:

```php
public function delete(): void
{
    $this->authorize('delete', $this->submission);

    $formId = $this->submission->form_id;
    $this->submission->delete();

    session()->flash('success', 'Submission deleted.');
    $this->redirect(route('forms.submissions.index', $formId));
}
```

### resendNotifications

Resend all notifications:

```php
public function resendNotifications(): void
{
    $this->notificationService->sendNotifications($this->submission);

    session()->flash('success', 'Notifications sent.');
}
```

### downloadUpload

Download an uploaded file:

```php
public function downloadUpload(int $uploadId)
{
    $upload = $this->submission->uploads->find($uploadId);

    if (!$upload) {
        abort(404);
    }

    $disk = Storage::disk($upload->disk);

    return response()->download(
        $disk->path($upload->path),
        $upload->original_name
    );
}
```

## Displaying Values

Format different value types:

```blade
@switch($value->field?->type)
    @case('checkbox')
        {{ $value->value ? 'Yes' : 'No' }}
        @break

    @case('checkbox_group')
    @case('select')
        @if (is_array($value->value))
            <ul>
                @foreach ($value->value as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @else
            {{ $value->value }}
        @endif
        @break

    @case('textarea')
        <pre>{{ $value->value }}</pre>
        @break

    @case('file')
        {{-- File handled separately via uploads --}}
        @break

    @default
        {{ $value->value }}
@endswitch
```

## Events Emitted

| Event | Payload | Description |
|-------|---------|-------------|
| `submission-deleted` | `{ id }` | Submission deleted |
| `notifications-resent` | `{ count }` | Notifications sent |

## Customizing the View

Publish views:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/submission-detail.blade.php`.

## Next Steps

- [SubmissionsList](./submissions-list.md) - Submissions listing
- [Submissions Usage](../usage/submissions.md) - Working with submissions
- [File Uploads](../usage/file-uploads.md) - File handling
