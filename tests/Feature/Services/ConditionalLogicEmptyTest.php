<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use Livewire\Livewire;

/**
 * Guards FT-06: "is empty" means an unchecked box for checkboxes, toggles and
 * consent boxes, however the value is sent, while a number 0 is not empty,
 * the same as in the browser.
 */
it( 'compares empty values like the browser', function ( mixed $value, bool $empty ): void {
    $service = app( ConditionalLogicService::class );

    expect( $service->compareValues( $value, 'is_empty', '' ) )->toBe( $empty )
        ->and( $service->compareValues( $value, 'is_not_empty', '' ) )->toBe( ! $empty );
} )->with( [
    'false'        => [false, true],
    'true'         => [true, false],
    'zero'         => [0, false],
    'float zero'   => [0.0, false],
    'zero string'  => ['0', false],
    'blank string' => ['  ', true],
    'null'         => [null, true],
    'empty array'  => [[], true],
] );

describe( 'a rule on a checkbox', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $this->form )->create( ['type' => 'toggle', 'name' => 'opt_in', 'label' => 'Opt in', 'sort_order' => 1] );
        FormField::factory()->for( $this->form )->create( [
            'type'              => 'text',
            'name'              => 'reason',
            'label'             => 'Reason',
            'is_required'       => true,
            'sort_order'        => 2,
            'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'opt_in', 'operator' => 'is_empty', 'value' => '']]],
        ] );
    } );

    it( 'reads an unchecked box as empty however it is sent', function ( mixed $unchecked ): void {
        $hidden = app( ConditionalLogicService::class )->getHiddenFields( $this->form->fields()->get(), ['opt_in' => $unchecked] );

        expect( $hidden['reason'] )->toBeFalse();
    } )->with( [false, '0', 'false', 'off', null, ''] );

    it( 'reads a checked box as not empty however it is sent', function ( mixed $checked ): void {
        $hidden = app( ConditionalLogicService::class )->getHiddenFields( $this->form->fields()->get(), ['opt_in' => $checked] );

        expect( $hidden['reason'] )->toBeTrue();
    } )->with( [true, '1', 'true', 'on'] );

    it( 'requires the field an unchecked toggle shows, through the API', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), ['data' => ['opt_in' => false], '_form_loaded_at' => time() - 10] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'data.reason' );

        $this->postJson( route( 'api.forms.submit', $this->form ), ['data' => ['opt_in' => true], '_form_loaded_at' => time() - 10] )
            ->assertCreated();
    } );

    it( 'shows the field for an unchecked toggle in the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.opt_in', true )
            ->assertSet( 'hiddenFields.reason', true )
            ->set( 'formData.opt_in', false )
            ->assertSet( 'hiddenFields.reason', false );
    } );
} );
