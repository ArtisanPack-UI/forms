<?php

/**
 * Form submission notification mailable.
 *
 * Handles email notification sending for form submissions.
 * Supports dynamic recipients, placeholder parsing, and
 * conditional submission data inclusion.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Mail;

use ArtisanPackUI\Forms\FieldTypes\SignatureFieldType;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Support\TransientUploads;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/**
 * Form submission notification mailable class.
 *
 * Handles email notification sending for form submissions.
 * Supports dynamic recipients, placeholder parsing, and
 * conditional submission data inclusion.
 *
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
     * Whether fields are labelled by their admin label.
     *
     * Off for autoresponders, which the visitor reads.
     *
     * @since 1.6.0
     */
    public bool $useAdminLabels;

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
     * The IDs of the signature uploads the submission data table shows, embedded when the message is built.
     *
     * @since 1.6.0
     *
     * @var list<int>
     */
    protected array $inlineImageIds = [];

    /**
     * Whether build() has embedded the inline images, so a render before sending doesn't embed them twice.
     *
     * @since 1.6.0
     */
    protected bool $inlineImagesEmbedded = false;

    /**
     * Creates a new message instance.
     *
     * @since 1.0.0
     *
     * @param  FormNotification  $notification  The notification configuration.
     * @param  FormSubmission  $submission  The form submission data.
     */
    public function __construct( FormNotification $notification, FormSubmission $submission )
    {
        $this->notification = $notification;
        $this->submission   = $submission;

        // Pre-parse templates
        $notificationService  = app( NotificationService::class );
        $this->useAdminLabels = FormNotification::TYPE_AUTORESPONDER !== $notification->type;
        $this->parsedSubject  = $notificationService->parseTemplate( $notification->subject, $submission, $this->useAdminLabels );
        $this->parsedMessage  = $notificationService->parseTemplate( $notification->message, $submission, $this->useAdminLabels );

        // Apply filter hooks to allow modifying the message
        $this->parsedMessage = applyFilters(
            'ap.forms.notificationMessage',
            $this->parsedMessage,
            $notification,
            $submission,
        );

        $this->parsedSubject = applyFilters(
            'ap.forms.notificationSubject',
            $this->parsedSubject,
            $notification,
            $submission,
        );

        $this->parsedMessage = applyFilters(
            'ap.forms.notificationBody',
            $this->parsedMessage,
            $notification,
            $submission,
        );

        // Drawn signatures are embedded as inline images, which email clients show where they block data URLs
        $this->submissionDataTable = $notificationService->formatAllFieldsAsTable(
            $submission,
            $this->useAdminLabels,
            $notification->include_submission_data ? fn ( FormUpload $upload ): string => $this->inlineImageSource( $upload ) : null,
        );

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
     * Embeds the drawn signatures the submission data table shows.
     *
     * Runs when the message is built for sending, so the PNGs are read then
     * rather than kept on a queued mailable. A file that can no longer be read
     * is left out, and the image shows its alternative text.
     *
     * @since 1.6.0
     */
    public function build(): void
    {
        if ( $this->inlineImagesEmbedded || [] === $this->inlineImageIds ) {
            return;
        }

        $this->inlineImagesEmbedded = true;

        foreach ( FormUpload::query()->whereIn( 'id', $this->inlineImageIds )->get() as $upload ) {
            $png = SignatureFieldType::contents( $upload );

            if ( null !== $png ) {
                $name = self::inlineImageName( $upload );

                $this->withSymfonyMessage( fn ( Email $email ): Email => $email->embed( $png, $name, 'image/png' ) );
            }
        }
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
     * Since 1.6.0, the files uploaded to "Do not store" file fields are
     * attached when the notification includes the submission data, since
     * they are deleted once the submission's deliveries are sent (see
     * TransientUploads).
     *
     * A notification whose recipient comes from the submission (an
     * autoresponder, or any notification with a recipient field) never
     * carries the files: the visitor chooses that address, so attaching
     * would let anyone have the site mail a file of their choosing to
     * someone else.
     *
     * @since 1.0.0
     *
     * @return array<int, Attachment> The message attachments.
     */
    public function attachments(): array
    {
        if ( ! $this->notification->include_submission_data || $this->sendsToSubmittedAddress() ) {
            return [];
        }

        return array_map(
            fn ( FormUpload $upload ): Attachment => Attachment::fromStorageDisk( $upload->disk, $upload->path )
                ->as( $upload->original_name )
                ->withMime( $upload->mime_type ?? 'application/octet-stream' ),
            TransientUploads::uploadsOf( $this->submission ),
        );
    }

    /**
     * Checks whether the notification goes to an address taken from the submission.
     *
     * @since 1.6.0
     *
     * @return bool True for an autoresponder or a notification with a recipient field.
     */
    protected function sendsToSubmittedAddress(): bool
    {
        return FormNotification::TYPE_AUTORESPONDER === $this->notification->type
            || ( is_string( $this->notification->to_field ) && '' !== $this->notification->to_field );
    }

    /**
     * Gets the name a signature's image is embedded under.
     *
     * The HTML references it as `cid:{name}`; Symfony swaps in the part's
     * content ID when the message is sent.
     *
     * @since 1.6.0
     *
     * @param  FormUpload  $upload  The signature's upload.
     *
     * @return string The name.
     */
    protected static function inlineImageName( FormUpload $upload ): string
    {
        return 'signature-' . $upload->id . '.png';
    }

    /**
     * Registers a signature's upload to embed, and gets its image source.
     *
     * @since 1.6.0
     *
     * @param  FormUpload  $upload  The signature's upload.
     *
     * @return string The `cid:` source.
     */
    protected function inlineImageSource( FormUpload $upload ): string
    {
        $this->inlineImageIds[] = (int) $upload->id;

        return 'cid:' . self::inlineImageName( $upload );
    }
}
