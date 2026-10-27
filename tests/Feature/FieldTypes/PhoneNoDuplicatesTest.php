<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;

/**
 * Guards BE-3 / FT-02: "No duplicates" on a phone field compares numbers in
 * the E.164 form they are stored in, however the visitor typed them.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'phone',
        'name'         => 'phone',
        'label'        => 'Phone',
        'field_config' => ['phone_format' => 'us', 'no_duplicates' => true],
    ] );

    $this->submitApi = fn ( string $phone ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['phone' => $phone],
        '_form_loaded_at' => time() - 10,
    ] );
} );

it( 'rejects the same number typed another way through the API', function ( string $again ): void {
    ( $this->submitApi )( '(212) 736-5000' )->assertCreated();

    ( $this->submitApi )( $again )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'data.phone' );

    expect( $this->form->submissions()->count() )->toBe( 1 )
        ->and( $this->form->submissions()->sole()->values()->value( 'value' ) )->toBe( '+12127365000' );
} )->with( ['212-736-5000', '(212) 736-5000', '+1 212 736 5000'] );

it( 'rejects the same number through the Livewire renderer', function (): void {
    ( $this->submitApi )( '(212) 736-5000' )->assertCreated();

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.phone', '212-736-5000' )
        ->call( 'submit' )
        ->assertHasErrors( 'formData.phone' )
        ->assertSet( 'formData.phone', '212-736-5000' );
} );

it( 'accepts a different number', function (): void {
    ( $this->submitApi )( '(212) 736-5000' )->assertCreated();
    ( $this->submitApi )( '(212) 736-5001' )->assertCreated();
} );
