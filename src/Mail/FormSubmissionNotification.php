<?php

/**
 * Form submission notification mailable.
 *
 * Handles email notification sending for form submissions.
 * Supports dynamic recipients, placeholder parsing, and
 * conditional submission data inclusion.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

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
 * Form submission notification mailable class.
 *
 * Handles email notification sending for form submissions.
 * Supports dynamic recipients, placeholder parsing, and
 * conditional submission data inclusion.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormSubmissionNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The form notification configuration.
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
     * The parsed subject line.
     *
     * @since 1.0.0
     */
    public string $parsedSubject;

    /**
     * The parsed message content.
     *
     * @since 1.0.0
     */
    public string $parsedMessage;

    /**
     * The submission data table HTML.
     *
     * @since 1.0.0
     */
    public string $submissionDataTable;

    /**
     * Whether to show IP address in the email.
     *
     * @since 1.0.0
     */
    public bool $showIpAddress;

    /**
     * The CC email addresses.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected array $ccEmails = [];

    /**
     * The BCC email addresses.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected array $bccEmails = [];

    /**
     * Creates a new message instance.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification configuration.
     * @param FormSubmission   $submission   The form submission data.
     */
    public function __construct( FormNotification $notification, FormSubmission $submission )
    {
        $this->notification = $notification;
        $this->submission   = $submission;

        // Pre-parse templates
        $notificationService = app( NotificationService::class );
        $this->parsedSubject = $notificationService->parseTemplate( $notification->subject, $submission );
        $this->parsedMessage = $notificationService->parseTemplate( $notification->message, $submission );

        // Apply filter hook to allow modifying the message
        if ( function_exists( 'applyFilters' ) ) {
            $this->parsedMessage = applyFilters(
                'forms.notification_message',
                $this->parsedMessage,
                $notification,
                $submission,
            );
        }

        $this->submissionDataTable = $notificationService->formatAllFieldsAsTable( $submission );

        // Get CC/BCC recipients upfront
        $this->ccEmails  = $notificationService->getCcEmails( $notification );
        $this->bccEmails = $notificationService->getBccEmails( $notification );

        // Determine whether to show IP address:
        // - Check config setting
        // - Hide for autoresponder emails (privacy: don't show submitter their own IP)
        $this->showIpAddress = config( 'artisanpack.forms.notifications.show_ip_in_emails', true )
            && FormNotification::TYPE_AUTORESPONDER !== $notification->type;
    }

    /**
     * Gets the message envelope.
     *
     * @since 1.0.0
     *
     * @return Envelope The email envelope with from, cc, bcc, reply-to and subject.
     */
    public function envelope(): Envelope
    {
        // Build from address
        $from = null;
        if ( $this->notification->from_email ) {
            $from = new Address(
                $this->notification->from_email,
                $this->notification->from_name ?? config( 'app.name' ),
            );
        }

        // Build reply-to
        $replyTo      = [];
        $replyToEmail = $this->notification->getReplyToEmail( $this->submission );
        if ( $replyToEmail && filter_var( $replyToEmail, FILTER_VALIDATE_EMAIL ) ) {
            $replyTo = [new Address( $replyToEmail )];
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
     * Gets the message content definition.
     *
     * @since 1.0.0
     *
     * @return Content The email content with view and text templates.
     */
    public function content(): Content
    {
        return new Content(
            view: 'forms::emails.notification',
            text: 'forms::emails.notification-text',
        );
    }

    /**
     * Gets the attachments for the message.
     *
     * @since 1.0.0
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment> The message attachments.
     */
    public function attachments(): array
    {
        // Future enhancement: attach uploaded files
        return [];
    }
}
