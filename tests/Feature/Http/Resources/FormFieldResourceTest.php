<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Http\Request;

describe( 'FormFieldResource', function (): void {

    it( 'transforms a field into an array', function (): void {
        $form  = Form::factory()->create();
        $field = FormField::factory()->for( $form )->create( [
            'name'              => 'email',
            'type'              => 'email',
            'label'             => 'Email Address',
            'placeholder'       => 'you@example.com',
            'help_text'         => 'Enter your email',
            'is_required'       => true,
            'validation_rules'  => ['email' => true],
            'field_config'      => ['autocomplete' => 'email'],
            'default_value'     => null,
            'conditional_logic' => null,
            'width'             => 'full',
            'css_classes'       => 'custom-class',
            'sort_order'        => 1,
        ] );

        $resource = ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $field->id )
            ->toHaveKey( 'form_id', $form->id )
            ->toHaveKey( 'uuid', $field->uuid )
            ->toHaveKey( 'name', 'email' )
            ->toHaveKey( 'type', 'email' )
            ->toHaveKey( 'label', 'Email Address' )
            ->toHaveKey( 'placeholder', 'you@example.com' )
            ->toHaveKey( 'help_text', 'Enter your email' )
            ->toHaveKey( 'is_required', true )
            ->toHaveKey( 'validation_rules' )
            ->toHaveKey( 'field_config' )
            ->toHaveKey( 'width', 'full' )
            ->toHaveKey( 'css_classes', 'custom-class' )
            ->toHaveKey( 'sort_order', 1 )
            ->toHaveKey( 'created_at' )
            ->toHaveKey( 'updated_at' );
    } );

    it( 'includes options for select fields', function (): void {
        $form  = Form::factory()->create();
        $field = FormField::factory()->select()->for( $form )->create();

        $resource = ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) );

        expect( $resource )->toHaveKey( 'options' )
            ->and( $resource['options'] )->toBeArray()->not->toBeEmpty();
    } );

    it( 'excludes options for non-select fields', function (): void {
        $form  = Form::factory()->create();
        $field = FormField::factory()->text()->for( $form )->create();

        $resource = ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) );

        expect( $resource )->not->toHaveKey( 'options' );
    } );

    it( 'includes conditional logic when set', function (): void {
        $form  = Form::factory()->create();
        $field = FormField::factory()->withConditions()->for( $form )->create();

        $resource = ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) );

        expect( $resource['conditional_logic'] )->toBeArray()->not->toBeEmpty();
    } );
} );
