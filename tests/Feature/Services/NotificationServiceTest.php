<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\NotificationService;
use Illuminate\Support\Facades\Queue;

beforeEach( function (): void {
    $this->service = app( NotificationService::class );
} );

describe( 'NotificationService', function (): void {
    describe( 'CRUD operations', function (): void {
        it( 'creates an admin notification with defaults', function (): void {
            $form = Form::factory()->create();

            $notification = $this->service->create( $form, FormNotification::TYPE_ADMIN );

            expect( $notification->type )->toBe( FormNotification::TYPE_ADMIN )
                ->and( $notification->name )->toBe( 'Admin Notification' )
                ->and( $notification->subject )->toContain( '{form_name}' )
                ->and( $notification->is_active )->toBeTrue()
                ->and( $notification->include_submission_data )->toBeTrue();
        } );

        it( 'creates an autoresponder notification with defaults', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['type' => 'email', 'name' => 'contact_email'] );

            $notification = $this->service->create( $form, FormNotification::TYPE_AUTORESPONDER );

            expect( $notification->type )->toBe( FormNotification::TYPE_AUTORESPONDER )
                ->and( $notification->name )->toBe( 'Autoresponder' )
                ->and( $notification->to_field )->toBe( 'contact_email' );
        } );

        it( 'creates a custom notification with defaults', function (): void {
            $form = Form::factory()->create();

            $notification = $this->service->create( $form, FormNotification::TYPE_CUSTOM );

            expect( $notification->type )->toBe( FormNotification::TYPE_CUSTOM )
                ->and( $notification->name )->toBe( 'Custom Notification' );
        } );

        it( 'creates notification with custom data', function (): void {
            $form = Form::factory()->create();

            $notification = $this->service->create( $form, FormNotification::TYPE_ADMIN, [
                'name'     => 'My Custom Name',
                'to_email' => 'custom@example.com',
            ] );

            expect( $notification->name )->toBe( 'My Custom Name' )
                ->and( $notification->to_email )->toBe( 'custom@example.com' );
        } );

        it( 'updates a notification', function (): void {
            $notification = FormNotification::factory()->create( [
                'name' => 'Original Name',
            ] );

            $updated = $this->service->update( $notification, [
                'name'    => 'Updated Name',
                'subject' => 'New Subject',
            ] );

            expect( $updated->name )->toBe( 'Updated Name' )
                ->and( $updated->subject )->toBe( 'New Subject' );
        } );

        it( 'deletes a notification', function (): void {
            $notification = FormNotification::factory()->create();
            $id           = $notification->id;

            $result = $this->service->delete( $notification );

            expect( $result )->toBeTrue()
                ->and( FormNotification::find( $id ) )->toBeNull();
        } );

        it( 'duplicates a notification', function (): void {
            $notification = FormNotification::factory()->create( [
                'name'    => 'Original',
                'subject' => 'Test Subject',
            ] );

            $duplicate = $this->service->duplicate( $notification );

            expect( $duplicate->id )->not->toBe( $notification->id )
                ->and( $duplicate->name )->toBe( 'Original (Copy)' )
                ->and( $duplicate->subject )->toBe( 'Test Subject' )
                ->and( $duplicate->form_id )->toBe( $notification->form_id );
        } );

        it( 'toggles active status', function (): void {
            $notification = FormNotification::factory()->create( ['is_active' => true] );

            $toggled = $this->service->toggleActive( $notification );
            expect( $toggled->is_active )->toBeFalse();

            $toggledAgain = $this->service->toggleActive( $toggled );
            expect( $toggledAgain->is_active )->toBeTrue();
        } );

        it( 'reorders notifications', function (): void {
            $form = Form::factory()->create();
            $n1   = FormNotification::factory()->for( $form )->create( ['sort_order' => 1] );
            $n2   = FormNotification::factory()->for( $form )->create( ['sort_order' => 2] );
            $n3   = FormNotification::factory()->for( $form )->create( ['sort_order' => 3] );

            $this->service->reorder( $form, [$n3->id, $n1->id, $n2->id] );

            expect( $n3->fresh()->sort_order )->toBe( 1 )
                ->and( $n1->fresh()->sort_order )->toBe( 2 )
                ->and( $n2->fresh()->sort_order )->toBe( 3 );
        } );
    } );

    describe( 'sending notifications', function (): void {
        it( 'queues notifications for a submission', function (): void {
            Queue::fake();

            $form = Form::factory()->create();
            FormNotification::factory()->for( $form )->create( ['is_active' => true] );
            FormNotification::factory()->for( $form )->create( ['is_active' => true] );
            $submission = FormSubmission::factory()->for( $form )->create();

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 2 );
            Queue::assertPushed( SendFormNotification::class, 2 );
        } );

        it( 'skips inactive notifications', function (): void {
            Queue::fake();

            $form = Form::factory()->create();
            FormNotification::factory()->for( $form )->create( ['is_active' => true] );
            FormNotification::factory()->for( $form )->create( ['is_active' => false] );
            $submission = FormSubmission::factory()->for( $form )->create();

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 1 );
            Queue::assertPushed( SendFormNotification::class, 1 );
        } );

        it( 'evaluates conditional logic before sending', function (): void {
            Queue::fake();

            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create( ['name' => 'category', 'type' => 'text'] );

            // Notification that should only send when category = "support"
            FormNotification::factory()->for( $form )->create( [
                'is_active'         => true,
                'conditional_logic' => [
                    'action' => 'send',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'category', 'operator' => 'equals', 'value' => 'support'],
                    ],
                ],
            ] );

            // Create submission with category = "sales"
            $submission = FormSubmission::factory()->for( $form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'category',
                'value'      => 'sales',
            ] );

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 0 );
            Queue::assertNotPushed( SendFormNotification::class );
        } );

        it( 'sends notification when conditional logic passes', function (): void {
            Queue::fake();

            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create( ['name' => 'category', 'type' => 'text'] );

            FormNotification::factory()->for( $form )->create( [
                'is_active'         => true,
                'conditional_logic' => [
                    'action' => 'send',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'category', 'operator' => 'equals', 'value' => 'support'],
                    ],
                ],
            ] );

            $submission = FormSubmission::factory()->for( $form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'category',
                'value'      => 'support',
            ] );

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 1 );
            Queue::assertPushed( SendFormNotification::class, 1 );
        } );

        it( 'skips notification when skip action condition is met', function (): void {
            Queue::fake();

            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create( ['name' => 'internal', 'type' => 'checkbox'] );

            FormNotification::factory()->for( $form )->create( [
                'is_active'         => true,
                'conditional_logic' => [
                    'action' => 'skip',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'internal', 'operator' => 'equals', 'value' => '1'],
                    ],
                ],
            ] );

            $submission = FormSubmission::factory()->for( $form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'internal',
                'value'      => '1',
            ] );

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 0 );
            Queue::assertNotPushed( SendFormNotification::class );
        } );

        it( 'suppresses notifications for quarantined submissions', function (): void {
            Queue::fake();

            $form = Form::factory()->create();
            FormNotification::factory()->for( $form )->create( ['is_active' => true] );
            $submission = FormSubmission::factory()->for( $form )->quarantined()->create();

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 0 );
            Queue::assertNotPushed( SendFormNotification::class );
        } );

        it( 'allows overriding the suppression via the filter', function (): void {
            Queue::fake();

            $form = Form::factory()->create();
            FormNotification::factory()->for( $form )->create( ['is_active' => true] );
            $submission = FormSubmission::factory()->for( $form )->quarantined()->create();

            addFilter( 'ap.forms.submission.should_send_notifications', fn (): bool => true );

            $count = $this->service->sendNotifications( $submission );

            expect( $count )->toBe( 1 );
            Queue::assertPushed( SendFormNotification::class, 1 );

            removeAllFilters( 'ap.forms.submission.should_send_notifications' );
        } );
    } );

    describe( 'placeholder system', function (): void {
        it( 'returns available placeholders for a form', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['name' => 'email', 'label' => 'Email Address'] );
            FormField::factory()->for( $form )->create( ['name' => 'name', 'label' => 'Full Name'] );

            $placeholders = $this->service->getAvailablePlaceholders( $form );

            expect( $placeholders )->toHaveKey( '{form_name}' )
                ->and( $placeholders )->toHaveKey( '{submission_number}' )
                ->and( $placeholders )->toHaveKey( '{email}' )
                ->and( $placeholders )->toHaveKey( '{name}' );
        } );

        it( 'parses template with field placeholders', function (): void {
            $form       = Form::factory()->create( ['name' => 'Contact Form'] );
            $submission = FormSubmission::factory()->for( $form )->create( [
                'submission_number' => 'FORM-1-2025-00001',
            ] );
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'name',
                'value'      => 'John Doe',
            ] );
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'email',
                'value'      => 'john@example.com',
            ] );

            $template = 'Hello {name}, your email is {email}. Form: {form_name}. Ref: {submission_number}';
            $parsed   = $this->service->parseTemplate( $template, $submission );

            expect( $parsed )->toBe( 'Hello John Doe, your email is john@example.com. Form: Contact Form. Ref: FORM-1-2025-00001' );
        } );

        it( 'formats all fields as readable plain-text list', function (): void {
            $form       = Form::factory()->create();
            $field1     = FormField::factory()->for( $form )->create( ['name' => 'name', 'label' => 'Name'] );
            $field2     = FormField::factory()->for( $form )->create( ['name' => 'email', 'label' => 'Email'] );
            $submission = FormSubmission::factory()->for( $form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field1->id,
                'field_name' => 'name',
                'value'      => 'John',
            ] );
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field2->id,
                'field_name' => 'email',
                'value'      => 'john@example.com',
            ] );

            $result = $this->service->formatAllFieldsPlainText( $submission );

            expect( $result )->toContain( 'Name: John' )
                ->and( $result )->toContain( 'Email: john@example.com' );
        } );

        it( 'formats all fields as HTML table', function (): void {
            $form       = Form::factory()->create();
            $field      = FormField::factory()->for( $form )->create( ['name' => 'name', 'label' => 'Name'] );
            $submission = FormSubmission::factory()->for( $form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'name',
                'value'      => 'John',
            ] );

            $result = $this->service->formatAllFieldsAsTable( $submission );

            expect( $result )->toContain( '<table' )
                ->and( $result )->toContain( 'Name' )
                ->and( $result )->toContain( 'John' );
        } );
    } );

    describe( 'email address helpers', function (): void {
        it( 'gets CC emails as array', function (): void {
            $notification = FormNotification::factory()->create( [
                'cc_emails' => 'cc1@example.com, cc2@example.com',
            ] );

            $emails = $this->service->getCcEmails( $notification );

            expect( $emails )->toBe( ['cc1@example.com', 'cc2@example.com'] );
        } );

        it( 'returns empty array when no CC emails', function (): void {
            $notification = FormNotification::factory()->create( [
                'cc_emails' => null,
            ] );

            expect( $this->service->getCcEmails( $notification ) )->toBe( [] );
        } );

        it( 'gets BCC emails as array', function (): void {
            $notification = FormNotification::factory()->create( [
                'bcc_emails' => 'bcc@example.com',
            ] );

            $emails = $this->service->getBccEmails( $notification );

            expect( $emails )->toBe( ['bcc@example.com'] );
        } );
    } );
} );
