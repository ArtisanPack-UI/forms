<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormSubmissionValueResource;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Http\Request;

describe( 'FormSubmissionValueResource', function (): void {

    it( 'transforms a submission value into an array', function (): void {
        $value = FormSubmissionValue::factory()->create( [
            'field_name'  => 'full_name',
            'field_label' => 'Full Name',
            'field_type'  => 'text',
            'value'       => 'John Doe',
            'value_array' => null,
            'upload_id'   => null,
        ] );

        $resource = ( new FormSubmissionValueResource( $value ) )->toArray( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $value->id )
            ->toHaveKey( 'submission_id', $value->submission_id )
            ->toHaveKey( 'field_id' )
            ->toHaveKey( 'field_name', 'full_name' )
            ->toHaveKey( 'field_label', 'Full Name' )
            ->toHaveKey( 'field_type', 'text' )
            ->toHaveKey( 'value', 'John Doe' )
            ->toHaveKey( 'value_array' )
            ->toHaveKey( 'display_value', 'John Doe' )
            ->toHaveKey( 'upload_id' )
            ->toHaveKey( 'created_at' );
    } );

    it( 'includes display value for array values', function (): void {
        $value = FormSubmissionValue::factory()->array()->create();

        $resource = ( new FormSubmissionValueResource( $value ) )->toArray( Request::create( '/' ) );

        expect( $resource['display_value'] )->toBeString()
            ->and( $resource['value_array'] )->toBeArray()->not->toBeEmpty();
    } );

    it( 'includes email field data', function (): void {
        $value = FormSubmissionValue::factory()->email()->create();

        $resource = ( new FormSubmissionValueResource( $value ) )->toArray( Request::create( '/' ) );

        expect( $resource['field_type'] )->toBe( 'email' )
            ->and( $resource['field_name'] )->toBe( 'email' )
            ->and( $resource['value'] )->toContain( '@' );
    } );
} );
