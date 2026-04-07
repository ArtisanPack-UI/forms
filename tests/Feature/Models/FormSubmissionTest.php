<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;

describe( 'FormSubmission Model', function (): void {
    describe( 'factory', function (): void {
        it( 'creates a valid submission', function (): void {
            $submission = FormSubmission::factory()->create();

            expect( $submission )->toBeInstanceOf( FormSubmission::class )
                ->and( $submission->submission_number )->not->toBeEmpty()
                ->and( $submission->form )->toBeInstanceOf( Form::class );
        } );

        it( 'creates a read submission', function (): void {
            $submission = FormSubmission::factory()->read()->create();

            expect( $submission->is_read )->toBeTrue();
        } );

        it( 'creates a spam submission', function (): void {
            $submission = FormSubmission::factory()->spam()->create();

            expect( $submission->is_spam )->toBeTrue();
        } );

        it( 'creates a starred submission', function (): void {
            $submission = FormSubmission::factory()->starred()->create();

            expect( $submission->is_starred )->toBeTrue();
        } );

        it( 'creates an old submission', function (): void {
            $submission = FormSubmission::factory()->old( 60 )->create();

            expect( $submission->created_at->diffInDays( now() ) )->toBeGreaterThanOrEqual( 59 );
        } );
    } );

    describe( 'boot', function (): void {
        it( 'auto-generates submission number', function (): void {
            $submission = FormSubmission::factory()->create( ['submission_number' => null] );

            // Format from config: FORM-{year}-{sequence}
            expect( $submission->submission_number )->toMatch( '/^FORM-\d{4}-\d{5}$/' );
        } );

        it( 'increments submission number for same form', function (): void {
            $form = Form::factory()->create();
            $sub1 = FormSubmission::factory()->for( $form )->create();
            $sub2 = FormSubmission::factory()->for( $form )->create();

            expect( $sub1->submission_number )->not->toBe( $sub2->submission_number );
        } );
    } );

    describe( 'relationships', function (): void {
        it( 'belongs to a form', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            expect( $submission->form->id )->toBe( $form->id );
        } );

        it( 'has many values', function (): void {
            $submission = FormSubmission::factory()->create();
            FormSubmissionValue::factory()->count( 3 )->for( $submission, 'submission' )->create();

            expect( $submission->values )->toHaveCount( 3 );
        } );

        it( 'has many uploads', function (): void {
            $submission = FormSubmission::factory()->create();
            FormUpload::factory()->count( 2 )->for( $submission, 'submission' )->create();

            expect( $submission->uploads )->toHaveCount( 2 );
        } );
    } );

    describe( 'scopes', function (): void {
        it( 'filters unread submissions', function (): void {
            FormSubmission::factory()->create( ['is_read' => false] );
            FormSubmission::factory()->create( ['is_read' => true] );

            expect( FormSubmission::unread()->count() )->toBe( 1 );
        } );

        it( 'filters read submissions', function (): void {
            FormSubmission::factory()->create( ['is_read' => false] );
            FormSubmission::factory()->create( ['is_read' => true] );

            expect( FormSubmission::read()->count() )->toBe( 1 );
        } );

        it( 'filters not spam submissions', function (): void {
            FormSubmission::factory()->create( ['is_spam' => false] );
            FormSubmission::factory()->create( ['is_spam' => true] );

            expect( FormSubmission::notSpam()->count() )->toBe( 1 );
        } );

        it( 'filters spam submissions', function (): void {
            FormSubmission::factory()->create( ['is_spam' => false] );
            FormSubmission::factory()->create( ['is_spam' => true] );

            expect( FormSubmission::spam()->count() )->toBe( 1 );
        } );

        it( 'filters starred submissions', function (): void {
            FormSubmission::factory()->create( ['is_starred' => false] );
            FormSubmission::factory()->create( ['is_starred' => true] );

            expect( FormSubmission::starred()->count() )->toBe( 1 );
        } );

        it( 'filters recent submissions', function (): void {
            FormSubmission::factory()->create( ['created_at' => now()] );
            FormSubmission::factory()->create( ['created_at' => now()->subDays( 60 )] );

            expect( FormSubmission::recent( 30 )->count() )->toBe( 1 );
        } );
    } );

    describe( 'accessors', function (): void {
        it( 'gets data as collection', function (): void {
            $submission = FormSubmission::factory()->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'name',
                'value'      => 'John Doe',
            ] );

            expect( $submission->data )->toBeInstanceOf( Illuminate\Support\Collection::class )
                ->and( $submission->data->get( 'name' ) )->toBe( 'John Doe' );
        } );

        it( 'gets data as array', function (): void {
            $submission = FormSubmission::factory()->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'email',
                'value'      => 'test@example.com',
            ] );

            expect( $submission->data_array )->toBeArray()
                ->and( $submission->data_array['email'] )->toBe( 'test@example.com' );
        } );
    } );

    describe( 'methods', function (): void {
        it( 'marks as read', function (): void {
            $submission = FormSubmission::factory()->unread()->create();

            $submission->markAsRead();

            expect( $submission->fresh()->is_read )->toBeTrue();
        } );

        it( 'marks as unread', function (): void {
            $submission = FormSubmission::factory()->read()->create();

            $submission->markAsUnread();

            expect( $submission->fresh()->is_read )->toBeFalse();
        } );

        it( 'toggles spam', function (): void {
            $submission = FormSubmission::factory()->create( ['is_spam' => false] );

            $submission->toggleSpam();
            expect( $submission->fresh()->is_spam )->toBeTrue();

            $submission->toggleSpam();
            expect( $submission->fresh()->is_spam )->toBeFalse();
        } );

        it( 'toggles star', function (): void {
            $submission = FormSubmission::factory()->create( ['is_starred' => false] );

            $submission->toggleStar();
            expect( $submission->fresh()->is_starred )->toBeTrue();

            $submission->toggleStar();
            expect( $submission->fresh()->is_starred )->toBeFalse();
        } );

        it( 'gets value by field name', function (): void {
            $submission = FormSubmission::factory()->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'company',
                'value'      => 'Acme Inc',
            ] );

            expect( $submission->getValue( 'company' ) )->toBe( 'Acme Inc' )
                ->and( $submission->getValue( 'missing' ) )->toBeNull();
        } );

        it( 'gets email value', function (): void {
            $submission = FormSubmission::factory()->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'contact_email',
                'field_type' => 'email',
                'value'      => 'contact@example.com',
            ] );

            expect( $submission->getEmailValue() )->toBe( 'contact@example.com' );
        } );
    } );
});
