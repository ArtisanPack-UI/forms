<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\ExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SubmissionsList Livewire Component
 *
 * Displays a paginated, searchable, sortable list of form submissions
 * with actions for read/unread, star, spam, and delete.
 *
 * @since 1.0.0
 */
class SubmissionsList extends Component
{
    use WithPagination;

    /**
     * The form to show submissions for (optional - if null, shows all).
     */
    public ?int $formId = null;

    /**
     * The search query string.
     */
    #[Url(as: 'q')]
    public string $search = '';

    /**
     * The status filter (all, unread, read, spam, starred).
     */
    #[Url(as: 'status')]
    public string $status = 'all';

    /**
     * The date range filter (all, today, week, month, year).
     */
    #[Url(as: 'date')]
    public string $dateRange = 'all';

    /**
     * The form filter (for when viewing all submissions).
     */
    #[Url(as: 'form')]
    public string $formFilter = '';

    /**
     * The column to sort by.
     */
    #[Url(as: 'sort')]
    public string $sortBy = 'created_at';

    /**
     * The sort direction (asc or desc).
     */
    #[Url(as: 'dir')]
    public string $sortDirection = 'desc';

    /**
     * Selected submission IDs for bulk actions.
     *
     * @var array<int>
     */
    public array $selected = [];

    /**
     * Select all checkbox state.
     */
    public bool $selectAll = false;

