<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\RegexPattern;
use Illuminate\Support\Facades\Log;

/**
 * Guards FT-01: the "Pattern (Regex)" setting is read the same way with or
 * without PHP delimiters, and a pattern that does not compile never fails a
 * submission.
 */
it( 'reads delimited and undelimited patterns', function ( mixed $raw, ?string $expected ): void {
    expect( RegexPattern::normalize( $raw ) )->toBe( $expected );
} )->with( [
    'delimited'               => ['/^\d{6}$/', '/^\d{6}$/'],
    'delimited with flags'    => ['/^[a-z]+$/i', '/^[a-z]+$/i'],
    'other delimiter'         => ['#^a/b$#', '#^a/b$#'],
    'undelimited'             => ['^[a-z]+$', '/^[a-z]+$/u'],
    'undelimited with slash'  => ['^a/b$', '/^a\/b$/u'],
    'undelimited escaped /'   => ['^a\/b$', '/^a\/b$/u'],
    'starts with a letter'    => ['abc', '/abc/u'],
    'starts with a backslash' => ['\d+', '/\d+/u'],
    'invalid'                 => ['[a-z', null],
    'blank'                   => ['  ', null],
    'not a string'            => [['x'], null],
] );

describe( 'submissions', function (): void {
    beforeEach( function (): void {
        $this->form   = Form::factory()->create( ['is_active' => true] );
        $this->submit = fn ( string $code ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['code' => $code],
            '_form_loaded_at' => now()->subSeconds( 10 )->getTimestamp(),
        ] );
    } );

    it( 'validates an undelimited pattern without an exception', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'code', 'validation_rules' => ['pattern' => '^[a-z]+$']] );

        ( $this->submit )( 'abc' )->assertSuccessful();
        ( $this->submit )( 'ABC' )->assertUnprocessable()->assertJsonValidationErrors( 'data.code' );
    } );

    it( 'validates a delimited pattern', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'code', 'validation_rules' => ['pattern' => '/^\d{6}$/']] );

        ( $this->submit )( '123456' )->assertSuccessful();
        ( $this->submit )( '12a' )->assertUnprocessable()->assertJsonValidationErrors( 'data.code' );
    } );

    it( 'ignores a pattern that does not compile, and logs it', function (): void {
        Log::spy();

        $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'code', 'validation_rules' => ['pattern' => '[a-z']] );

        expect( $field->fieldType()->rules( $field )[''] )->not->toContain( 'regex:[a-z' );

        ( $this->submit )( 'anything' )->assertSuccessful();

        Log::shouldHaveReceived( 'warning' )->withArgs( fn ( string $message ): bool => str_contains( $message, 'pattern' ) )->atLeast()->once();
    } );
} );
