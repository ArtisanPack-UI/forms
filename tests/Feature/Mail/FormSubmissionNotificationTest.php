<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Mail\Mailables\Address;

describe('FormSubmissionNotification Mailable', function (): void {
    describe('construction', function (): void {
        it('parses subject and message on construction', function (): void {
            $form = Form::factory()->create(['name' => 'Contact Us']);
            $notification = FormNotification::factory()->for($form)->create([
                'subject' => 'New message from {form_name}',
                'message' => 'Hello, {name} has contacted you.',
            ]);
            $submission = FormSubmission::factory()->for($form)->create();
            FormSubmissionValue::factory()->for($submission, 'submission')->create([
                'field_name' => 'name',
                'value' => 'John',
            ]);

            $mailable = new FormSubmissionNotification($notification, $submission);

            expect($mailable->parsedSubject)->toBe('New message from Contact Us')
                ->and($mailable->parsedMessage)->toContain('Hello, John has contacted you.');
        });

        it('generates submission data table', function (): void {
            $form = Form::factory()->create();
            $field = FormField::factory()->for($form)->create(['name' => 'email', 'label' => 'Email Address']);
            $notification = FormNotification::factory()->for($form)->create([
                'include_submission_data' => true,
            ]);
            $submission = FormSubmission::factory()->for($form)->create();
            FormSubmissionValue::factory()->for($submission, 'submission')->create([
                'field_id' => $field->id,
                'field_name' => 'email',
                'value' => 'test@example.com',
            ]);

            $mailable = new FormSubmissionNotification($notification, $submission);

            expect($mailable->submissionDataTable)->toContain('table')
                ->and($mailable->submissionDataTable)->toContain('Email Address')
                ->and($mailable->submissionDataTable)->toContain('test@example.com');
        });
    });

    describe('envelope', function (): void {
        it('sets subject from parsed template', function (): void {
            $notification = FormNotification::factory()->create([
                'subject' => 'Test Subject',
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            expect($envelope->subject)->toBe('Test Subject');
        });

        it('sets from address when configured', function (): void {
            $notification = FormNotification::factory()->create([
                'from_email' => 'noreply@example.com',
                'from_name' => 'My Company',
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            expect($envelope->from)->toBeInstanceOf(Address::class)
                ->and($envelope->from->address)->toBe('noreply@example.com')
                ->and($envelope->from->name)->toBe('My Company');
        });

        it('does not set from when not configured', function (): void {
            $notification = FormNotification::factory()->create([
                'from_email' => null,
                'from_name' => null,
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            expect($envelope->from)->toBeNull();
        });

        it('sets reply-to from static email', function (): void {
            $notification = FormNotification::factory()->create([
                'reply_to_email' => 'support@example.com',
                'reply_to_field' => null,
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            expect($envelope->replyTo)->toHaveCount(1)
                ->and($envelope->replyTo[0]->address)->toBe('support@example.com');
        });

        it('sets reply-to from field value', function (): void {
            $notification = FormNotification::factory()->create([
                'reply_to_email' => null,
                'reply_to_field' => 'contact_email',
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();
            FormSubmissionValue::factory()->for($submission, 'submission')->create([
                'field_name' => 'contact_email',
                'value' => 'user@example.com',
            ]);

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            expect($envelope->replyTo)->toHaveCount(1)
                ->and($envelope->replyTo[0]->address)->toBe('user@example.com');
        });

        it('skips invalid reply-to emails', function (): void {
            $notification = FormNotification::factory()->create([
                'reply_to_email' => 'not-an-email',
                'reply_to_field' => null,
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            // Invalid email should result in empty or null replyTo
            expect($envelope->replyTo)->toBeEmpty();
        });
    });

    describe('content', function (): void {
        it('uses the notification view', function (): void {
            $notification = FormNotification::factory()->create();
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $content = $mailable->content();

            expect($content->view)->toBe('forms::emails.notification')
                ->and($content->text)->toBe('forms::emails.notification-text');
        });
    });

    describe('envelope cc/bcc', function (): void {
        it('includes CC recipients in envelope', function (): void {
            $notification = FormNotification::factory()->create([
                'cc_emails' => 'cc1@example.com, cc2@example.com',
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            $ccAddresses = array_map(fn ($addr) => $addr->address, $envelope->cc);
            expect($ccAddresses)->toContain('cc1@example.com')
                ->and($ccAddresses)->toContain('cc2@example.com');
        });

        it('includes BCC recipients in envelope', function (): void {
            $notification = FormNotification::factory()->create([
                'bcc_emails' => 'bcc@example.com',
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            $bccAddresses = array_map(fn ($addr) => $addr->address, $envelope->bcc);
            expect($bccAddresses)->toContain('bcc@example.com');
        });

        it('has empty CC when not configured', function (): void {
            $notification = FormNotification::factory()->create([
                'cc_emails' => null,
            ]);
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $envelope = $mailable->envelope();

            expect($envelope->cc)->toBeEmpty();
        });
    });

    describe('attachments', function (): void {
        it('returns empty attachments by default', function (): void {
            $notification = FormNotification::factory()->create();
            $submission = FormSubmission::factory()->for($notification->form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);

            expect($mailable->attachments())->toBeEmpty();
        });
    });

    describe('rendering', function (): void {
        it('can be rendered', function (): void {
            $form = Form::factory()->create(['name' => 'Test Form']);
            $notification = FormNotification::factory()->for($form)->create([
                'subject' => 'Test Subject',
                'message' => 'Test message content',
                'include_submission_data' => true,
            ]);
            $submission = FormSubmission::factory()->for($form)->create();

            $mailable = new FormSubmissionNotification($notification, $submission);
            $rendered = $mailable->render();

            expect($rendered)->toContain('Test Form')
                ->and($rendered)->toContain('Test message content');
        });
    });
});
