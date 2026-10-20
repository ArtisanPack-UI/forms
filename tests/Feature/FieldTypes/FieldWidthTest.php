<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;

describe( 'field width classes (issue #89)', function (): void {
    it( 'stacks partial widths full-width on small screens', function (): void {
        expect( FieldTypes::WIDTH_CLASSES )->toBe( [
            'full'       => 'w-full',
            'half'       => 'w-full md:w-1/2',
            'third'      => 'w-full md:w-1/3',
            'two-thirds' => 'w-full md:w-2/3',
        ] );
    } );

    it( 'gives the width options the same classes', function (): void {
        foreach ( FieldTypes::getWidths() as $width => $option ) {
            expect( $option['class'] )->toBe( FieldTypes::WIDTH_CLASSES[ $width ] );
        }
    } );

    it( 'gives a field the class of its width, or full width', function ( string $width, string $expected ): void {
        expect( FormField::factory()->make( ['width' => $width] )->width_class )->toBe( $expected );
    } )->with( [
        'half'       => ['half', 'w-full md:w-1/2'],
        'two-thirds' => ['two-thirds', 'w-full md:w-2/3'],
        'unknown'    => ['quarter', 'w-full'],
    ] );

    it( 'mirrors the classes in the shared JS used by the React and Vue renderers', function (): void {
        $source = file_get_contents( __DIR__ . '/../../../resources/js/shared/fieldSettings.ts' );

        preg_match( '/export const WIDTH_CLASSES[^{]*\{(.*?)\};/s', $source, $block );
        preg_match_all( "/'?([\\w-]+)'?:\\s*'([^']+)'/", $block[1] ?? '', $pairs, PREG_SET_ORDER );

        $classes = [];

        foreach ( $pairs as [, $width, $class] ) {
            $classes[ $width ] = $class;
        }

        expect( $classes )->toBe( FieldTypes::WIDTH_CLASSES );
    } );
} );
