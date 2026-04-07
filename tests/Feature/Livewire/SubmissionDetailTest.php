<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Livewire\Livewire;

describe( 'SubmissionDetail Livewire Component', function (): void {
    describe( 'rendering', function (): void {
        it( 'renders the component', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertStatus( 200 );
        } );

        it( 'displays submission number', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'submission_number' => 'FORM-2025-00001',
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'FORM-2025-00001' );
        } );

        it( 'displays form name', function (): void {
            $form       = Form::factory()->create( ['name' => 'Contact Form'] );
            $submission = FormSubmission::factory()->for( $form )->create();

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'Contact Form' );
        } );

        it( 'displays submission values', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            FormSubmissionValue::factory()->create( [
                'submission_id' => $submission->id,
                'field_label'   => 'Email Address',
                'value'         => 'john@example.com',
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'Email Address' )
                ->assertSee( 'john@example.com' );
        } );
    } );

    describe( 'auto-read', function (): void {
        it( 'marks submission as read on mount', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( ['is_read' => false] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] );

            expect( $submission->fresh()->is_read )->toBeTrue();
        } );
    } );

    describe( 'actions', function (): void {
        it( 'toggles starred status', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( ['is_starred' => false] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->call( 'toggleStar' )
                ->assertHasNoErrors();

            expect( $submission->fresh()->is_starred )->toBeTrue();
        } );

        it( 'toggles spam status', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( ['is_spam' => false] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->call( 'toggleSpam' )
                ->assertHasNoErrors();

            expect( $submission->fresh()->is_spam )->toBeTrue();
        } );

        it( 'marks as read', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( ['is_read' => false] );

            // Mount the component (which auto-marks as read)
            $component = Livewire::test( SubmissionDetail::class, ['submission' => $submission] );

            // Reset the submission to unread state after mount to properly test markAsRead
            $submission->update( ['is_read' => false] );
            expect( $submission->fresh()->is_read )->toBeFalse();

            // Now call markAsRead and verify it works
            $component->call( 'markAsRead' )
                ->assertHasNoErrors();

            expect( $submission->fresh()->is_read )->toBeTrue();
        } );

        it( 'marks as unread', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( ['is_read' => true] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->call( 'markAsUnread' )
                ->assertHasNoErrors();

            expect( $submission->fresh()->is_read )->toBeFalse();
        } );

        it( 'deletes the submission', function (): void {
            $form         = Form::factory()->create();
            $submission   = FormSubmission::factory()->for( $form )->create();
            $submissionId = $submission->id;

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->call( 'delete' )
                ->assertHasNoErrors();

            expect( FormSubmission::find( $submissionId ) )->toBeNull();
        } );
    } );

    describe( 'admin notes', function (): void {
        it( 'loads admin notes on mount', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'admin_notes' => 'This is a test note',
            ] );

            $component = Livewire::test( SubmissionDetail::class, ['submission' => $submission] );

            expect( $component->get( 'adminNotes' ) )->toBe( 'This is a test note' );
        } );

        it( 'saves admin notes on change', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( ['admin_notes' => null] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->set( 'adminNotes', 'New note content' )
                ->assertHasNoErrors();

            expect( $submission->fresh()->admin_notes )->toBe( 'New note content' );
        } );
    } );

    describe( 'navigation', function (): void {
        it( 'has previous submission navigation when not first', function (): void {
            $form        = Form::factory()->create();
            $submission1 = FormSubmission::factory()->for( $form )->create();
            $submission2 = FormSubmission::factory()->for( $form )->create();

            // When viewing submission2, the previous button should be enabled
            Livewire::test( SubmissionDetail::class, ['submission' => $submission2] )
                ->assertSee( 'Previous submission' ); // aria-label in the button
        } );

        it( 'has next submission navigation when not last', function (): void {
            $form        = Form::factory()->create();
            $submission1 = FormSubmission::factory()->for( $form )->create();
            $submission2 = FormSubmission::factory()->for( $form )->create();

            // When viewing submission1, the next button should be enabled
            Livewire::test( SubmissionDetail::class, ['submission' => $submission1] )
                ->assertSee( 'Next submission' ); // aria-label in the button
        } );

        it( 'can navigate to previous submission', function (): void {
            $form        = Form::factory()->create();
            $submission1 = FormSubmission::factory()->for( $form )->create();
            $submission2 = FormSubmission::factory()->for( $form )->create();

            Livewire::test( SubmissionDetail::class, ['submission' => $submission2] )
                ->call( 'goToPrevious' )
                ->assertHasNoErrors();
        } );

        it( 'can navigate to next submission', function (): void {
            $form        = Form::factory()->create();
            $submission1 = FormSubmission::factory()->for( $form )->create();
            $submission2 = FormSubmission::factory()->for( $form )->create();

            Livewire::test( SubmissionDetail::class, ['submission' => $submission1] )
                ->call( 'goToNext' )
                ->assertHasNoErrors();
        } );
    } );

    describe( 'metadata display', function (): void {
        it( 'displays page URL', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'page_url' => 'https://example.com/contact',
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'example.com/contact' );
        } );

        it( 'displays IP address', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'ip_address' => '192.168.1.100',
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( '192.168.1.100' );
        } );

        it( 'displays submission status badges', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'is_starred' => true,
                'is_spam'    => true,
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'Starred' )
                ->assertSee( 'Spam' );
        } );
    } );

    describe( 'field value types', function (): void {
        it( 'displays array values as list', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            FormSubmissionValue::factory()->create( [
                'submission_id' => $submission->id,
                'field_label'   => 'Interests',
                'value'         => null,
                'value_array'   => ['Sports', 'Music', 'Travel'],
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'Interests' )
                ->assertSee( 'Sports' )
                ->assertSee( 'Music' )
                ->assertSee( 'Travel' );
        } );

        it( 'displays empty value placeholder', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            FormSubmissionValue::factory()->create( [
                'submission_id' => $submission->id,
                'field_label'   => 'Optional Field',
                'value'         => null,
                'value_array'   => null,
            ] );

            Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
                ->assertSee( 'Optional Field' )
                ->assertSee( 'No response' );
        } );
    } );
});
