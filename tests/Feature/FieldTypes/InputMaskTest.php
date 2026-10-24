<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\InputMask;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #115: input masks on text and phone fields.
 */
beforeEach( function (): void {
    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'text',
        'name'         => 'ssn',
        'label'        => 'SSN',
        'field_config' => ['input_mask' => 'ssn'],
    ] );
} );

describe( 'InputMask', function (): void {
    it( 'resolves presets and custom patterns', function (): void {
        expect( InputMask::resolve( 'us_phone', null ) )->toBe( '(999) 999-9999' )
            ->and( InputMask::resolve( 'zip_plus4', null ) )->toBe( '99999-9999' )
            ->and( InputMask::resolve( 'custom', '999-aa-***' ) )->toBe( '999-aa-***' )
            ->and( InputMask::resolve( '', 'ignored' ) )->toBeNull()
            ->and( InputMask::resolve( 'unknown', null ) )->toBeNull();
    } );

    it( 'rejects a custom pattern without tokens or that is too long', function (): void {
        expect( InputMask::resolve( 'custom', '---' ) )->toBeNull()
            ->and( InputMask::resolve( 'custom', '' ) )->toBeNull()
            ->and( InputMask::resolve( 'custom', str_repeat( '9', InputMask::MAX_LENGTH + 1 ) ) )->toBeNull();
    } );

    it( 'builds a regex that matches only a complete masked value', function ( string $mask, string $value, bool $matches ): void {
        expect( 1 === preg_match( InputMask::toRegex( $mask ), $value ) )->toBe( $matches );
    } )->with( [
        'phone'          => ['(999) 999-9999', '(555) 123-4567', true],
        'phone partial'  => ['(999) 999-9999', '(555) 123-45', false],
        'phone unmasked' => ['(999) 999-9999', '5551234567', false],
        'custom'         => ['999-aa-***', '123-ab-x9Z', true],
        'custom letter'  => ['999-aa-***', '123-a1-x9Z', false],
        'regex chars'    => ['9.9+9', '1.2+3', true],
        'regex literal'  => ['9.9+9', '1x2+3', false],
    ] );

    it( 'describes presets by example and custom masks by pattern', function (): void {
        expect( InputMask::hint( '(999) 999-9999' ) )->toBe( 'Format: (555) 555-0123' )
            ->and( InputMask::hint( '999-aa-***' ) )->toBe( 'Format: ###-AA-XXX (# is a digit, A is a letter, X is a letter or digit)' )
            ->and( InputMask::hint( 'a99' ) )->toBe( 'Format: A## (# is a digit, A is a letter)' );
    } );
} );

describe( 'settings', function (): void {
    it( 'offers the mask settings on text and phone fields only', function ( string $type, bool $offered ): void {
        $field = FormField::factory()->make( ['type' => $type, 'name' => 'f'] );
        $keys  = array_column( FieldSettings::schema( $field ), 'key' );

        expect( in_array( 'input_mask', $keys, true ) )->toBe( $offered )
            ->and( in_array( 'custom_mask', $keys, true ) )->toBe( $offered );
    } )->with( [
        ['text', true],
        ['phone', true],
        ['email', false],
        ['textarea', false],
    ] );

    it( 'ignores a mask on a type that does not offer it', function (): void {
        $field = FormField::factory()->make( ['type' => 'email', 'name' => 'e', 'field_config' => ['input_mask' => 'zip']] );

        expect( $field->getInputMask() )->toBeNull()
            ->and( $field->buildValidationRules() )->not->toContain( 'regex:' . InputMask::toRegex( '99999' ) );
    } );
} );

describe( 'validation', function (): void {
    it( 'adds a regex rule and a message naming the format', function (): void {
        expect( $this->field->buildValidationRules() )->toContain( 'regex:' . InputMask::toRegex( '999-99-9999' ) )
            ->and( $this->field->buildValidationMessages()['regex'] )->toBe( 'The SSN must match the expected format. Format: 123-45-6789' );
    } );

    it( 'rejects an incomplete value and stores a complete one with its literals', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.ssn', '123-45-67' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.ssn' => 'regex'] )
            ->set( 'formData.ssn', '123-45-6789' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '123-45-6789' );
    } );

    it( 'checks the mask through the API', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['ssn' => '123456789'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.ssn' );
    } );

    it( 'leaves an empty optional field alone', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasNoErrors();
    } );
} );

describe( 'rendering', function (): void {
    it( 'binds the Alpine mask and shows the format, referenced by aria-describedby', function (): void {
        $html = FieldRenderer::render( $this->field, '' );

        expect( $html )->toContain( 'x-mask="999-99-9999"' )
            ->toContain( 'id="mask-ssn"' )
            ->toContain( 'Format: 123-45-6789' )
            ->toMatch( '/aria-describedby="[^"]*\bmask-ssn\b/' );
    } );

    it( 'masks a phone field', function (): void {
        $phone = FormField::factory()->for( $this->form )->create( ['type' => 'phone', 'name' => 'tel', 'field_config' => ['input_mask' => 'us_phone']] );

        expect( FieldRenderer::render( $phone, '' ) )->toContain( 'x-mask="(999) 999-9999"' )->toContain( 'id="mask-tel"' );
    } );

    it( 'renders no mask when none is set', function (): void {
        $this->field->update( ['field_config' => []] );

        expect( FieldRenderer::render( $this->field->fresh(), '' ) )->not->toContain( 'x-mask' )->not->toContain( 'mask-ssn' );
    } );

    it( 'sends the mask and its hint to the React and Vue renderers', function (): void {
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['input_mask'] )->toBe( ['mask' => '999-99-9999', 'hint' => 'Format: 123-45-6789'] );
    } );
} );
