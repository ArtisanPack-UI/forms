<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;

/**
 * Guards FT-04: a multiple-choice field with an "Other" choice takes its
 * options plus one typed value, not any number of made-up values.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'checkbox_group',
        'name'         => 'tags',
        'label'        => 'Tags',
        'field_config' => [
            'enable_other' => true,
            'options'      => [['value' => 'a', 'label' => 'A'], ['value' => 'b, c', 'label' => 'B and C']],
        ],
    ] );

    $this->submit = fn ( array $tags ) => $this->postJson( route( 'api.forms.submit', $this->form ), ['data' => ['tags' => $tags], '_form_loaded_at' => time() - 10] );
} );

it( 'accepts the options plus one typed value', function ( array $tags ): void {
    ( $this->submit )( $tags )->assertCreated();
} )->with( [
    'options only'          => [['a', 'b, c']],
    'option and other text' => [['a', 'my text']],
    'other text only'       => [['my text']],
] );

it( 'rejects more than one value outside the options', function (): void {
    ( $this->submit )( ['a', 'x', 'y'] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( ['data.tags' => 'Please choose one of the available options for Tags.'] );
} );

it( 'rejects more than one made-up value in the Livewire renderer', function (): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.tags', ['x', 'y'] )
        ->call( 'submit' )
        ->assertHasErrors( 'formData.tags' );
} );
