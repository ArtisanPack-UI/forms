<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The regression this locks in: FormSubmitted is dispatched from the
 * FormSubmission model's `created` hook, which runs before
 * SubmissionService::create() writes the submission's field values in the same
 * transaction. Because the event is ShouldDispatchAfterCommit, its delivery is
 * held until that transaction commits — so a listener runs once, after the
 * commit, and reads the fully persisted submission (values included) rather than
 * an empty one. A listener seeing zero values is exactly the failure that
 * silently stopped form-driven bookings.
 *
 * This runs without RefreshDatabase (Unit, not Feature) on purpose: an
 * after-commit event never fires inside RefreshDatabase's wrapping transaction,
 * which never commits, so the boundary can only be observed against real commits.
 */
it( 'delivers FormSubmitted only after commit, with the submission values persisted', function (): void {
    $form = Form::factory()->create();
    FormField::factory()->for( $form )->create( ['name' => 'email', 'type' => 'email'] );

    $valuesWhenListenerRan = null;
    $ranBeforeCommit       = null;

    Event::listen( FormSubmitted::class, function ( FormSubmitted $event ) use ( &$valuesWhenListenerRan ): void {
        // Queried fresh from the database at the moment the listener runs.
        $valuesWhenListenerRan = $event->submission->values()->count();
    } );

    DB::transaction( function () use ( $form, &$ranBeforeCommit, &$valuesWhenListenerRan ): void {
        app( SubmissionService::class )->create( $form, ['email' => 'lee@example.test'] );

        // Still inside the open transaction: the after-commit listener must not
        // have fired, so it cannot have seen the (empty-at-this-instant) values.
        $ranBeforeCommit = null !== $valuesWhenListenerRan;
    } );

    expect( $ranBeforeCommit )->toBeFalse()
        ->and( $valuesWhenListenerRan )->toBe( 1 );
} );
