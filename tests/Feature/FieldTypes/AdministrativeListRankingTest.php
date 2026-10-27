<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards LW-07: administrative list and ranking fields on the submission
 * detail use the same row and move actions as the public form.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Name'] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'list',
        'name'         => 'attendees',
        'label'        => 'Attendees',
        'sort_order'   => 2,
        'field_config' => ['visibility' => 'administrative', 'columns' => "Name\nEmail | email", 'row_label' => 'Attendee', 'max_rows' => 3],
    ] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'ranking',
        'name'         => 'prefs',
        'label'        => 'Preferences',
        'sort_order'   => 3,
        'field_config' => [
            'visibility' => 'administrative',
            'options'    => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C']],
        ],
    ] );

    $this->submission = app( SubmissionService::class )->create( $this->form, ['name' => 'Ada'] );

    $this->actingAs( User::factory()->create( ['is_admin' => true] ) );
} );

it( 'adds and removes list rows on the submission detail', function (): void {
    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->call( 'addListRow', 'attendees' )
        ->assertHasNoErrors()
        ->assertSet( 'formData.attendees', fn ( $rows ): bool => is_array( $rows ) && 2 === count( $rows ) )
        ->assertDispatched( 'forms-list-row-added', field: 'attendees', index: 1 )
        ->call( 'removeListRow', 'attendees', 0 )
        ->assertSet( 'formData.attendees', fn ( $rows ): bool => is_array( $rows ) && 1 === count( $rows ) )
        ->assertDispatched( 'forms-list-row-removed', field: 'attendees', index: 0 );
} );

it( 'moves ranking choices on the submission detail', function (): void {
    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->call( 'moveRankingItem', 'prefs', 0, 1 )
        ->assertSet( 'formData.prefs', ['b', 'a', 'c'] )
        ->assertDispatched( 'forms-ranking-moved', field: 'prefs', value: 'a', position: 1 );
} );

it( 'saves the rows and order added on the submission detail', function (): void {
    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->call( 'addListRow', 'attendees' )
        ->set( 'formData.attendees.0.name', 'Grace' )
        ->set( 'formData.attendees.0.email', 'grace@example.com' )
        ->call( 'moveRankingItem', 'prefs', 2, 0 )
        ->call( 'saveAdministrativeFields' )
        ->assertHasNoErrors()
        ->assertSet( 'administrativeFieldsSaved', true );

    expect( $this->submission->fresh()->values->firstWhere( 'field_name', 'prefs' )?->value_array )->toBe( ['c', 'a', 'b'] );
} );

it( 'ignores fields that are not administrative lists or rankings', function (): void {
    Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
        ->call( 'addListRow', 'name' )
        ->call( 'moveRankingItem', 'attendees', 0, 1 )
        ->assertNotDispatched( 'forms-list-row-added' )
        ->assertNotDispatched( 'forms-ranking-moved' );
} );

it( 'does not let the public form change administrative fields', function (): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->call( 'addListRow', 'attendees' )
        ->call( 'moveRankingItem', 'prefs', 0, 1 )
        ->assertNotDispatched( 'forms-list-row-added' )
        ->assertNotDispatched( 'forms-ranking-moved' );
} );
