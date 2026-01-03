# Submissions Management

**Purpose:** Define the admin interface for viewing, searching, filtering, and exporting form submissions.

---

## Overview

The submissions management interface provides:

- List view of all submissions with filtering
- Detailed submission view
- Mark as read/unread/spam
- Star important submissions
- Add admin notes
- Export to CSV
- Bulk actions
- Delete submissions

---

## Submissions List Component

```php
<?php

namespace ArtisanPackUI\Forms\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\ExportService;

class SubmissionsList extends Component
{
    use WithPagination;

    // =========================================
    // Properties
    // =========================================

    public ?Form $form = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all'; // all, unread, read, spam, starred

    #[Url]
    public string $dateRange = 'all'; // all, today, week, month, year

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDir = 'desc';

    public array $selected = [];
    public bool $selectAll = false;

    // =========================================
    // Computed Properties
    // =========================================

    #[Computed]
    public function submissions()
    {
        $query = FormSubmission::query()
            ->with(['form', 'values']);

        // Filter by form if specified
        if ($this->form) {
            $query->where('form_id', $this->form->id);
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('submission_number', 'like', "%{$this->search}%")
                  ->orWhereHas('values', function ($vq) {
                      $vq->where('value', 'like', "%{$this->search}%");
                  });
            });
        }

        // Status filter
        $query = match ($this->status) {
            'unread' => $query->unread()->notSpam(),
            'read' => $query->read()->notSpam(),
            'spam' => $query->spam(),
            'starred' => $query->starred()->notSpam(),
            default => $query->notSpam(),
        };

        // Date range
        $query = match ($this->dateRange) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            default => $query,
        };

        // Sort
        $query->orderBy($this->sortBy, $this->sortDir);

        return $query->paginate(25);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $baseQuery = FormSubmission::query();

        if ($this->form) {
            $baseQuery->where('form_id', $this->form->id);
        }

        return [
            'all' => (clone $baseQuery)->notSpam()->count(),
            'unread' => (clone $baseQuery)->unread()->notSpam()->count(),
            'read' => (clone $baseQuery)->read()->notSpam()->count(),
            'spam' => (clone $baseQuery)->spam()->count(),
            'starred' => (clone $baseQuery)->starred()->notSpam()->count(),
        ];
    }

    #[Computed]
    public function forms()
    {
        return Form::withCount('submissions')->orderBy('name')->get();
    }

    // =========================================
    // Actions
    // =========================================

    public function markAsRead(int $id): void
    {
        FormSubmission::find($id)?->markAsRead();
    }

    public function markAsUnread(int $id): void
    {
        FormSubmission::find($id)?->markAsUnread();
    }

    public function toggleStar(int $id): void
    {
        FormSubmission::find($id)?->toggleStar();
    }

    public function toggleSpam(int $id): void
    {
        FormSubmission::find($id)?->toggleSpam();
    }

    public function delete(int $id): void
    {
        FormSubmission::find($id)?->delete();
        $this->dispatch('notify', message: 'Submission deleted');
    }

    // =========================================
    // Bulk Actions
    // =========================================

    public function bulkMarkAsRead(): void
    {
        FormSubmission::whereIn('id', $this->selected)->update(['is_read' => true]);
        $this->selected = [];
        $this->selectAll = false;
    }

    public function bulkMarkAsUnread(): void
    {
        FormSubmission::whereIn('id', $this->selected)->update(['is_read' => false]);
        $this->selected = [];
        $this->selectAll = false;
    }

    public function bulkMarkAsSpam(): void
    {
        FormSubmission::whereIn('id', $this->selected)->update(['is_spam' => true]);
        $this->selected = [];
        $this->selectAll = false;
    }

    public function bulkDelete(): void
    {
        FormSubmission::whereIn('id', $this->selected)->delete();
        $this->selected = [];
        $this->selectAll = false;
        $this->dispatch('notify', message: 'Submissions deleted');
    }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected = $this->submissions->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    // =========================================
    // Export
    // =========================================

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $exportService = app(ExportService::class);

        $submissions = FormSubmission::query()
            ->when($this->form, fn($q) => $q->where('form_id', $this->form->id))
            ->whereIn('id', $this->selected ?: $this->submissions->pluck('id'))
            ->with(['form', 'values', 'uploads'])
            ->get();

        return $exportService->toCsv($submissions);
    }

    // =========================================
    // Render
    // =========================================

    public function render()
    {
        return view('forms::admin.submissions.index');
    }
}
```

