<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;

/**
 * Guards FT-03: "Min Selections" applies only once something is selected, so
 * an optional checkbox group or multi-select can be left empty.
 */
beforeEach( function (): void {
    $this->form   = Form::factory()->create( ['is_active' => true] );
    $this->submit = fn ( array $data ) => $this->postJson( route( 'api.forms.submit', $this->form ), ['data' => $data, '_form_loaded_at' => time() - 10] );
} );

function selectionField( Form $form, string $type, bool $required ): FormField
{
    return FormField::factory()->for( $form )->create( [
        'type'             => $type,
        'name'             => 'tags',
        'label'            => 'Tags',
        'is_required'      => $required,
        'validation_rules' => ['min' => 2],
        'field_config'     => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C']]],
    ] );
}

it( 'lets an optional field be left empty but checks the minimum once answered', function ( string $type ): void {
    selectionField( $this->form, $type, false );

    ( $this->submit )( ['tags' => []] )->assertCreated();
    ( $this->submit )( ['tags' => ['a', 'b']] )->assertCreated();
    ( $this->submit )( ['tags' => ['a']] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( ['data.tags' => 'Please choose at least 2 options for Tags.'] );
} )->with( ['checkbox_group', 'select_multiple'] );

it( 'still requires a required field', function (): void {
    selectionField( $this->form, 'checkbox_group', true );

    ( $this->submit )( ['tags' => []] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( ['data.tags' => 'required'] );
} );

it( 'lets an optional field be left empty in the Livewire renderer', function (): void {
    selectionField( $this->form, 'checkbox_group', false );

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.tags', [] )
        ->call( 'submit' )
        ->assertHasNoErrors();

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.tags', ['a'] )
        ->call( 'submit' )
        ->assertHasErrors( 'formData.tags' );
} );
