<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Guards FT-07: date limits are resolved on the server, in the app's time
 * zone, and sent to the React and Vue renderers.
 */
beforeEach( function (): void {
    config( ['app.timezone' => 'UTC'] );
    date_default_timezone_set( 'UTC' );
    Carbon::setTestNow( Carbon::parse( '2026-10-28 01:00', 'UTC' ) );

    $this->form     = Form::factory()->create();
    $this->resource = fn ( FormField $field ): array => ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) );
} );

it( 'sends a date field\'s resolved limits and bounds', function (): void {
    $field = FormField::factory()->for( $this->form )->create( ['type' => 'date', 'name' => 'visit', 'validation_rules' => ['date_range' => 'no_past', 'max_date' => '+30 days']] );

    $data = ( $this->resource )( $field );

    expect( $data['date_limits'] )->toBe( ['min' => '2026-10-28', 'max' => '2026-11-27'] )
        ->and( $data['date_bounds'] )->toBe( $field->getDateBounds() );
} );

it( 'sends a birthdate\'s age bounds', function (): void {
    $field = FormField::factory()->for( $this->form )->create( ['type' => 'date', 'name' => 'born', 'validation_rules' => ['min_age' => 18]] );

    expect( ( $this->resource )( $field )['date_bounds']['max'] )->toBe( '2008-10-28' );
} );

it( 'sends limits for date and time and date range fields', function ( string $type ): void {
    $field = FormField::factory()->for( $this->form )->create( ['type' => $type, 'name' => 'when', 'validation_rules' => ['max_date' => 'today']] );

    expect( ( $this->resource )( $field )['date_limits'] )->toBe( $field->getDateLimits() )
        ->and( ( $this->resource )( $field )['date_limits']['max'] )->toBe( '2026-10-28' );
} )->with( ['datetime', 'date_range'] );

it( 'leaves the limits out for other types', function (): void {
    $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name'] );

    expect( ( $this->resource )( $field ) )->not->toHaveKey( 'date_limits' )
        ->and( ( $this->resource )( $field ) )->not->toHaveKey( 'date_bounds' );
} );
