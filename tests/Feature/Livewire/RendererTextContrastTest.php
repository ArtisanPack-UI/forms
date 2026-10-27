<?php

declare( strict_types=1 );

/**
 * Guards LW-12: text in the public renderer uses at least 70% of the base
 * content colour, which keeps small text at 4.5:1 on the default themes.
 */
it( 'uses no low-contrast text in :dataset', function ( string $view ): void {
    $source = file_get_contents( __DIR__ . '/../../../resources/views/' . $view );

    // Decorative, aria-hidden icons are exempt; text classes are not
    $lines = array_filter(
        explode( "\n", $source ),
        fn ( string $line ): bool => ! str_contains( $line, 'aria-hidden="true"' ),
    );

    expect( implode( "\n", $lines ) )->not->toMatch( '/text-base-content\/[1-6]0\b/' );
} )->with( [
    'the renderer'      => 'livewire/form-renderer.blade.php',
    'the rich text box' => 'components/fields/partials/rich-text.blade.php',
] );
