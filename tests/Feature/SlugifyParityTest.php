<?php

declare( strict_types=1 );

/**
 * Guards issues #138 and #139: `resources/js/shared/slugify.ts` gives the
 * same slug as `Str::slug` for punctuation and Latin letters, and an empty
 * slug for scripts it does not transliterate (the server then derives it).
 *
 * The helper is run in Node with its types stripped, so these tests are
 * skipped when no Node with `--experimental-strip-types` (22.6+) is on the
 * PATH.
 */

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Slugs each input with the client helper.
 *
 * @param  array<int, string>  $inputs  The inputs.
 *
 * @throws RuntimeException When Node runs TypeScript but not the helper.
 *
 * @return array<int, string>|null The slugs, or null when Node cannot run TypeScript.
 */
$clientSlugs = static function ( array $inputs ): ?array {
    if ( ! nodeRunsTypeScript() ) {
        return null;
    }

    $helper  = realpath( __DIR__ . '/../../resources/js/shared/slugify.ts' );
    $script  = 'import( process.argv[1] ).then( ( { slugify } ) => process.stdout.write( JSON.stringify( JSON.parse( process.argv[2] ).map( slugify ) ) ) );';
    $process = new Process( ['node', '--experimental-strip-types', '--no-warnings', '--input-type=module', '-e', $script, 'file://' . $helper, json_encode( $inputs )] );
    $process->run();

    if ( ! $process->isSuccessful() ) {
        throw new RuntimeException( 'slugify.ts did not run: ' . $process->getErrorOutput() );
    }

    return json_decode( $process->getOutput(), true, flags: JSON_THROW_ON_ERROR );
};

it( 'matches Str::slug for punctuation and Latin letters', function () use ( $clientSlugs ): void {
    $inputs = [
        "Don't stop",
        'x.y',
        'a@b',
        'Hello World',
        'snake_case name',
        '  --a--b--  ',
        'a & b',
        '1.5 Release',
        'Æther',
        'Straße',
        'coŀlegi',
        'Ünïcödé Fôrm',
        'Contact (Main) Form!',
    ];

    $slugs = $clientSlugs( $inputs );

    if ( null === $slugs ) {
        $this->markTestSkipped( 'Node 22.6+ is needed to run slugify.ts.' );
    }

    expect( $slugs )->toBe( array_map( static fn ( string $input ): string => Str::slug( $input ), $inputs ) );
} );

it( 'gives an empty slug for scripts it does not transliterate', function () use ( $clientSlugs ): void {
    $slugs = $clientSlugs( ['Привет мир', 'Ελλάδα'] );

    if ( null === $slugs ) {
        $this->markTestSkipped( 'Node 22.6+ is needed to run slugify.ts.' );
    }

    expect( $slugs )->toBe( ['', ''] );
} );

it( 'treats a name that slugifies to nothing as an auto slug in the React builder', function (): void {
    $builder = file_get_contents( __DIR__ . '/../../resources/js/react/components/admin/FormBuilder.tsx' );

    expect( $builder )
        ->toContain( "setSlugIsManual( '' !== derivedSlug && persistedSlug !== derivedSlug );" )
        ->toContain( "if ( '' === data.slug && savedSlug ) {" );
} );
