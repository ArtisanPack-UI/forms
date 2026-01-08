<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

describe( 'FormNotification Model', function (): void {
    describe( 'constants', function (): void {
        it( 'defines notification types', function (): void {
            expect( FormNotification::TYPE_ADMIN )->toBe( 'admin' )
                ->and( FormNotification::TYPE_AUTORESPONDER )->toBe( 'autoresponder' )
                ->and( FormNotification::TYPE_CUSTOM )->toBe( 'custom' );
        } );
    } );

    describe( 'factory', function (): void {
        it( 'creates a valid notification', function (): void {
            $notification = FormNotification::factory()->create();

            expect( $notification )->toBeInstanceOf( FormNotification::class )
                ->and( $notification->name )->not->toBeEmpty()
                ->and( $notification->form )->toBeInstanceOf( Form::class );
        } );

        it( 'creates an admin notification', function (): void {
            $notification = FormNotification::factory()->admin()->create();

            expect( $notification->type )->toBe( FormNotification::TYPE_ADMIN )
                ->and( $notification->include_submission_data )->toBeTrue();
        } );

        it( 'creates an autoresponder notification', function (): void {
            $notification = FormNotification::factory()->autoresponder()->create();

            expect( $notification->type )->toBe( FormNotification::TYPE_AUTORESPONDER )
                ->and( $notification->to_field )->toBe( 'email' )
                ->and( $notification->include_submission_data )->toBeFalse();
        } );

        it( 'creates a custom notification', function (): void {
            $notification = FormNotification::factory()->custom()->create();

            expect( $notification->type )->toBe( FormNotification::TYPE_CUSTOM );
        } );

        it( 'creates an inactive notification', function (): void {
            $notification = FormNotification::factory()->inactive()->create();

            expect( $notification->is_active )->toBeFalse();
        } );

        it( 'creates a notification with conditions', function (): void {
            $notification = FormNotification::factory()->withConditions()->create();

            expect( $notification->conditional_logic )->not->toBeEmpty()
                ->and( $notification->has_conditional_logic )->toBeTrue();
        } );
    } );

    describe( 'relationships', function (): void {
        it( 'belongs to a form', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create();

            expect( $notification->form->id )->toBe( $form->id );
        } );
    } );

    describe( 'scopes', function (): void {
        it( 'filters active notifications', function (): void {
            FormNotification::factory()->create( ['is_active' => true] );
            FormNotification::factory()->create( ['is_active' => false] );

            expect( FormNotification::active()->count() )->toBe( 1 );
        } );

        it( 'filters by type', function (): void {
            FormNotification::factory()->admin()->create();
            FormNotification::factory()->autoresponder()->create();

            expect( FormNotification::ofType( FormNotification::TYPE_ADMIN )->count() )->toBe( 1 );
        } );

        it( 'filters admin notifications', function (): void {
            FormNotification::factory()->admin()->create();
            FormNotification::factory()->autoresponder()->create();

            expect( FormNotification::admin()->count() )->toBe( 1 );
        } );

        it( 'filters autoresponder notifications', function (): void {
            FormNotification::factory()->admin()->create();
            FormNotification::factory()->autoresponder()->create();

            expect( FormNotification::autoresponder()->count() )->toBe( 1 );
        } );
    } );

    describe( 'accessors', function (): void {
        it( 'detects conditional logic', function (): void {
            $withConditions    = FormNotification::factory()->withConditions()->create();
            $withoutConditions = FormNotification::factory()->create( ['conditional_logic' => null] );

            expect( $withConditions->has_conditional_logic )->toBeTrue()
                ->and( $withoutConditions->has_conditional_logic )->toBeFalse();
        } );

        it( 'detects autoresponder', function (): void {
            $autoresponder = FormNotification::factory()->autoresponder()->create();
            $admin         = FormNotification::factory()->admin()->create();

            expect( $autoresponder->is_autoresponder )->toBeTrue()
                ->and( $admin->is_autoresponder )->toBeFalse();
        } );
    } );

    describe( 'methods', function (): void {
        it( 'gets recipient emails from static email', function (): void {
            $notification = FormNotification::factory()->create( [
                'to_email' => 'admin@example.com, manager@example.com',
                'to_field' => null,
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $emails = $notification->getRecipientEmails( $submission );

            expect( $emails )->toBe( ['admin@example.com', 'manager@example.com'] );
        } );

        it( 'gets recipient emails from field', function (): void {
            $notification = FormNotification::factory()->create( [
                'to_email' => null,
                'to_field' => 'email',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'email',
                'value'      => 'user@example.com',
            ] );

            $emails = $notification->getRecipientEmails( $submission );

            expect( $emails )->toBe( ['user@example.com'] );
        } );

        it( 'gets reply-to email from field', function (): void {
            $notification = FormNotification::factory()->create( [
                'reply_to_email' => null,
                'reply_to_field' => 'contact_email',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'contact_email',
                'value'      => 'reply@example.com',
            ] );

            expect( $notification->getReplyToEmail( $submission ) )->toBe( 'reply@example.com' );
        } );

        it( 'gets reply-to email from static value', function (): void {
            $notification = FormNotification::factory()->create( [
                'reply_to_email' => 'noreply@example.com',
                'reply_to_field' => null,
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            expect( $notification->getReplyToEmail( $submission ) )->toBe( 'noreply@example.com' );
        } );

        it( 'parses message with placeholders', function (): void {
            $form         = Form::factory()->create( ['name' => 'Contact Form'] );
            $notification = FormNotification::factory()->for( $form )->create( [
                'message' => 'Hello {name}, thank you for contacting {form_name}. Your submission #{submission_number} was received.',
            ] );
            $submission = FormSubmission::factory()->for( $form )->create( [
                'submission_number' => 'FORM-1-2025-00001',
            ] );
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'name',
                'value'      => 'John',
            ] );

            $parsed = $notification->parseMessage( $submission );

            expect( $parsed )->toContain( 'Hello John' )
                ->and( $parsed )->toContain( 'Contact Form' )
                ->and( $parsed )->toContain( 'FORM-1-2025-00001' );
        } );

        it( 'parses subject with placeholders', function (): void {
            $form         = Form::factory()->create( ['name' => 'Support Form']);
            $notification = FormNotification::factory()->for( $form)->create( [
                'subject' => 'New submission from {form_name}: {submission_number}',
            ]);
            $submission = FormSubmission::factory()->for( $form)->create( [
                'submission_number' => 'FORM-2-2025-00005',
            ]);

            $parsed = $notification->parseSubject( $submission);

            expect( $parsed)->toBe( 'New submission from Support Form: FORM-2-2025-00005');
        });
    });
});
