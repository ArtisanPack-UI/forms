<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use Livewire\Livewire;

/**
 * Guards BE-2: conditional logic reads values the way they are validated and
 * stored (formatted numbers, URLs, colors), on every submit path.
 */
function showWhen( string $field, string $operator, string $value ): array
{
    return ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => $field, 'operator' => $operator, 'value' => $value]]];
}

beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'number', 'name' => 'amount', 'label' => 'Amount', 'sort_order' => 1, 'field_config' => ['number_format' => 'decimal_dot']] );
    FormField::factory()->for( $this->form )->create( [
        'type'              => 'textarea',
        'name'              => 'details',
        'label'             => 'Details',
        'is_required'       => true,
        'sort_order'        => 2,
        'conditional_logic' => showWhen( 'amount', 'greater_than', '500' ),
    ] );

    $this->hidden = fn ( array $data, bool $normalized = false ): array => app( ConditionalLogicService::class )
        ->getHiddenFields( $this->form->fields()->get(), $data, $normalized );
} );

it( 'reads a number typed in its field\'s format', function (): void {
    expect( ( $this->hidden )( ['amount' => '1,000'] )['details'] )->toBeFalse()
        ->and( ( $this->hidden )( ['amount' => '400'] )['details'] )->toBeTrue();
} );

it( 'does not normalize already normalized values again', function (): void {
    FormField::query()->where( 'name', 'amount' )->update( ['field_config' => ['number_format' => 'decimal_comma']] );

    expect( ( $this->hidden )( ['amount' => '1.000,50'] )['details'] )->toBeFalse()
        ->and( ( $this->hidden )( ['amount' => '0.75'], true )['details'] )->toBeTrue()
        ->and( ( $this->hidden )( ['amount' => '1000.50'], true )['details'] )->toBeFalse();
} );

it( 'reads a URL without a scheme and a color in any case', function (): void {
    FormField::factory()->for( $this->form )->create( ['type' => 'url', 'name' => 'site', 'sort_order' => 3] );
    FormField::factory()->for( $this->form )->create( ['type' => 'color', 'name' => 'shade', 'sort_order' => 4] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'secure', 'sort_order' => 5, 'conditional_logic' => showWhen( 'site', 'starts_with', 'https://' )] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'orange', 'sort_order' => 6, 'conditional_logic' => showWhen( 'shade', 'equals', '#ff8800' )] );

    $hidden = ( $this->hidden )( ['site' => 'example.com', 'shade' => 'FF8800'] );

    expect( $hidden['secure'] )->toBeFalse()
        ->and( $hidden['orange'] )->toBeFalse();
} );

it( 'requires a field a formatted number shows, through the API', function (): void {
    $submit = fn ( array $data ) => $this->postJson( route( 'api.forms.submit', $this->form ), ['data' => $data, '_form_loaded_at' => time() - 10] );

    $submit( ['amount' => '1,000'] )->assertUnprocessable()->assertJsonValidationErrors( 'data.details' );
    $submit( ['amount' => '1,000', 'details' => 'Big order'] )->assertCreated();

    expect( $this->form->submissions()->sole()->values()->where( 'field_name', 'details' )->value( 'value' ) )->toBe( 'Big order' );
} );

it( 'shows the field a formatted number shows, in the Livewire renderer', function (): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->set( 'formData.amount', '1,000' )
        ->assertSet( 'hiddenFields.details', false )
        ->set( 'formData.amount', '400' )
        ->assertSet( 'hiddenFields.details', true );
} );
