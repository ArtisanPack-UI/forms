<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Http\Request;

describe( 'FormRenderResource', function (): void {

    it( 'transforms a form into a render definition', function (): void {
        $form = Form::factory()->create( [
            'name'                  => 'Contact Form',
            'slug'                  => 'contact-form',
            'description'           => 'A contact form',
            'submit_button_text'    => 'Send',
            'success_message'       => 'Thanks!',
            'redirect_url'          => null,
            'is_multi_step'         => false,
            'show_progress_bar'     => false,
            'allow_step_navigation' => false,
        ] );

        $resource = ( new FormRenderResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $form->id )
            ->toHaveKey( 'name', 'Contact Form' )
            ->toHaveKey( 'slug', 'contact-form' )
            ->toHaveKey( 'description', 'A contact form' )
            ->toHaveKey( 'submit_button_text', 'Send' )
            ->toHaveKey( 'success_message', 'Thanks!' )
            ->toHaveKey( 'is_multi_step', false )
            ->toHaveKey( 'fields' )
            ->toHaveKey( 'steps' )
            ->toHaveKey( 'config' );
    } );

    it( 'includes fields directly from the form', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->count( 3 )->for( $form )->create();

        $resource = ( new FormRenderResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['fields'] )->toHaveCount( 3 );
    } );

    it( 'includes steps for multi-step forms', function (): void {
        $form = Form::factory()->multiStep()->create();
        FormStep::factory()->count( 2 )->for( $form )->create();

        $resource = ( new FormRenderResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['steps'] )->toHaveCount( 2 )
            ->and( $resource['is_multi_step'] )->toBeTrue();
    } );

    it( 'includes honeypot config', function (): void {
        config()->set( 'artisanpack.forms.spam_protection.honeypot.enabled', true );
        config()->set( 'artisanpack.forms.spam_protection.honeypot.field_name', 'website_url' );

        $form = Form::factory()->create();

        $resource = ( new FormRenderResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['config']['honeypot'] )
            ->toHaveKey( 'enabled', true )
            ->toHaveKey( 'field_name', 'website_url' );
    } );

    it( 'includes display config', function (): void {
        config()->set( 'artisanpack.forms.display', [
            'label_position'          => 'above',
            'show_required_indicator' => true,
        ] );

        $form = Form::factory()->create();

        $resource = ( new FormRenderResource( $form ) )->toArray( Request::create( '/' ) );

        expect( $resource['config']['display'] )
            ->toHaveKey( 'label_position', 'above' )
            ->toHaveKey( 'show_required_indicator', true );
    } );
} );