---

## Submissions List View

```blade
{{-- resources/views/admin/submissions/index.blade.php --}}

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">
                @if($form)
                    {{ $form->name }} Submissions
                @else
                    All Submissions
                @endif
            </h1>
            <p class="text-base-content/60">
                {{ number_format($this->statusCounts['all']) }} total submissions
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if(!empty($selected))
                <div class="dropdown dropdown-end">
                    <x-artisanpack-button tabindex="0">
                        Bulk Actions ({{ count($selected) }})
                        <x-artisanpack-icon name="o-chevron-down" class="w-4 h-4" />
                    </x-artisanpack-button>
                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box w-52 shadow-lg">
                        <li><a wire:click="bulkMarkAsRead">Mark as Read</a></li>
                        <li><a wire:click="bulkMarkAsUnread">Mark as Unread</a></li>
                        <li><a wire:click="bulkMarkAsSpam">Mark as Spam</a></li>
                        <li class="text-error"><a wire:click="bulkDelete" wire:confirm="Delete selected submissions?">Delete</a></li>
                    </ul>
                </div>
            @endif

            <x-artisanpack-button wire:click="export" color="ghost">
                <x-artisanpack-icon name="o-arrow-down-tray" class="w-4 h-4" />
                Export CSV
            </x-artisanpack-button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-base-100 rounded-lg border border-base-300 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <x-artisanpack-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search submissions..."
                    icon="o-magnifying-glass"
                />
            </div>

            {{-- Form Filter --}}
            @if(!$form)
                <x-artisanpack-select
                    wire:model.live="form_id"
                    placeholder="All Forms"
                    :options="$this->forms->pluck('name', 'id')->toArray()"
                />
            @endif

            {{-- Date Range --}}
            <x-artisanpack-select
                wire:model.live="dateRange"
                :options="[
                    'all' => 'All Time',
                    'today' => 'Today',
                    'week' => 'This Week',
                    'month' => 'This Month',
                    'year' => 'This Year',
                ]"
            />
        </div>

        {{-- Status Tabs --}}
        <div class="flex items-center gap-1 mt-4 border-t border-base-300 pt-4">
            @foreach(['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'starred' => 'Starred', 'spam' => 'Spam'] as $key => $label)
                <button
                    wire:click="$set('status', '{{ $key }}')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition
                           {{ $status === $key
                               ? 'bg-primary text-primary-content'
                               : 'hover:bg-base-200' }}"
                >
                    {{ $label }}
                    <span class="badge badge-sm ml-1 {{ $status === $key ? 'badge-primary-content' : '' }}">
                        {{ $this->statusCounts[$key] }}
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Submissions Table --}}
    <div class="bg-base-100 rounded-lg border border-base-300 overflow-hidden">
        <table class="table">
            <thead>
                <tr>
                    <th class="w-12">
                        <input
                            type="checkbox"
                            wire:model.live="selectAll"
                            class="checkbox checkbox-sm"
                        />
                    </th>
                    <th>
                        <button wire:click="$set('sortBy', 'submission_number')" class="flex items-center gap-1">
                            Submission
                            @if($sortBy === 'submission_number')
                                <x-artisanpack-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    @if(!$form)
                        <th>Form</th>
                    @endif
                    <th>Summary</th>
                    <th>
                        <button wire:click="$set('sortBy', 'created_at')" class="flex items-center gap-1">
                            Date
                            @if($sortBy === 'created_at')
                                <x-artisanpack-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    <th class="w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->submissions as $submission)
                    <tr class="{{ !$submission->is_read ? 'font-semibold bg-primary/5' : '' }}">
                        <td>
                            <input
                                type="checkbox"
                                wire:model.live="selected"
                                value="{{ $submission->id }}"
                                class="checkbox checkbox-sm"
                            />
                        </td>
                        <td>
                            <a
                                href="{{ route('forms.submissions.show', $submission) }}"
                                class="flex items-center gap-2 hover:text-primary"
                            >
                                @if($submission->is_starred)
                                    <x-artisanpack-icon name="s-star" class="w-4 h-4 text-warning" />
                                @endif
                                <span>{{ $submission->submission_number }}</span>
                            </a>
                        </td>
                        @if(!$form)
                            <td>
                                <span class="badge">{{ $submission->form->name }}</span>
                            </td>
                        @endif
                        <td class="max-w-md truncate text-base-content/60">
                            {{ $submission->values->take(2)->pluck('display_value')->join(' - ') }}
                        </td>
                        <td class="text-base-content/60">
                            {{ $submission->created_at->diffForHumans() }}
                        </td>
                        <td>
                            <div class="flex items-center gap-1">
                                <button
                                    wire:click="toggleStar({{ $submission->id }})"
                                    class="btn btn-ghost btn-xs"
                                    title="{{ $submission->is_starred ? 'Unstar' : 'Star' }}"
                                >
                                    <x-artisanpack-icon
                                        :name="$submission->is_starred ? 's-star' : 'o-star'"
                                        class="w-4 h-4 {{ $submission->is_starred ? 'text-warning' : '' }}"
                                    />
                                </button>
                                <button
                                    wire:click="{{ $submission->is_read ? 'markAsUnread' : 'markAsRead' }}({{ $submission->id }})"
                                    class="btn btn-ghost btn-xs"
                                    title="{{ $submission->is_read ? 'Mark as Unread' : 'Mark as Read' }}"
                                >
                                    <x-artisanpack-icon
                                        :name="$submission->is_read ? 'o-envelope-open' : 'o-envelope'"
                                        class="w-4 h-4"
                                    />
                                </button>
                                <button
                                    wire:click="delete({{ $submission->id }})"
                                    wire:confirm="Delete this submission?"
                                    class="btn btn-ghost btn-xs text-error"
                                    title="Delete"
                                >
                                    <x-artisanpack-icon name="o-trash" class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-12 text-base-content/50">
                            <x-artisanpack-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3" />
                            <p>No submissions found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $this->submissions->links() }}
    </div>
</div>
```

