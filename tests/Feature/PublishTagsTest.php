<?php

declare( strict_types=1 );

/**
 * Guards the vendor:publish tags against README/provider drift.
 *
 * `vendor:publish` exits 0 on a tag that matches nothing, so a documented tag
 * that was never registered fails silently — the consumer gets no config, no
 * views and no error. These tests assert every tag the documentation instructs
 * is actually registered, and that the package-scoped publish tags resolve to
 * the source files they are meant to publish.
 *
 * @since 1.4.0
 */

use Illuminate\Support\ServiceProvider;

/**
 * Maps each publishable asset to the destination path it publishes to and the
 * package-scoped tag that publishes it. Source paths are relative to the
 * package root.
 *
 * @return array<string, array{ source: string, destination: string, tag: string }>
 */
function formsPublishMap(): array
{
    return [
        'config' => formsPublishEntry(
            'config/forms.php',
            'artisanpack/forms.php',
            'forms-config',
        ),
        'views' => formsPublishEntry(
            'resources/views',
            'views/vendor/forms',
            'forms-views',
        ),
        'types' => formsPublishEntry(
            'resources/js/types/artisanpack-forms.d.ts',
            'types/artisanpack-forms.d.ts',
            'forms-types',
        ),
    ];
}

/**
 * Builds one entry of the publish map.
 *
 * @param  string  $source       Source path relative to the package root.
 * @param  string  $destination  Tail of the path the source publishes to.
 * @param  string  $tag          Package-scoped tag that publishes this source.
 *
 * @return array{ source: string, destination: string, tag: string }
 */
function formsPublishEntry( string $source, string $destination, string $tag ): array
{
    return compact( 'source', 'destination', 'tag' );
}

/**
 * Wraps the publish map for use as a Pest dataset.
 *
 * Pest spreads an array-valued dataset entry across the test's parameters, so
 * each config array is nested one level deeper to arrive as a single argument.
 *
 * @return array<string, array{ 0: array{ source: string, destination: string, tag: string } }>
 */
function formsPublishDataset(): array
{
    return array_map(
        fn ( array $config ): array => [ $config ],
        formsPublishMap(),
    );
}

/**
 * Resolves the destination a tag publishes a given source to.
 *
 * Providers register sources as unresolved `__DIR__ . '/../...'` strings, so
 * both sides are run through realpath() before comparing.
 *
 * @param  string  $tag     Publish tag to look in.
 * @param  string  $source  Source path relative to the package root.
 *
 * @return string|null The destination path, or null when the tag does not
 *                     publish that source.
 */
function formsPublishDestination( string $tag, string $source ): ?string
{
    $tags = ServiceProvider::$publishGroups;

    if ( ! isset( $tags[ $tag ] ) ) {
        return null;
    }

    $expected = realpath( __DIR__ . '/../../' . $source );

    if ( false === $expected ) {
        return null;
    }

    foreach ( $tags[ $tag ] as $from => $to ) {
        if ( realpath( $from ) === $expected ) {
            return $to;
        }
    }

    return null;
}

/**
 * Lists the publish tags that resolve to a file inside this package.
 *
 * `ServiceProvider::$publishGroups` is a global static that accumulates every
 * provider the TestCase boots — Hooks, Livewire, Ai — so a bare `toHaveKey()`
 * against it proves only that *some* provider claimed the tag. Ownership is
 * therefore decided by realpath prefix.
 *
 * A plain package-root prefix is not enough: this package's own `vendor/` lives
 * under that root, so every dependency's publishes would match it and the
 * filter would be a no-op. Matching the source directories a first-party
 * publish can legitimately come from keeps the check meaningful.
 *
 * @return array<int, string>
 */
