<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Jobs;

use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SendFormNotification Job
 *
 * Queued job that sends a single form notification email.
 * Handles recipient resolution, email building, and error logging.
 *
 * @since 1.0.0
 */
class SendFormNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The form notification to send.
     */
    public FormNotification $notification;

    /**
     * The form submission data.
     */
    public FormSubmission $submission;

    /**
     * Create a new job instance.
     */
    public function __construct(FormNotification $notification, FormSubmission $submission)
    {
        $this->notification = $notification;
        $this->submission = $submission;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Ensure relationships are loaded
        $this->submission->loadMissing(['form', 'values.field']);
        $this->notification->loadMissing('form');

        // Get recipient emails
        $recipients = $this->notification->getRecipientEmails($this->submission);

        if (empty($recipients)) {
            Log::warning('Form notification has no valid recipients', [
                'notification_id' => $this->notification->id,
                'notification_name' => $this->notification->name,
                'submission_id' => $this->submission->id,
                'form_id' => $this->submission->form_id,
            ]);

            return;
        }

        try {
            // Create and send the mailable
            $mailable = new FormSubmissionNotification($this->notification, $this->submission);

            Mail::to($recipients)->send($mailable);

            Log::info('Form notification sent successfully', [
                'notification_id' => $this->notification->id,
                'notification_name' => $this->notification->name,
                'submission_id' => $this->submission->id,
                'recipients' => $recipients,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send form notification', [
                'notification_id' => $this->notification->id,
                'notification_name' => $this->notification->name,
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Form notification job failed permanently', [
            'notification_id' => $this->notification->id,
            'notification_name' => $this->notification->name,
            'submission_id' => $this->submission->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'form-notification',
            'notification:'.$this->notification->id,
            'submission:'.$this->submission->id,
            'form:'.$this->submission->form_id,
        ];
    }
}