---

## Submission Detail View

```blade
{{-- resources/views/admin/submissions/show.blade.php --}}

<div class="max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('forms.submissions.index', ['form' => $submission->form]) }}" class="btn btn-ghost btn-sm">
                    <x-artisanpack-icon name="o-arrow-left" class="w-4 h-4" />
                </a>
                <h1 class="text-2xl font-bold">{{ $submission->submission_number }}</h1>
                @if($submission->is_starred)
                    <x-artisanpack-icon name="s-star" class="w-5 h-5 text-warning" />
                @endif
            </div>
            <p class="text-base-content/60 ml-11">
                {{ $submission->form->name }} &middot;
                {{ $submission->created_at->format('F j, Y \a\t g:i A') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button wire:click="toggleStar" class="btn btn-ghost btn-sm">
                <x-artisanpack-icon :name="$submission->is_starred ? 's-star' : 'o-star'" class="w-4 h-4" />
                {{ $submission->is_starred ? 'Unstar' : 'Star' }}
            </button>
            <button wire:click="toggleSpam" class="btn btn-ghost btn-sm">
                {{ $submission->is_spam ? 'Not Spam' : 'Mark as Spam' }}
            </button>
            <button
                wire:click="delete"
                wire:confirm="Are you sure you want to delete this submission?"
                class="btn btn-ghost btn-sm text-error"
            >
                <x-artisanpack-icon name="o-trash" class="w-4 h-4" />
                Delete
            </button>
        </div>
    </div>

    {{-- Submission Data --}}
    <div class="bg-base-100 rounded-lg border border-base-300 overflow-hidden mb-6">
        <div class="p-4 border-b border-base-300 bg-base-200">
            <h2 class="font-semibold">Submission Data</h2>
        </div>
        <div class="divide-y divide-base-300">
            @foreach($submission->values as $value)
                <div class="p-4 flex">
                    <div class="w-1/3 font-medium text-base-content/70">
                        {{ $value->field_label }}
                    </div>
                    <div class="w-2/3">
                        @if($value->is_file && $value->upload)
                            <a
                                href="{{ $value->upload->url }}"
                                target="_blank"
                                class="link link-primary flex items-center gap-2"
                            >
                                <x-artisanpack-icon name="o-paper-clip" class="w-4 h-4" />
                                {{ $value->upload->original_name }}
                                <span class="text-xs text-base-content/50">({{ $value->upload->human_size }})</span>
                            </a>
                        @elseif($value->is_array_value)
                            <ul class="list-disc list-inside">
                                @foreach($value->value_array as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @else
                            {!! nl2br(e($value->display_value)) !!}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Metadata --}}
    <div class="bg-base-100 rounded-lg border border-base-300 overflow-hidden mb-6">
        <div class="p-4 border-b border-base-300 bg-base-200">
            <h2 class="font-semibold">Metadata</h2>
        </div>
        <div class="divide-y divide-base-300 text-sm">
            <div class="p-4 flex">
                <div class="w-1/3 font-medium text-base-content/70">Submitted</div>
                <div class="w-2/3">{{ $submission->created_at->format('F j, Y \a\t g:i A') }}</div>
            </div>
            @if($submission->page_url)
                <div class="p-4 flex">
                    <div class="w-1/3 font-medium text-base-content/70">Page URL</div>
                    <div class="w-2/3 truncate">
                        <a href="{{ $submission->page_url }}" target="_blank" class="link link-primary">
                            {{ $submission->page_url }}
                        </a>
                    </div>
                </div>
            @endif
            @if($submission->ip_address)
                <div class="p-4 flex">
                    <div class="w-1/3 font-medium text-base-content/70">IP Address</div>
                    <div class="w-2/3">{{ $submission->ip_address }}</div>
                </div>
            @endif
            @if($submission->user_agent)
                <div class="p-4 flex">
                    <div class="w-1/3 font-medium text-base-content/70">User Agent</div>
                    <div class="w-2/3 text-xs truncate">{{ $submission->user_agent }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Admin Notes --}}
    <div class="bg-base-100 rounded-lg border border-base-300 overflow-hidden">
        <div class="p-4 border-b border-base-300 bg-base-200">
            <h2 class="font-semibold">Admin Notes</h2>
        </div>
        <div class="p-4">
            <textarea
                wire:model.blur="notes"
                class="textarea textarea-bordered w-full"
                rows="3"
                placeholder="Add private notes about this submission..."
            ></textarea>
        </div>
    </div>
</div>
```

