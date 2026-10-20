<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Facades\Forms;
use Illuminate\Support\Facades\File;

/**
 * Guards issue #90: every string the React and Vue admin components pass to
 * `t()` must be listed in translation-keys.json, so `Forms::jsTranslations()`
 * hands the host a translation for it.
 */

/**
 * Checks whether a JS source file uses `t()`, leaving out its definition.
 */
function isTranslatableJsSource( SplFileInfo $file ): bool
{
    return in_array( $file->getExtension(), ['ts', 'tsx', 'vue'], true )
        && 'i18n.ts' !== $file->getFilename();
}

/**
 * Collects the string literals passed to `t()` across the JS sources.
 *
 * @return list<string>
 */
function collectJsTranslationKeys(): array
{
    $keys  = [];
    $files = File::allFiles( __DIR__ . '/../../resources/js' );

    foreach ( $files as $file ) {
        if ( ! isTranslatableJsSource( $file ) ) {
            continue;
        }

        preg_match_all( "/(?<![\\w.])t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $matches );

        foreach ( $matches[1] as $key ) {
            $keys[] = stripcslashes( $key );
        }
    }

    $keys = array_values( array_unique( $keys ) );
    sort( $keys );

    return $keys;
}

describe( 'JS admin translations (issue #90)', function (): void {
    it( 'lists exactly the strings the components translate', function (): void {
        expect( Forms::jsTranslationKeys() )->toBe( collectJsTranslationKeys() );
    } );

    it( 'only passes string literals to t()', function (): void {
        foreach ( File::allFiles( __DIR__ . '/../../resources/js' ) as $file ) {
            if ( ! isTranslatableJsSource( $file ) ) {
                continue;
            }

            preg_match_all( "/(?<![\\w.])t\\(\\s*([^'\\s)])/", $file->getContents(), $matches );

            expect( $matches[1] )->toBe( [], $file->getRelativePathname() . ' passes a non-literal to t()' );
        }
    } );

    it( 'translates each string with the host language files', function (): void {
        app( 'translator' )->addLines( ['*.Delete' => 'Supprimer'], 'fr' );
        app()->setLocale( 'fr' );

        $translations = Forms::jsTranslations();

        expect( $translations )->toHaveKey( 'Delete' )
            ->and( $translations['Delete'] )->toBe( 'Supprimer' )
            ->and( array_keys( $translations ) )->toBe( Forms::jsTranslationKeys() );

        foreach ( $translations as $key => $value ) {
            if ( 'Delete' !== $key ) {
                expect( $value )->toBe( $key );
            }
        }
    } );
} );
