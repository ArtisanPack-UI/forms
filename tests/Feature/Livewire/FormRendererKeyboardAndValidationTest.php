<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Livewire\Livewire;

/**
 * Guards LW-01 (the server validates; the browser's constraint validation
 * would block hidden required fields) and LW-02 (the multi-step key handler
 * is scoped to the form and only advances on Enter in a text-like input).
 */
it( 'renders the form with novalidate (LW-01)', function (): void {
    $form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $form )->required()->create( ['type' => 'text', 'name' => 'name'] );

    Livewire::test( FormRenderer::class, ['form' => $form] )
        ->assertSeeHtml( '<form wire:submit="submit" class="space-y-6" x-ref="form" novalidate>' );
} );

it( 'submits a required field hidden by conditional logic without it (LW-01)', function (): void {
    $form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'attending', 'sort_order' => 1] );
    FormField::factory()->for( $form )->required()->create( [
        'type'              => 'text',
        'name'              => 'guest',
        'sort_order'        => 2,
        'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'attending', 'operator' => 'equals', 'value' => 'yes']]],
    ] );

    Livewire::test( FormRenderer::class, ['form' => $form] )
        ->afterSpamDelay()
        ->set( 'formData.attending', 'no' )
        ->call( 'submit' )
        ->assertHasNoErrors()
        ->assertSet( 'isSubmitted', true );
} );

it( 'scopes the multi-step key handler to the form (LW-02)', function (): void {
    $form  = Form::factory()->create( ['is_active' => true, 'is_multi_step' => true] );
    $first = FormStep::factory()->for( $form )->create( ['sort_order' => 0] );
    FormStep::factory()->for( $form )->create( ['sort_order' => 1] );
    FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'name', 'step_id' => $first->id] );

    $html = Livewire::test( FormRenderer::class, ['form' => $form->fresh()] )->html();
    $view = file_get_contents( __DIR__ . '/../../../resources/views/livewire/form-renderer.blade.php' );

    expect( $html )->not->toContain( 'keydown.window' )
        ->and( $html )->toContain( 'x-on:keydown="handleKeydown($event)"' )
        ->and( $view )->toContain( 'this.$root.contains(target)' )
        ->and( $view )->toContain( 'advancesOnEnter(target)' )
        ->and( $view )->toContain( "target.closest('button, a, [role=\"button\"], [role=\"combobox\"], [role=\"listbox\"], [contenteditable=\"true\"], [contenteditable=\"\"]')" )
        ->and( $view )->toContain( "if (target.tagName !== 'INPUT' || target.isContentEditable) return false;" );
} );
