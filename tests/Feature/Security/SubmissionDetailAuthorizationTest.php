<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards SEC-07: every SubmissionDetail action checks its own ability, not
 * only the `view` check made when the page is first rendered.
 */
beforeEach( function (): void {
    $this->submission = FormSubmission::factory()->for( Form::factory()->create() )->create( [
        'is_starred'  => false,
        'is_spam'     => false,
        'is_read'     => true,
        'admin_notes' => 'Original',
    ] );

    $this->actingAs( User::factory()->create() );
} );

it( 'requires the view ability to mount', function (): void {
    Gate::before( fn ( $user, string $ability ): ?bool => 'view' === $ability ? false : null );

    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->assertForbidden();
} );

it( 'forbids guests', function (): void {
    auth()->logout();

    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->assertForbidden();
} );

describe( 'for a user who may only view the submission', function (): void {
    beforeEach( function (): void {
        Gate::before( fn ( $user, string $ability ): ?bool => in_array( $ability, ['update', 'delete', 'markAsSpam'], true ) ? false : null );

        $this->component = Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] );
    } );

    it( 'does not delete the submission', function (): void {
        $this->component->call( 'delete' )->assertForbidden();

        expect( FormSubmission::find( $this->submission->id ) )->not->toBeNull();
    } );

    it( 'does not change the submission with :dataset', function ( string $method, string $column, mixed $expected ): void {
        $this->component->call( $method )->assertForbidden();

        expect( $this->submission->fresh()->{$column} )->toBe( $expected );
    } )->with( [
        'toggleStar'   => ['toggleStar', 'is_starred', false],
        'toggleSpam'   => ['toggleSpam', 'is_spam', false],
        'markAsUnread' => ['markAsUnread', 'is_read', true],
    ] );

    it( 'does not save admin notes', function (): void {
        $this->component->set( 'adminNotes', 'Rewritten' )->assertForbidden();

        expect( $this->submission->fresh()->admin_notes )->toBe( 'Original' );
    } );
} );

it( 'lets a user with the abilities run the actions', function (): void {
    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->call( 'toggleStar' )
        ->call( 'toggleSpam' )
        ->set( 'adminNotes', 'Updated' )
        ->assertOk();

    expect( $this->submission->fresh() )
        ->is_starred->toBeTrue()
        ->is_spam->toBeTrue()
        ->admin_notes->toBe( 'Updated' );
} );
