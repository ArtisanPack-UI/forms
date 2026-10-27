<?php

declare( strict_types=1 );

/**
 * Guards VU-03: the Vue field editor binds its inputs, the conditional logic
 * editor included, to the field with its buffered edits, so a second edit
 * within the debounce (or during the save) builds on the first.
 */

it( 'binds the editors to the field with its buffered edits', function (): void {
    $source = (string) file_get_contents( __DIR__ . '/../../resources/js/vue/components/admin/FieldEditor.vue' );

    expect( $source )
        ->toContain( ':value="currentField.conditional_logic"' )
        ->toContain( ':model-value="currentField.label ?? \'\'"' )
        ->toContain( ':model-value="currentField.is_required"' )
        ->toContain( ':model-value="getLayoutContent( currentField )"' )
        ->not->toContain( ':value="field.conditional_logic"' )
        ->not->toMatch( '/:model-value="field\./' );
} );
