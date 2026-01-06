<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Mail;

use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FormSubmissionNotification Mailable
 *
 * Handles email notification sending for form submissions.
 * Supports dynamic recipients, placeholder parsing, and
 * conditional submission data inclusion.
 *
 * @since 1.0.0
 */
class FormSubmissionNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The form notification configuration.
     */
    public FormNotification $notification;

    /**
     * The form submission data.
     */
    public FormSubmission $submission;

    /**
     * The parsed subject line.
     */
    public string $parsedSubject;

    /**
     * The parsed message content.
     */
    public string $parsedMessage;

    /**
     * The submission data table HTML.
     */
    public string $submissionDataTable;

    /**
     * Whether to show IP address in the email.
     */
    public bool $showIpAddress;

    /**
     * The CC email addresses.
     *
     * @var array<int, string>
     */
    protected array $ccEmails = [];

    /**
     * The BCC email addresses.
     *
     * @var array<int, string>
     */
    protected array $bccEmails = [];

    /**
     * Create a new message instance.
     */
    public function __construct(FormNotification $notification, FormSubmission $submission)
    {
        $this->notification = $notification;
        $this->submission = $submission;

        // Pre-parse templates
        $notificationService = app(NotificationService::class);
        $this->parsedSubject = $notificationService->parseTemplate($notification->subject, $submission);
        $this->parsedMessage = $notificationService->parseTemplate($notification->message, $submission);
        $this->submissionDataTable = $notificationService->formatAllFieldsAsTable($submission);

        // Get CC/BCC recipients upfront
        $this->ccEmails = $notificationService->getCcEmails($notification);
        $this->bccEmails = $notificationService->getBccEmails($notification);

        // Determine whether to show IP address:
        // - Check config setting
        // - Hide for autoresponder emails (privacy: don't show submitter their own IP)
        $this->showIpAddress = config('artisanpack.forms.notifications.show_ip_in_emails', true)
            && $notification->type !== FormNotification::TYPE_AUTORESPONDER;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Build from address
        $from = null;
        if ($this->notification->from_email) {
            $from = new Address(
                $this->notification->from_email,
                $this->notification->from_name ?? config('app.name')
            );
        }

        // Build reply-to
        $replyTo = [];
        $replyToEmail = $this->notification->getReplyToEmail($this->submission);
        if ($replyToEmail && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $replyTo = [new Address($replyToEmail)];
        }

        return new Envelope(
            from: $from,
            cc: $this->ccEmails,
            bcc: $this->bccEmails,
            replyTo: $replyTo,
            subject: $this->parsedSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'forms::emails.notification',
            text: 'forms::emails.notification-text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Future enhancement: attach uploaded files
        return [];
    }
}
