<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\UrlSchemes;
use Livewire\Livewire;

/**
 * Guards issue #120: URL fields' allowed schemes and the automatic https:// prefix.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    $this->url  = FormField::factory()->for( $this->form )->create( [
        'type'  => 'url',
        'name'  => 'website',
        'label' => 'Website',
    ] );
} );

describe( 'UrlSchemes', function (): void {
    it( 'adds a scheme to addresses typed without one', function ( mixed $value, mixed $expected ): void {
        expect( UrlSchemes::withScheme( $value ) )->toBe( $expected );
    } )->with( [
        'bare domain'       => ['example.com', 'https://example.com'],
        'with path'         => ['example.com/a?b=1', 'https://example.com/a?b=1'],
        'www'               => ['  www.example.com ', 'https://www.example.com'],
        'protocol relative' => ['//example.com', 'https://example.com'],
        'host and port'     => ['localhost:8080', 'https://localhost:8080'],
        'already https'     => ['https://example.com', 'https://example.com'],
        'other scheme'      => ['ftp://example.com', 'ftp://example.com'],
        'javascript'        => ['javascript:alert(1)', 'javascript:alert(1)'],
        'blank'             => ['', ''],
        'not a string'      => [42, 42],
    ] );

    it( 'adds http when only http is allowed, and nothing when neither is', function (): void {
        expect( UrlSchemes::withScheme( 'example.com', ['http'] ) )->toBe( 'http://example.com' )
            ->and( UrlSchemes::withScheme( 'example.com', ['ftp'] ) )->toBe( 'example.com' );
    } );

    it( 'normalizes scheme lists and falls back to http and https', function (): void {
        expect( UrlSchemes::normalizeList( ['HTTPS://', 'ftp:', ' sftp ', 'bad scheme', 'https'] ) )->toBe( ['https', 'ftp', 'sftp'] )
            ->and( UrlSchemes::normalizeList( null ) )->toBe( ['http', 'https'] )
            ->and( UrlSchemes::normalizeList( ['1bad'] ) )->toBe( ['http', 'https'] );
    } );
} );

describe( 'settings and rules', function (): void {
    it( 'offers the allowed schemes setting in the validation tab', function (): void {
        $setting = FieldSettings::find( $this->url, 'allowed_schemes' );

        expect( $setting )->toMatchArray( ['input' => 'list', 'store' => 'validation_rules', 'tab' => 'validation'] );
    } );

    it( 'accepts http and https by default', function (): void {
        expect( $this->url->buildValidationRules() )->toBe( ['nullable', 'url:http,https'] );
    } );

    it( 'accepts the schemes the field allows', function (): void {
        $this->url->update( ['validation_rules' => ['allowed_schemes' => ['https']]] );

        expect( $this->url->fresh()->buildValidationRules() )->toBe( ['nullable', 'url:https'] );
    } );
} );

describe( 'submission', function (): void {
    it( 'validates and stores an address typed without a scheme in the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.website', 'example.com/about' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( 'https://example.com/about' );
    } );

    it( 'adds the scheme through the API', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['website' => 'www.example.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( 'https://www.example.com' );
    } );

    it( 'rejects schemes the field does not allow', function ( string $value ): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['website' => $value],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.website' );
    } )->with( [
        'ftp'        => ['ftp://example.com'],
        'javascript' => ['javascript:alert(1)'],
    ] );

    it( 'rejects http when only https is allowed', function (): void {
        $this->url->update( ['validation_rules' => ['allowed_schemes' => ['https']]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.website', 'http://example.com' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.website' => 'url'] );
    } );

    it( 'leaves values of other fields alone', function (): void {
        $text = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'note'] );

        expect( app( SubmissionService::class )->normalizeSubmittedValues( [$this->url, $text], ['website' => 'a.com', 'note' => 'b.com'] ) )
            ->toBe( ['website' => 'https://a.com', 'note' => 'b.com'] );
    } );
} );

describe( 'rendering', function (): void {
    it( 'adds the scheme when the input loses focus', function (): void {
        expect( FieldRenderer::render( $this->url, '' ) )->toContain( 'x-on:blur="const typed = $el.value.trim();' )
            ->toContain( '&#039;https://&#039;' );
    } );

    it( 'skips the blur handler when neither http nor https is allowed', function (): void {
        $this->url->update( ['validation_rules' => ['allowed_schemes' => ['ftp']]] );

        expect( FieldRenderer::render( $this->url->fresh(), '' ) )->not->toContain( 'x-on:blur' );
    } );
} );
