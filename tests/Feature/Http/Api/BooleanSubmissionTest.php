<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;

/**
 * Guards RX1: checkbox, toggle and consent values sent as text (`true` /
 * `false` / `on` / `off`, as HTML forms and headless clients send them) or
 * as `1` / `0` are accepted by the submission API.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'checkbox', 'name' => 'news', 'label' => 'News', 'is_required' => false] );
    FormField::factory()->for( $this->form )->create( ['type' => 'toggle', 'name' => 'subscribe', 'label' => 'Subscribe', 'is_required' => false, 'sort_order' => 2] );
    FormField::factory()->for( $this->form )->create( ['type' => 'consent', 'name' => 'agree', 'label' => 'Agree', 'is_required' => true, 'sort_order' => 3] );

    $this->submit = fn ( array $data ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => $data,
        '_form_loaded_at' => now()->subSeconds( 10 )->getTimestamp(),
    ] );
} );

it( 'accepts booleans sent as text or digits', function ( array $data, array $stored ): void {
    ( $this->submit )( $data )->assertSuccessful();

    $values = FormSubmission::query()->sole()->values()->pluck( 'value', 'field_name' )->all();

    foreach ( $stored as $name => $value ) {
        expect( $values[ $name ] ?? null )->toBe( $value );
    }

    expect( $values )->toHaveKey( 'agree' );
} )->with( [
    'digits'      => [['news' => '0', 'subscribe' => '1', 'agree' => '1'], ['news' => '0', 'subscribe' => '1']],
    'true/false'  => [['news' => 'false', 'subscribe' => 'true', 'agree' => 'true'], ['news' => '0', 'subscribe' => '1']],
    'on/off'      => [['news' => 'off', 'subscribe' => 'ON', 'agree' => 'on'], ['news' => '0', 'subscribe' => '1']],
] );

it( 'still requires a required consent box to be checked', function ( string $unchecked ): void {
    ( $this->submit )( ['agree' => $unchecked] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'data.agree' );
} )->with( ['0', 'false', 'off'] );

it( 'still rejects text that is not a boolean', function (): void {
    ( $this->submit )( ['news' => 'maybe', 'agree' => '1'] )
        ->assertUnprocessable()
        ->assertJsonValidationErrors( 'data.news' );
} );
