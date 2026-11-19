<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;

describe( 'field width classes (issue #89)', function (): void {
    it( 'stacks partial widths full-width on small screens', function (): void {
        expect( FieldTypes::WIDTH_CLASSES )->toBe( [
            'full'       => 'w-full',
            'half'       => 'w-full md:w-[calc((100%-1rem)/2)]',
            'third'      => 'w-full md:w-[calc((100%-2rem)/3)]',
            'two-thirds' => 'w-full md:w-[calc((200%-1rem)/3)]',
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
        'half'       => ['half', 'w-full md:w-[calc((100%-1rem)/2)]'],
        'two-thirds' => ['two-thirds', 'w-full md:w-[calc((200%-1rem)/3)]'],
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

    it( 'fits partial widths and the 1rem field gap into one row', function ( array $widths ): void {
        // Resolve `md:w-[calc((A%-Brem)/C)]` against a container of the given width.
        $resolve = static function ( string $width, float $container ): float {
            preg_match( '/md:w-\[calc\(\((\d+)%-(\d+(?:\.\d+)?)rem\)\/(\d+)\)\]/', FieldTypes::WIDTH_CLASSES[ $width ], $m );

            expect( $m )->not->toBeEmpty();

            return ( $container * (float) $m[1] / 100 - 16 * (float) $m[2] ) / (float) $m[3];
        };

        foreach ( [ 480.0, 768.0, 1152.0 ] as $container ) {
            $row = array_sum( array_map( static fn ( string $width ): float => $resolve( $width, $container ), $widths ) ) + 16 * ( count( $widths ) - 1 );

            expect( round( $row, 6 ) )->toBe( $container );
        }
    } )->with( [
        'two halves'           => [ [ 'half', 'half' ] ],
        'three thirds'         => [ [ 'third', 'third', 'third' ] ],
        'two-thirds and third' => [ [ 'two-thirds', 'third' ] ],
    ] );
} );