    /**
     * Initialize the component.
     */
    public function mount(?int $formId = null): void
    {
        $this->formId = $formId;
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Reset pagination when status filter changes.
     */
    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Reset pagination when date range changes.
     */
    public function updatedDateRange(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Reset pagination when form filter changes.
     */
    public function updatedFormFilter(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Handle select all checkbox.
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected = $this->submissions->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    /**
     * Reset selection state.
     */
    protected function resetSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * Sort by a given column.
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    // =========================================
    // Individual Actions
    // =========================================

    /**
     * Mark a submission as read.
     */
    public function markAsRead(int $id): void
    {
        $submission = FormSubmission::find($id);

        if ($submission) {
            $submission->markAsRead();
        }
    }

    /**
     * Mark a submission as unread.
     */
    public function markAsUnread(int $id): void
    {
        $submission = FormSubmission::find($id);

        if ($submission) {
            $submission->markAsUnread();
        }
    }

    /**
     * Toggle the starred status of a submission.
     */
    public function toggleStar(int $id): void
    {
        $submission = FormSubmission::find($id);

        if ($submission) {
            $submission->toggleStar();
        }
    }

    /**
     * Toggle the spam status of a submission.
     */
    public function toggleSpam(int $id): void
    {
        $submission = FormSubmission::find($id);

        if ($submission) {
            $submission->toggleSpam();
        }
    }

    /**
     * Delete a submission.
     */
    public function delete(int $id): void
    {
        $submission = FormSubmission::find($id);

        if ($submission) {
            $submission->delete();
            session()->flash('success', 'Submission deleted successfully.');
        }
    }

    // =========================================
    // Bulk Actions
    // =========================================

    /**
     * Mark selected submissions as read.
     */
    public function bulkMarkAsRead(): void
    {
        FormSubmission::whereIn('id', $this->selected)
            ->update(['is_read' => true]);

        $count = count($this->selected);
        $this->resetSelection();
        session()->flash('success', "{$count} submission(s) marked as read.");
    }

    /**
     * Mark selected submissions as unread.
     */
    public function bulkMarkAsUnread(): void
    {
        FormSubmission::whereIn('id', $this->selected)
            ->update(['is_read' => false]);

        $count = count($this->selected);
        $this->resetSelection();
        session()->flash('success', "{$count} submission(s) marked as unread.");
    }

    /**
     * Mark selected submissions as spam.
     */
    public function bulkMarkAsSpam(): void
    {
        FormSubmission::whereIn('id', $this->selected)
            ->update(['is_spam' => true]);

        $count = count($this->selected);
        $this->resetSelection();
        session()->flash('success', "{$count} submission(s) marked as spam.");
    }

    /**
     * Delete selected submissions.
     */
    public function bulkDelete(): void
    {
        FormSubmission::whereIn('id', $this->selected)->delete();

        $count = count($this->selected);
        $this->resetSelection();
        session()->flash('success', "{$count} submission(s) deleted.");
    }

    // =========================================
    // Export
    // =========================================

    /**
     * Export submissions to CSV.
     */
    public function exportCsv(): StreamedResponse|\Livewire\Features\SupportRedirects\Redirector
    {
        // Determine form: use formId if set, otherwise try formFilter
        $form = null;
        if ($this->formId) {
            $form = Form::find($this->formId);
        } elseif ($this->formFilter !== '') {
            $form = Form::find((int) $this->formFilter);
        }

        if (! $form) {
            session()->flash('error', 'Please select a form to export submissions.');

            return $this->redirect(request()->header('Referer', route('forms.submissions.all')));
        }

        $exportService = app(ExportService::class);

        // Get submissions to export (selected or all visible)
        if (! empty($this->selected)) {
            $submissions = FormSubmission::whereIn('id', $this->selected)
                ->with('values')
                ->get();
        } else {
            $submissions = $this->getFilteredQuery()
                ->with('values')
                ->get();
        }

        return $exportService->exportToCsv($form, $submissions);
    }

    // =========================================
    // Computed Properties
    // =========================================

    /**
     * Get the paginated list of submissions.
     *
     * @return LengthAwarePaginator<FormSubmission>
     */
    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return $this->getFilteredQuery()
            ->with(['form', 'values'])
            ->paginate(config('artisanpack.forms.admin.per_page', 15));
    }

    /**
     * Get the count of submissions for each status.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        $baseQuery = FormSubmission::query();

        if ($this->formId) {
            $baseQuery->where('form_id', $this->formId);
        }

        return [
            'all' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->where('is_read', false)->count(),
            'read' => (clone $baseQuery)->where('is_read', true)->count(),
            'spam' => (clone $baseQuery)->where('is_spam', true)->count(),
            'starred' => (clone $baseQuery)->where('is_starred', true)->count(),
        ];
    }

    /**
     * Get all forms for the filter dropdown.
     *
     * @return Collection<int, Form>
     */
    #[Computed]
    public function forms(): Collection
    {
        return Form::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * Get the currently selected form (if any).
     */
    #[Computed]
    public function currentForm(): ?Form
    {
        return $this->formId ? Form::find($this->formId) : null;
    }

    /**
     * Build the filtered query for submissions.
     *
     * @return \Illuminate\Database\Eloquent\Builder<FormSubmission>
     */
    protected function getFilteredQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = FormSubmission::query();

        // Filter by form
        if ($this->formId) {
            $query->where('form_id', $this->formId);
        } elseif ($this->formFilter !== '') {
            $query->where('form_id', (int) $this->formFilter);
        }

        // Apply search filter (escape special LIKE characters)
        if ($this->search !== '') {
            $search = str_replace(['%', '_'], ['\%', '\_'], $this->search);
            $query->where(function ($q) use ($search): void {
                $q->where('submission_number', 'like', "%{$search}%")
                    ->orWhereHas('values', function ($vq) use ($search): void {
                        $vq->where('value', 'like', "%{$search}%");
                    });
            });
        }

        // Apply status filter
        match ($this->status) {
            'unread' => $query->where('is_read', false)->where('is_spam', false),
            'read' => $query->where('is_read', true)->where('is_spam', false),
            'spam' => $query->where('is_spam', true),
            'starred' => $query->where('is_starred', true),
            default => $query->where('is_spam', false), // 'all' excludes spam by default
        };

        // Apply date range filter
        match ($this->dateRange) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            default => null, // 'all' - no date filter
        };

        // Apply sorting (validate to prevent injection)
        $sortColumn = match ($this->sortBy) {
            'submission_number' => 'submission_number',
            default => 'created_at',
        };

        $sortDirection = strtolower($this->sortDirection);
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $query->orderBy($sortColumn, $sortDirection);

        return $query;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('forms::livewire.submissions-list');
    }
}
