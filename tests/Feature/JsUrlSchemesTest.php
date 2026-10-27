<?php

declare( strict_types=1 );

/**
 * Guards SEC-06 on the client: a URL field's allowed schemes never include
 * one that runs script or reads local data, as on the server.
 */

use Tests\Support\SharedTypeScript;

it( 'drops blocked schemes from a URL field\'s list', function (): void {
    $results = SharedTypeScript::run( <<<'JS'
        const { normalizeSchemeList, isAllowedUrl, getAllowedUrlSchemes } = await load( 'url' );
        const field = { name: 'site', type: 'url', validation_rules: { allowed_schemes: 'javascript, https' } };
        return [
            normalizeSchemeList( 'javascript, https' ),
            normalizeSchemeList( 'javascript' ),
            normalizeSchemeList( ['DATA:', 'vbscript', 'file://', 'blob', 'ftp'] ),
            isAllowedUrl( 'javascript://example.com/%0aalert(1)', getAllowedUrlSchemes( field ) ),
        ];
        JS );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $results )->toBe( [['https'], ['http', 'https'], ['ftp'], false] );
} );
