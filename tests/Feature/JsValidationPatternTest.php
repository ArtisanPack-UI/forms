<?php

declare( strict_types=1 );

/**
 * Guards FT-01: the React/Vue client reads a field's Pattern setting the way
 * the server does, delimited (`/^\d{6}$/`) or not (`^[a-z]+$`), and leaves
 * patterns JS cannot run to the server.
 */

use Tests\Support\SharedTypeScript;

it( 'validates delimited and undelimited patterns like the server', function (): void {
    $cases = [
        ['/^\d{6}$/', '123456'],
        ['/^\d{6}$/', '12a'],
        ['^[a-z]+$', 'abc'],
        ['^[a-z]+$', 'ABC'],
        ['/^[a-z]+$/i', 'ABC'],
        ['#^a/b$#', 'a/b'],
        ['{^\d{2}$}', '12'],
        ['{^\d{2}$}', '123'],
        ['/^a$/x', 'b'],
        ['[a-z', 'b'],
        ['/a(/', 'b'],
        ['/^\d+$/ui', 'x'],
    ];

    $results = SharedTypeScript::run( <<<'JS'
        const { validateField } = await load( 'validation' );
        return input.map( ( [pattern, value] ) => validateField( { type: 'text', name: 'code', validation_rules: { pattern } }, value ).length === 0 );
        JS, $cases );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $results )->toBe( [true, false, true, false, true, true, true, false, true, true, true, false] );
} );

it( 'exposes the RegExp the client checks with', function (): void {
    $results = SharedTypeScript::run( <<<'JS'
        const { toClientPattern } = await load( 'validation' );
        return ['/^\\d{6}$/', '/^[a-z]+$/im', '^[a-z]+$', '/x/U', '', null].map( ( raw ) => {
            const regex = toClientPattern( raw );

            return regex ? [regex.source, regex.flags] : null;
        } );
        JS );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $results )->toBe( [['^\d{6}$', ''], ['^[a-z]+$', 'im'], ['^[a-z]+$', ''], null, null, null] );
} );
