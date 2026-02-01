---
title: SubmissionsList Component
---

# SubmissionsList Component

The SubmissionsList component displays form submissions with search, filtering, and export capabilities.

## Basic Usage

```blade
{{-- All submissions for a form --}}
<livewire:forms::submissions-list :form="$form" />

{{-- All submissions across all forms --}}
<livewire:forms::submissions-list />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `form` | Form\|null | null | Filter by form |
| `perPage` | int | 15 | Items per page |
| `sortBy` | string | 'created_at' | Sort column |
| `sortDirection` | string | 'desc' | Sort direction |

## Features

- Search submissions by field values
- Filter by date range
- Sort by columns
- Pagination
- Export to CSV/Excel
- Bulk delete
- View submission details

## Component Structure

```blade
<div class="submissions-list">
    {{-- Search & Filters --}}
    <div class="filters">
        <input wire:model.live.debounce.300ms="search" placeholder="Search...">

        <input type="date" wire:model.live="startDate">
        <input type="date" wire:model.live="endDate">

        @if (!$form)
            <select wire:model.live="formFilter">
                <option value="">All Forms</option>
                @foreach ($forms as $f)
                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- Actions --}}
    <div class="actions">
        <button wire:click="exportCsv">Export CSV</button>

        @if (count($selected) > 0)
            <button wire:click="deleteSelected">
                Delete Selected ({{ count($selected) }})
            </button>
        @endif
    </div>

    {{-- Submissions Table --}}
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" wire:model="selectAll"></th>
                <th wire:click="sortBy('submission_number')">Number</th>
                @if (!$form)
                    <th>Form</th>
                @endif
                {{-- Dynamic columns based on form fields --}}
                @foreach ($displayColumns as $column)
                    <th>{{ $column }}</th>
                @endforeach
                <th wire:click="sortBy('created_at')">Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($submissions as $submission)
                <tr>
                    <td>
                        <input type="checkbox" wire:model="selected" value="{{ $submission->id }}">
                    </td>
                    <td>{{ $submission->submission_number }}</td>
                    @if (!$form)
                        <td>{{ $submission->form->name }}</td>
                    @endif
                    @foreach ($displayColumns as $column)
                        <td>{{ $submission->getValue($column) }}</td>
                    @endforeach
                    <td>{{ $submission->created_at->format('M d, Y H:i') }}</td>
                    <td>
                        <a href="{{ route('forms.submissions.show', [$submission->form, $submission]) }}">
                            View
                        </a>
                        <button wire:click="delete({{ $submission->id }})">Delete</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pagination --}}
    {{ $submissions->links() }}
</div>
```

## Methods

### exportCsv

Export submissions to CSV:

```php
public function exportCsv()
{
    $this->authorize('export', FormSubmission::class);

    return response()->streamDownload(function () {
        echo $this->exportService->toCsv($this->form, [
            'search' => $this->search,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ]);
    }, "submissions-{$this->form->slug}.csv");
}
```

### delete

Delete a single submission:

```php
public function delete(int $id): void
{
    $submission = FormSubmission::findOrFail($id);
    $this->authorize('delete', $submission);

    $submission->delete();

    session()->flash('success', 'Submission deleted.');
}
```

### deleteSelected

Bulk delete submissions:

```php
public function deleteSelected(): void
{
    foreach ($this->selected as $id) {
        $submission = FormSubmission::find($id);
        if ($submission && Gate::allows('delete', $submission)) {
            $submission->delete();
        }
    }

    $this->selected = [];
    session()->flash('success', 'Selected submissions deleted.');
}
```

## Display Columns

Configure which fields to show in the list:

```php
public array $displayColumns = ['email', 'name'];

// Or dynamically from form
public function getDisplayColumnsProperty(): array
{
    if (!$this->form) {
        return ['email'];
    }

    return $this->form->fields
        ->where('type', '!=', 'hidden')
        ->take(3)
        ->pluck('name')
        ->toArray();
}
```

## Events Emitted

| Event | Payload | Description |
|-------|---------|-------------|
| `submission-deleted` | `{ id }` | Submission deleted |
| `submissions-exported` | `{ count }` | Export completed |

## Customizing the View

Publish views:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/submissions-list.blade.php`.

## Next Steps

- [SubmissionDetail](Submission-Detail) - Submission detail view
- [Submissions Usage](Usage-Submissions) - Working with submissions
