<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormStepResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Http\Request;

describe( 'FormStepResource', function (): void {

    it( 'transforms a step into an array', function (): void {
        $form = Form::factory()->multiStep()->create();
        $step = FormStep::factory()->for( $form )->create( [
            'title'            => 'Personal Info',
            'description'      => 'Enter your personal details',
            'sort_order'       => 0,
            'next_button_text' => 'Continue',
            'prev_button_text' => 'Go Back',
        ] );

        $resource = ( new FormStepResource( $step ) )->toArray( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $step->id )
            ->toHaveKey( 'form_id', $form->id )
            ->toHaveKey( 'title', 'Personal Info' )
            ->toHaveKey( 'description', 'Enter your personal details' )
            ->toHaveKey( 'sort_order', 0 )
            ->toHaveKey( 'next_button_text', 'Continue' )
            ->toHaveKey( 'prev_button_text', 'Go Back' )
            ->toHaveKey( 'created_at' )
            ->toHaveKey( 'updated_at' );
    } );

    it( 'includes fields when loaded', function (): void {
        $form = Form::factory()->multiStep()->create();
        $step = FormStep::factory()->for( $form )->create();
        FormField::factory()->count( 2 )->for( $form )->create( ['step_id' => $step->id] );
        $step->load( 'fields' );

        $resource = ( new FormStepResource( $step ) )->toArray( Request::create( '/' ) );

        expect( $resource['fields'] )->toHaveCount( 2 );
    } );
} );
