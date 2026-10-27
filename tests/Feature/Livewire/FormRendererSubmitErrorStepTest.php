<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Livewire\Livewire;

/**
 * Guards BE-7: a field on an earlier step that fails when the form is
 * submitted moves the form to that step, so the visitor sees the error.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true, 'is_multi_step' => true] );
    $first      = FormStep::factory()->for( $this->form )->create( ['sort_order' => 0] );
    $second     = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );

    FormField::factory()->for( $this->form )->create( [
        'type'         => 'email',
        'name'         => 'email',
        'label'        => 'Email',
        'step_id'      => $first->id,
        'sort_order'   => 1,
        'field_config' => ['no_duplicates' => true],
    ] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'comment', 'step_id' => $second->id, 'sort_order' => 2] );
} );

it( 'moves to the step of a field that fails on submit', function (): void {
    $component = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->afterSpamDelay()
        ->set( 'formData.email', 'ada@example.com' )
        ->call( 'nextStep' )
        ->assertSet( 'currentStepIndex', 1 );

    app( SubmissionService::class )->create( $this->form->fresh(), ['email' => 'ada@example.com'] );

    $component->call( 'submit' )
        ->assertHasErrors( 'formData.email' )
        ->assertSet( 'currentStepIndex', 0 )
        ->assertSet( 'isSubmitted', false );
} );

it( 'stays on the last step when its own field fails', function (): void {
    FormField::query()->where( 'name', 'comment' )->update( ['is_required' => true] );

    Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->afterSpamDelay()
        ->set( 'formData.email', 'ada@example.com' )
        ->call( 'nextStep' )
        ->call( 'submit' )
        ->assertHasErrors( 'formData.comment' )
        ->assertSet( 'currentStepIndex', 1 );
} );
