<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * SubmissionDetail Livewire Component
 *
 * Displays detailed information about a form submission including
 * field values, metadata, and admin actions.
 *
 * @since 1.0.0
 */
class SubmissionDetail extends Component
{
    /**
     * The submission ID.
     */
    public int $submissionId;

    /**
     * The admin notes text.
     */
    public string $adminNotes = '';

    /**
     * Initialize the component.
     */
    public function mount(FormSubmission $submission): void
    {
        $this->submissionId = $submission->id;
        $this->adminNotes = $submission->admin_notes ?? '';

        // Mark as read when viewed
        if (! $submission->is_read) {
            $submission->markAsRead();
        }
    }

    /**
     * Get the submission model.
     */
    #[Computed]
    public function submission(): ?FormSubmission
    {
        return FormSubmission::with(['form', 'values', 'uploads'])
            ->find($this->submissionId);
    }

    /**
     * Get the form model.
     */
    #[Computed]
    public function form(): ?Form
    {
        return $this->submission?->form;
    }

    /**
     * Get the previous submission.
     */
    #[Computed]
    public function previousSubmission(): ?FormSubmission
    {
        if (! $this->submission) {
            return null;
        }

        return FormSubmission::where('form_id', $this->submission->form_id)
            ->where('id', '<', $this->submissionId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Get the next submission.
     */
    #[Computed]
    public function nextSubmission(): ?FormSubmission
    {
        if (! $this->submission) {
            return null;
        }

        return FormSubmission::where('form_id', $this->submission->form_id)
            ->where('id', '>', $this->submissionId)
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Save admin notes (auto-save on change).
     */
    public function updatedAdminNotes(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission) {
            $submission->update(['admin_notes' => $this->adminNotes]);
        }
    }

    /**
     * Toggle the starred status.
     */
    public function toggleStar(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission) {
            $submission->toggleStar();
        }
    }

    /**
     * Toggle the spam status.
     */
    public function toggleSpam(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission) {
            $submission->toggleSpam();
        }
    }

    /**
     * Mark the submission as read.
     */
    public function markAsRead(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission) {
            $submission->markAsRead();
        }
    }

    /**
     * Mark the submission as unread.
     */
    public function markAsUnread(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission) {
            $submission->markAsUnread();
        }
    }

    /**
     * Delete the submission.
     */
    public function delete(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission) {
            $formSlug = $submission->form->slug;
            $submission->delete();

            session()->flash('success', 'Submission deleted successfully.');

            $this->redirect(route('forms.submissions.index', $formSlug));
        }
    }

    /**
     * Navigate to the previous submission.
     */
    public function goToPrevious(): void
    {
        if ($this->previousSubmission) {
            $this->redirect(route('forms.submissions.show', [
                $this->form->slug,
                $this->previousSubmission,
            ]));
        }
    }

    /**
     * Navigate to the next submission.
     */
    public function goToNext(): void
    {
        if ($this->nextSubmission) {
            $this->redirect(route('forms.submissions.show', [
                $this->form->slug,
                $this->nextSubmission,
            ]));
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('forms::livewire.submission-detail');
    }
}
