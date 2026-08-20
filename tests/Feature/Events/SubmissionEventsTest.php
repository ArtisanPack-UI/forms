<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Events\SubmissionDeleted;
use ArtisanPackUI\Forms\Events\SubmissionUpdated;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;

describe( 'Submission Events', function (): void {
    describe( 'FormSubmitted event', function (): void {
        it( 'dispatches FormSubmitted event when submission is created', function (): void {
            Event::fake( [FormSubmitted::class] );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            Event::assertDispatched( FormSubmitted::class, function ( FormSubmitted $event ) use ( $submission ) {
                return $event->submission->id === $submission->id;
            } );
        } );

        // Regression: the submission's field values are written *after* the
        // FormSubmission row in SubmissionService::create(), inside the same
        // transaction. FormSubmitted is dispatched from the model's `created`
        // hook, which runs before those values exist, so a listener that reads
        // `$event->submission->values` (the bookings appointment listener, the
        // webhook dispatcher, the ConvertKit sync) would see an empty submission.
        // Deferring the event until the transaction commits is what gives every
        // listener the complete submission it is documented to receive; if this
        // contract is dropped, form-driven bookings silently stop being created.
        it( 'defers FormSubmitted until after the submission transaction commits', function (): void {
            expect( new FormSubmitted( new FormSubmission() ) )
                ->toBeInstanceOf( ShouldDispatchAfterCommit::class );
        } );

        it( 'fires ap.forms.submission.created action hook when submission is created', function (): void {
            $hookFired          = false;
            $receivedSubmission = null;

            addAction( 'ap.forms.submission.created', function ( FormSubmission $submission ) use ( &$hookFired, &$receivedSubmission ): void {
                $hookFired          = true;
                $receivedSubmission = $submission;
            } );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            expect( $hookFired )->toBeTrue()
                ->and( $receivedSubmission )->not->toBeNull()
                ->and( $receivedSubmission->id )->toBe( $submission->id );
        } );
    } );

    describe( 'SubmissionUpdated event', function (): void {
        it( 'dispatches SubmissionUpdated event when submission is updated', function (): void {
            Event::fake( [SubmissionUpdated::class] );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();
            $submission->update( ['is_read' => true] );

            Event::assertDispatched( SubmissionUpdated::class, function ( SubmissionUpdated $event ) use ( $submission ) {
                return $event->submission->id === $submission->id;
            } );
        } );

        it( 'fires ap.forms.submission.updated action hook when submission is updated', function (): void {
            $hookFired          = false;
            $receivedSubmission = null;

            addAction( 'ap.forms.submission.updated', function ( FormSubmission $submission ) use ( &$hookFired, &$receivedSubmission ): void {
                $hookFired          = true;
                $receivedSubmission = $submission;
            } );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();
            $submission->update( ['is_read' => true] );

            expect( $hookFired )->toBeTrue()
                ->and( $receivedSubmission )->not->toBeNull()
                ->and( $receivedSubmission->id )->toBe( $submission->id );
        } );
    } );

    describe( 'SubmissionDeleted event', function (): void {
        it( 'dispatches SubmissionDeleted event when submission is deleted', function (): void {
            Event::fake( [SubmissionDeleted::class] );

            $form         = Form::factory()->create();
            $submission   = FormSubmission::factory()->for( $form )->create();
            $submissionId = $submission->id;
            $submission->delete();

            Event::assertDispatched( SubmissionDeleted::class, function ( SubmissionDeleted $event ) use ( $submissionId ) {
                return $event->submission->id === $submissionId;
            } );
        } );

        it( 'fires ap.forms.submission.deleted action hook when submission is deleted', function (): void {
            $hookFired            = false;
            $receivedSubmissionId = null;

            addAction( 'ap.forms.submission.deleted', function ( FormSubmission $submission ) use ( &$hookFired, &$receivedSubmissionId ): void {
                $hookFired            = true;
                $receivedSubmissionId = $submission->id;
            } );

            $form         = Form::factory()->create();
            $submission   = FormSubmission::factory()->for( $form )->create();
            $submissionId = $submission->id;
            $submission->delete();

            expect( $hookFired )->toBeTrue()
                ->and( $receivedSubmissionId )->toBe( $submissionId );
        });
    });
});
