<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Livewire\Livewire;

/**
 * Guards BE-1: a calculation reads a `decimal_comma` number once, so the
 * stored result matches what the visitor saw, on both submit paths.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'number', 'name' => 'quantity', 'label' => 'Quantity', 'sort_order' => 1] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'number',
        'name'         => 'price',
        'label'        => 'Price',
        'sort_order'   => 2,
        'field_config' => ['number_format' => 'decimal_comma'],
    ] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'calculation',
        'name'         => 'total',
        'label'        => 'Total',
        'sort_order'   => 3,
        'field_config' => ['formula' => '{quantity} * {price}', 'decimals' => 2],
    ] );

    $this->storedTotal = fn (): ?string => $this->form->submissions()->sole()->values()->where( 'field_name', 'total' )->value( 'value' );
} );

it( 'stores the right result through the API', function (): void {
    $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['quantity' => '2', 'price' => '1.234,50'],
        '_form_loaded_at' => time() - 10,
    ] )->assertCreated();

    expect( ( $this->storedTotal )() )->toBe( '2469.00' );
} );

it( 'stores the right result through the Livewire renderer', function (): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.price', '1.234,50' )
        ->set( 'formData.quantity', '2' )
        ->call( 'submit' )
        ->assertHasNoErrors();

    expect( ( $this->storedTotal )() )->toBe( '2469.00' );
} );

it( 'reads already normalized values as they are', function (): void {
    $fields = $this->form->fields()->get();

    expect( app( SubmissionService::class )->applyCalculations( $fields, ['price' => '0.5', 'quantity' => '2', 'total' => ''], true, true )['total'] )->toBe( '1.00' )
        ->and( app( SubmissionService::class )->applyCalculations( $fields, ['price' => '1,5', 'quantity' => '2', 'total' => ''] )['total'] )->toBe( '3.00' );
} );
