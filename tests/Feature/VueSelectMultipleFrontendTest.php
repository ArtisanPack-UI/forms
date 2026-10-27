<?php

declare( strict_types=1 );

/**
 * Guards VU-01: Vue sets a `<select multiple>`'s `value` as one string, so
 * the native multi-select marks each option selected instead.
 */

it( 'selects each chosen option of a native multi-select', function (): void {
    $source = (string) file_get_contents( __DIR__ . '/../../resources/js/vue/components/fields/ChoiceField.vue' );
    $select = substr( $source, (int) strpos( $source, "multiple\n\t\t\t\t:required" ) - 200, 1400 );

    expect( $select )->toContain( ':selected="selectedArray.includes( option.value )"' )
        ->toContain( '@change="handleMultiSelectChange"' )
        ->and( $source )->not->toContain( ':value="selectedArray"' );
} );
