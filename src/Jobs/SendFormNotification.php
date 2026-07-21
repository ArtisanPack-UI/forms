<?php

/**
 * Send form notification job.
 *
 * Queued job that sends a single form notification email.
 * Handles recipient resolution, email building, and error logging.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Jobs;

use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Send form notification job class.
 *
 * Queued job that sends a single form notification email.
 * Handles recipient resolution, email building, and error logging.
 *
 *
 * @since      1.0.0
 */
class SendFormNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @since 1.0.0
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @since 1.0.0
     */
    public int $backoff = 60;

    /**
     * The form notification to send.
     *
     * @since 1.0.0
     */
    public FormNotification $notification;

    /**
     * The form submission data.
     *
     * @since 1.0.0
     */
    public FormSubmission $submission;

    /**
     * Creates a new job instance.
     *
     * @since 1.0.0
     *
     * @param  FormNotification  $notification  The notification to send.
     * @param  FormSubmission  $submission  The form submission data.
     */
    public function __construct( FormNotification $notification, FormSubmission $submission )
    {
        $this->notification = $notification;
        $this->submission   = $submission;
    }

    /**
     * Executes the job.
     *
     * Loads required relationships, resolves recipients, and sends the notification email.
     *
     * @since 1.0.0
     */
    public function handle(): void
    {
        // Ensure relationships are loaded
        $this->submission->loadMissing( ['form', 'values.field'] );
        $this->notification->loadMissing( 'form' );

        // Get recipient emails
        $recipients = $this->notification->getRecipientEmails( $this->submission );

        // Apply filter hook to allow modifying recipients
        $recipients = applyFilters(
            'ap.forms.notificationRecipients',
            $recipients,
            $this->notification,
        );

        if ( empty( $recipients ) ) {
            Log::warning( 'Form notification has no valid recipients', [
                'notification_id'   => $this->notification->id,
                'notification_name' => $this->notification->name,
                'submission_id'     => $this->submission->id,
                'form_id'           => $this->submission->form_id,
            ] );

            return;
        }

        try {
            // Fire before_send action hook
            doAction(
                'ap.forms.notification.beforeSend',
                $this->notification,
                $this->submission,
            );

            // Create and send the mailable
            $mailable = new FormSubmissionNotification( $this->notification, $this->submission );

            Mail::to( $recipients )->send( $mailable );

            // Fire sent action hook
            doAction(
                'ap.forms.notification.sent',
                $this->notification,
                $this->submission,
            );

            Log::info( 'Form notification sent successfully', [
                'notification_id'   => $this->notification->id,
                'notification_name' => $this->notification->name,
                'submission_id'     => $this->submission->id,
                'recipients'        => $recipients,
            ] );
        } catch ( Exception $e ) {
            Log::error( 'Failed to send form notification', [
                'notification_id'   => $this->notification->id,
                'notification_name' => $this->notification->name,
                'submission_id'     => $this->submission->id,
                'error'             => $e->getMessage(),
            ] );

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handles a job failure.
     *
     * Logs an error when the job fails permanently after all retry attempts.
     *
     * @since 1.0.0
     *
     * @param  Throwable  $exception  The exception that caused the failure.
     */
    public function failed( Throwable $exception ): void
    {
        Log::error( 'Form notification job failed permanently', [
            'notification_id'   => $this->notification->id,
            'notification_name' => $this->notification->name,
            'submission_id'     => $this->submission->id,
            'error'             => $exception->getMessage(),
        ] );
    }

    /**
     * Gets the tags that should be assigned to the job.
     *
     * Tags are used for monitoring and filtering jobs in queue dashboards.
     *
     * @since 1.0.0
     *
     * @return array<int, string> The job tags.
     */
    public function tags(): array
    {
        return [
            'form-notification',
            'notification:' . $this->notification->id,
            'submission:' . $this->submission->id,
            'form:' . $this->submission->form_id,
        ];
    }
}
