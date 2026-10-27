<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;

/**
 * Guards FT-05: a number field's "Step Increment" is checked on the server,
 * counted from "Min Value" (or 0), with the browser's float tolerance.
 */
beforeEach( function (): void {
    $this->form   = Form::factory()->create( ['is_active' => true] );
    $this->submit = fn ( string $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), ['data' => ['qty' => $value], '_form_loaded_at' => time() - 10] );
} );

function stepField( Form $form, array $rules ): FormField
{
    return FormField::factory()->for( $form )->create( ['type' => 'number', 'name' => 'qty', 'label' => 'Quantity', 'validation_rules' => $rules] );
}

it( 'rejects a value off the step', function (): void {
    stepField( $this->form, ['step' => 0.25, 'min' => 0] );

    ( $this->submit )( '1.25' )->assertCreated();
    ( $this->submit )( '1.1' )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( ['data.qty' => 'The Quantity must be in steps of 0.25.'] );
} );

it( 'allows for float error', function (): void {
    stepField( $this->form, ['step' => 0.1] );

    ( $this->submit )( '0.3' )->assertCreated();
} );

it( 'counts steps from the minimum', function (): void {
    stepField( $this->form, ['step' => 2, 'min' => 1] );

    ( $this->submit )( '5' )->assertCreated();
    ( $this->submit )( '4' )->assertUnprocessable()->assertJsonValidationErrors( 'data.qty' );
} );

it( 'checks the step in the Livewire renderer', function (): void {
    stepField( $this->form, ['step' => 0.25] );

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.qty', '1.1' )
        ->call( 'submit' )
        ->assertHasErrors( 'formData.qty' );
} );

it( 'ignores a step that is not positive', function (): void {
    stepField( $this->form, ['step' => 0] );

    ( $this->submit )( '1.1' )->assertCreated();
} );
