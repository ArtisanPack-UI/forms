<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\Autocomplete;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #94: `autocomplete` tokens for WCAG 2.2 SC 1.3.5.
 */
describe( 'autocomplete tokens', function (): void {
    it( 'defaults by type', function ( string $type, ?string $expected ): void {
        $field = FormField::factory()->make( ['type' => $type, 'field_config' => []] );

        expect( $field->getAutocomplete() )->toBe( $expected );
    } )->with( [
        'email'    => ['email', 'email'],
        'phone'    => ['phone', 'tel'],
        'url'      => ['url', 'url'],
        'text'     => ['text', null],
        'textarea' => ['textarea', null],
        'select'   => ['select', null],
    ] );

    it( 'uses the configured token over the default', function (): void {
        $field = FormField::factory()->make( ['type' => 'email', 'field_config' => ['autocomplete' => 'off']] );
        $name  = FormField::factory()->make( ['type' => 'text', 'field_config' => ['autocomplete' => 'given-name']] );

        expect( $field->getAutocomplete() )->toBe( 'off' )
            ->and( $name->getAutocomplete() )->toBe( 'given-name' );
    } );

    it( 'ignores a token outside the WHATWG list', function (): void {
        $field = FormField::factory()->make( ['type' => 'email', 'field_config' => ['autocomplete' => 'javascript:alert(1)']] );

        expect( $field->getAutocomplete() )->toBe( 'email' );
    } );

    it( 'exposes nothing for types that do not offer the setting', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type, 'field_config' => ['autocomplete' => 'email']] );

        expect( $field->getAutocomplete() )->toBeNull()
            ->and( FieldSettings::find( $field, 'autocomplete' ) )->toBeNull();
    } )->with( ['checkbox', 'toggle', 'radio', 'file', 'rating', 'hidden', 'heading'] );

    it( 'offers the full token list plus a type default in the Advanced tab', function (): void {
        $field   = FormField::factory()->make( ['type' => 'email'] );
        $setting = FieldSettings::find( $field, 'autocomplete' );

        expect( $setting )->toMatchArray( ['input' => 'select', 'tab' => 'advanced', 'store' => 'field_config'] )
            ->and( $setting['options'][0] )->toBe( ['value' => '', 'label' => 'Default (email)'] )
            ->and( array_slice( array_column( $setting['options'], 'value' ), 1 ) )->toBe( Autocomplete::TOKENS )
            ->and( Autocomplete::TOKENS )->toContain( 'off', 'name', 'given-name', 'email', 'tel', 'street-address', 'postal-code', 'bday', 'one-time-code' );
    } );

    it( 'stores a chosen token and clears it with the default option', function (): void {
        $field   = FormField::factory()->create( ['type' => 'text'] );
        $setting = FieldSettings::find( $field, 'autocomplete' );

        expect( FieldSettings::update( $field, $setting, 'family-name' )['field_config'] )->toMatchArray( ['autocomplete' => 'family-name'] )
            ->and( FieldSettings::update( $field, $setting, '' )['field_config'] )->not->toHaveKey( 'autocomplete' )
            ->and( FieldSettings::update( $field, $setting, 'nonsense' )['field_config'] )->not->toHaveKey( 'autocomplete' );
    } );

    it( 'is part of the API field resource', function (): void {
        $field = FormField::factory()->create( ['type' => 'phone'] );

        expect( ( new FormFieldResource( $field ) )->toArray( Request::create( '/' ) )['autocomplete'] )->toBe( 'tel' );
    } );

    it( 'is rendered by the Livewire renderer', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'email', 'name' => 'email'] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'first', 'field_config' => ['autocomplete' => 'given-name']] );
        FormField::factory()->for( $form )->create( ['type' => 'date', 'name' => 'born', 'field_config' => ['autocomplete' => 'bday']] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'autocomplete="email"' )
            ->assertSeeHtml( 'autocomplete="given-name"' )
            ->assertSeeHtml( 'autocomplete="bday"' );
    } );
} );
