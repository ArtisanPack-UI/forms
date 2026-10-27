<?php

declare( strict_types=1 );

/**
 * Guards FT-09: the client normalizes formatted numbers, currency rounding
 * included, exactly as `NumberFormat::normalize()` does on the server.
 */

use ArtisanPackUI\Forms\Support\NumberFormat;
use Tests\Support\SharedTypeScript;

it( 'normalizes formatted numbers like NumberFormat', function (): void {
    $cases = [
        ['1.005', 'currency'],
        ['2.675', 'currency'],
        ['-2.675', 'currency'],
        ['0.285', 'currency'],
        ['1.115', 'currency'],
        ['-0.001', 'currency'],
        ['-0.005', 'currency'],
        ['9.995', 'currency'],
        ['99.999', 'currency'],
        ['.5', 'currency'],
        ['007.5', 'currency'],
        ['1,234.5', 'currency'],
        ['$1,234.565', 'currency'],
        ['12', 'currency'],
        ['12.', 'currency'],
        ['1,000', 'decimal_dot'],
        ['1.000,50', 'decimal_comma'],
        ['12.', 'decimal_dot'],
        ['abc', 'currency'],
        ['', 'currency'],
    ];

    $results = SharedTypeScript::run( <<<'JS'
        const { normalizeFormattedNumber } = await load( 'numberFormat' );
        return input.map( ( [value, format] ) => normalizeFormattedNumber( value, format ) );
        JS, $cases );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    $expected = array_map( fn ( array $case ): string => (string) NumberFormat::normalize( $case[0], $case[1] ), $cases );

    expect( $results )->toBe( $expected )
        ->and( $results[0] )->toBe( '1.01' )
        ->and( $results[1] )->toBe( '2.68' );
} );
