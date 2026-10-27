<?php

declare( strict_types=1 );

/**
 * Guards RX8: the @artisanpack-ui/react Input, Checkbox and Select take no
 * `size` prop (it would become the HTML `size` attribute), so the React
 * notification editor sizes them with daisyUI classes instead.
 */

it( 'sizes form controls with daisyUI classes, not a size prop', function (): void {
    $source = (string) file_get_contents( __DIR__ . '/../../resources/js/react/components/admin/NotificationEditor.tsx' );

    // Only the two Buttons, which take a size, keep `size="sm"`
    expect( substr_count( $source, 'size="sm"' ) )->toBe( 2 )
        ->and( $source )->toContain( 'className="input-sm"' )
        ->toContain( 'className="checkbox-sm"' )
        ->toContain( 'className="select-sm"' );
} );
