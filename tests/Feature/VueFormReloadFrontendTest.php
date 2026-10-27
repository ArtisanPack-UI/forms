<?php

declare( strict_types=1 );

/**
 * Guards VU-11: the Vue `useForm` reloads when its form slug or API base
 * changes, and ignores a response for a slug since replaced, as React does.
 */

it( 'watches the slug and base URL and ignores stale loads', function (): void {
    $useForm  = (string) file_get_contents( __DIR__ . '/../../resources/js/vue/composables/useForm.ts' );
    $renderer = (string) file_get_contents( __DIR__ . '/../../resources/js/vue/components/FormRenderer.vue' );

    expect( $useForm )
        ->toContain( 'formSlug: MaybeRefOrGetter<string>;' )
        ->toContain( 'watch( [() => toValue( baseUrl ), () => toValue( formSlug )], () => {' )
        ->toContain( 'if ( load !== latestLoad ) {' )
        ->toContain( '`${toValue( baseUrl )}/${encodeURIComponent( toValue( formSlug ) )}/submit`' )
        ->and( $renderer )
        ->toContain( 'formSlug: () => props.formSlug,' )
        ->toContain( 'baseUrl: () => props.baseUrl,' );
} );
