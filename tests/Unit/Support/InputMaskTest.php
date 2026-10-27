<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Support\InputMask;

/**
 * Guards FT-10: a mask's digit accepts ASCII digits only, as in the browser.
 */
it( 'accepts only ASCII digits for 9', function ( string $value, bool $matches ): void {
    expect( 1 === preg_match( InputMask::toRegex( '99999' ), $value ) )->toBe( $matches );
} )->with( [
    'ascii'        => ['12345', true],
    'arabic-indic' => ['١٢٣٤٥', false],
    'fullwidth'    => ['１２３４５', false],
] );
