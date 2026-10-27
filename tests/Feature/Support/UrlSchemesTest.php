<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\UrlSchemes;
use Illuminate\Support\Facades\Validator;

/**
 * Guards SEC-06: a URL field can never allow a scheme that runs script or
 * reads local data when the link is opened.
 */
it( 'drops blocked schemes from the allowed list', function ( mixed $schemes, array $expected ): void {
    expect( UrlSchemes::normalizeList( $schemes ) )->toBe( $expected );
} )->with( [
    'javascript and https' => ['javascript, https', ['https']],
    'javascript alone'     => ['javascript', UrlSchemes::DEFAULT],
    'every blocked scheme' => [['JavaScript:', 'data', 'vbscript://', 'file', 'blob'], UrlSchemes::DEFAULT],
    'safe schemes kept'    => ['https, ftp, mailto', ['https', 'ftp', 'mailto']],
] );

it( 'rejects a javascript URL even when the field lists the scheme', function (): void {
    $form  = Form::factory()->create();
    $field = FormField::factory()->for( $form )->create( [
        'type'             => 'url',
        'name'             => 'website',
        'validation_rules' => ['allowed_schemes' => 'javascript, https'],
    ] );

    $rules = $field->fieldType()->rules( $field )[''] ?? [];

    expect( Validator::make( ['website' => 'javascript://example.com/%0aalert(1)'], ['website' => $rules] )->fails() )->toBeTrue()
        ->and( Validator::make( ['website' => 'https://example.com'], ['website' => $rules] )->passes() )->toBeTrue();
} );
