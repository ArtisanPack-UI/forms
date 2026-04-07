<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Http\Request;

describe( 'FormResource', function (): void {

    it( 'transforms a form into an array', function (): void {
        $form = Form::factory()->create( [
            'name'                  => 'Contact Form',
            'slug'                  => 'contact-form',
            'description'           => 'A contact form',
            'submit_button_text'    => 'Submit',
            'success_message'       => 'Thank you!',
            'redirect_url'          => 'https://example.com/thanks',
            'is_multi_step'         => false,
            'show_progress_bar'     => true,
            'allow_step_navigation' => false,
            'is_active'             => true,
        ] );

        $resource = ( new FormResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $form->id )
            ->toHaveKey( 'name', 'Contact Form' )
            ->toHaveKey( 'slug', 'contact-form' )
            ->toHaveKey( 'description', 'A contact form' )
            ->toHaveKey( 'submit_button_text', 'Submit' )
            ->toHaveKey( 'success_message', 'Thank you!' )
            ->toHaveKey( 'redirect_url', 'https://example.com/thanks' )
            ->toHaveKey( 'is_multi_step', false )
            ->toHaveKey( 'show_progress_bar', true )
            ->toHaveKey( 'allow_step_navigation', false )
            ->toHaveKey( 'is_active', true )
            ->toHaveKey( 'created_at' )
            ->toHaveKey( 'updated_at' );
    } );

    it( 'includes fields when loaded', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->count( 2 )->for( $form )->create();
        $form->load( 'fields' );

        $resource = ( new FormResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['fields'] )->toHaveCount( 2 );
    } );

    it( 'includes steps when loaded', function (): void {
        $form = Form::factory()->multiStep()->create();
        FormStep::factory()->count( 3 )->for( $form )->create();
        $form->load( 'steps' );

        $resource = ( new FormResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['steps'] )->toHaveCount( 3 );
    } );

    it( 'includes notifications when loaded', function (): void {
        $form = Form::factory()->create();
        FormNotification::factory()->count( 2 )->for( $form )->create();
        $form->load( 'notifications' );

        $resource = ( new FormResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['notifications'] )->toHaveCount( 2 );
    } );

    it( 'includes submission counts', function (): void {
        $form = Form::factory()->create();
        FormSubmission::factory()->count( 3 )->for( $form )->create( ['is_read' => false] );
        FormSubmission::factory()->for( $form )->create( ['is_read' => true] );

        $resource = ( new FormResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['total_submissions_count'] )->toBe( 4 )
            ->and( $resource['unread_submissions_count'] )->toBe( 3 );
    } );

    it( 'formats dates as ISO 8601', function (): void {
        $form = Form::factory()->create();

        $resource = ( new FormResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['created_at'] )->toMatch( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/' )
            ->and( $resource['updated_at'] )->toMatch( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/' );
    } );
} );