---

## Export Service

```php
<?php

namespace ArtisanPackUI\Forms\Services;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use function ArtisanPackUI\Hooks\applyFilters;

class ExportService
{
    public function toCsv(Collection $submissions): StreamedResponse
    {
        $form = $submissions->first()?->form;
        $filename = ($form ? $form->slug : 'submissions') . '_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($submissions, $form) {
            $handle = fopen('php://output', 'w');

            // Headers
            $headers = $this->getHeaders($form);
            $headers = applyFilters('forms.export_headers', $headers, $form);
            fputcsv($handle, $headers);

            // Data rows
            foreach ($submissions as $submission) {
                $row = $this->getRow($submission, $headers);
                $row = applyFilters('forms.export_data', $row, $submission);
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function getHeaders(?Form $form): array
    {
        $headers = [
            'Submission #',
            'Date',
        ];

        if ($form) {
            foreach ($form->fields_ordered as $field) {
                if (!in_array($field->type, ['heading', 'paragraph', 'divider', 'html'])) {
                    $headers[] = $field->label;
                }
            }
        }

        $headers[] = 'Page URL';
        $headers[] = 'IP Address';
        $headers[] = 'Is Read';
        $headers[] = 'Is Spam';

        return $headers;
    }

    protected function getRow(FormSubmission $submission, array $headers): array
    {
        $row = [
            $submission->submission_number,
            $submission->created_at->format('Y-m-d H:i:s'),
        ];

        // Field values
        foreach ($submission->form->fields_ordered as $field) {
            if (!in_array($field->type, ['heading', 'paragraph', 'divider', 'html'])) {
                $value = $submission->values->firstWhere('field_name', $field->name);
                $row[] = $value ? $value->display_value : '';
            }
        }

        $row[] = $submission->page_url ?? '';
        $row[] = $submission->ip_address ?? '';
        $row[] = $submission->is_read ? 'Yes' : 'No';
        $row[] = $submission->is_spam ? 'Yes' : 'No';

        return $row;
    }
}
```

---

## Related Documents

- [01-database-schema.md](01-database-schema.md) - Submission tables
- [02-models-and-relationships.md](02-models-and-relationships.md) - FormSubmission model
- [09-integrations.md](09-integrations.md) - Export hooks
