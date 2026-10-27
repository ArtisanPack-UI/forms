<?php

declare( strict_types=1 );

/**
 * Guards FT-07: the React/Vue client uses the date limits the server resolved
 * for its own "today" (`date_limits`, `date_bounds`) when a field has them.
 */

use Tests\Support\SharedTypeScript;

it( 'prefers the limits and bounds the server resolved', function (): void {
    $results = SharedTypeScript::run( <<<'JS'
        const { getDateLimits, getDateBounds } = await load( 'dateConstraints' );
        const server = {
            name: 'when', type: 'date',
            validation_rules: { date_range: 'no_past' },
            date_limits: { min: '2026-10-28', max: null },
            date_bounds: { min: '2026-10-28', max: '2026-12-31' },
        };
        const local = { name: 'when', type: 'date', validation_rules: { min_date: '2020-01-01', max_date: '2020-12-31' } };
        return [getDateLimits( server ), getDateBounds( server ), getDateLimits( local ), getDateBounds( local )];
        JS );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $results )->toBe( [
        ['min' => '2026-10-28'],
        ['min' => '2026-10-28', 'max' => '2026-12-31'],
        ['min' => '2020-01-01', 'max' => '2020-12-31'],
        ['min' => '2020-01-01', 'max' => '2020-12-31'],
    ] );
} );

it( 'validates against the server\'s day, not the browser\'s', function (): void {
    $errors = SharedTypeScript::run( <<<'JS'
        const { validateField } = await load( 'validation' );
        const field = {
            name: 'when', label: 'When', type: 'date',
            validation_rules: { date_range: 'no_past', min_age: 18 },
            date_limits: { min: '2000-01-01', max: null },
            date_bounds: { min: '2000-01-01', max: '2008-10-28' },
        };
        return ['1999-12-31', '2008-10-28', '2008-10-29'].map( ( value ) => validateField( field, value ) );
        JS );

    if ( null === $errors ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $errors[0] )->toHaveCount( 1 )
        ->and( $errors[0][0] )->toContain( '2000-01-01' )
        ->and( $errors[1] )->toBe( [] )
        ->and( $errors[2] )->toBe( ['You must be at least 18 years old.'] );
} );

it( 'declares the server bounds on the field type', function (): void {
    expect( (string) file_get_contents( __DIR__ . '/../../resources/js/types/artisanpack-forms.d.ts' ) )
        ->toContain( 'date_limits?: ServerDateBounds;' )
        ->toContain( 'date_bounds?: ServerDateBounds;' );
} );