function formsOwnedPublishTags(): array
{
    $packageRoot = realpath( __DIR__ . '/../../' );
    $ownedRoots  = array_filter( [
        realpath( $packageRoot . '/src' ),
        realpath( $packageRoot . '/config' ),
        realpath( $packageRoot . '/resources' ),
        realpath( $packageRoot . '/database' ),
    ] );

    $owned = [];

    foreach ( ServiceProvider::$publishGroups as $tag => $paths ) {
        foreach ( array_keys( $paths ) as $from ) {
            $resolved = realpath( $from );

            if ( false === $resolved ) {
                continue;
            }

            foreach ( $ownedRoots as $root ) {
                if ( str_starts_with( $resolved, (string) $root . DIRECTORY_SEPARATOR ) ) {
                    $owned[] = (string) $tag;
                    continue 3;
                }
            }
        }
    }

    return $owned;
}

test( 'each package-scoped publish tag is registered by this package', function ( array $config ): void {
    expect( formsOwnedPublishTags() )->toContain( $config['tag'] );
} )->with( formsPublishDataset() );

test( 'each package-scoped tag publishes its source to the documented destination', function ( array $config ): void {
    $destination = formsPublishDestination( $config['tag'], $config['source'] );

    expect( $destination )->not()->toBeNull();
    expect( str_replace( '\\', '/', (string) $destination ) )
        ->toEndWith( $config['destination'] );
} )->with( formsPublishDataset() );

test( 'the forms-config file is also published by the ecosystem-wide tag', function (): void {
    $destination = formsPublishDestination( 'artisanpack-package-config', 'config/forms.php' );

    expect( $destination )->not()->toBeNull();
    expect( str_replace( '\\', '/', (string) $destination ) )
        ->toEndWith( 'artisanpack/forms.php' );
} );

test( 'the views are also published by the artisanpack-forms-views tag', function (): void {
    $destination = formsPublishDestination( 'artisanpack-forms-views', 'resources/views' );

    expect( $destination )->not()->toBeNull();
    expect( str_replace( '\\', '/', (string) $destination ) )
        ->toEndWith( 'views/vendor/forms' );
} );

test( 'every publish source the tags point at exists on disk', function ( array $config ): void {
    $path = realpath( __DIR__ . '/../../' . $config['source'] );

    expect( $path )->not()->toBeFalse();
    expect( file_exists( (string) $path ) )->toBeTrue();
} )->with( formsPublishDataset() );

/**
 * Collects the README plus every Markdown file under docs/ recursively.
 *
 * The drift in #62 was not confined to the README — the installation guide, the
 * FAQ, the troubleshooting guide and eight component docs each instructed a tag
 * that was registered nowhere, so scanning the README alone would leave the same
 * failure free to reappear one directory over.
 *
 * @return array<int, string>
 */
function formsDocFiles(): array
{
    $root  = __DIR__ . '/../../';
    $files = [ $root . 'README.md' ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $root . 'docs', FilesystemIterator::SKIP_DOTS ),
    );

    foreach ( $iterator as $file ) {
        if ( 'md' === $file->getExtension() ) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test( 'every publish tag the docs instruct is actually registered', function (): void {
    $unregistered = [];
    $ownedTags    = formsOwnedPublishTags();

    foreach ( formsDocFiles() as $file ) {
        $contents = file_get_contents( $file );

        if ( false === $contents ) {
            continue;
        }

        preg_match_all( '/--tag=["\']?([a-z0-9._-]+)["\']?/i', $contents, $matches );

        foreach ( array_unique( $matches[1] ) as $tag ) {
            if ( ! in_array( $tag, $ownedTags, true ) ) {
                $unregistered[] = basename( $file ) . ' documents --tag=' . $tag;
            }
        }
    }

    expect( $unregistered )->toBe( [] );
} );

test( 'the docs instruct at least one publish tag', function (): void {
    $found = 0;

    foreach ( formsDocFiles() as $file ) {
        $contents = file_get_contents( $file );
        $found += false === $contents ? 0 : preg_match_all( '/--tag=/', $contents );
    }

    expect( $found )->toBeGreaterThan( 0 );
} );
