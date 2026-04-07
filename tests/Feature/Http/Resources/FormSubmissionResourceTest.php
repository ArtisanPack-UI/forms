<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormSubmissionResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;
use Illuminate\Http\Request;

describe( 'FormSubmissionResource', function (): void {

    it( 'transforms a submission into an array', function (): void {
        $form       = Form::factory()->create();
        $submission = FormSubmission::factory()->for( $form )->create( [
            'submission_number' => 'SUB-001',
            'page_url'          => 'https://example.com/contact',
            'referrer_url'      => 'https://google.com',
            'is_read'           => false,
            'is_spam'           => false,
            'is_starred'        => true,
            'admin_notes'       => 'Follow up needed',
        ] );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $submission->id )
            ->toHaveKey( 'form_id', $form->id )
            ->toHaveKey( 'submission_number' )
            ->toHaveKey( 'page_url', 'https://example.com/contact' )
            ->toHaveKey( 'referrer_url', 'https://google.com' )
            ->toHaveKey( 'is_read', false )
            ->toHaveKey( 'is_spam', false )
            ->toHaveKey( 'is_starred', true )
            ->toHaveKey( 'admin_notes', 'Follow up needed' )
            ->toHaveKey( 'created_at' )
            ->toHaveKey( 'updated_at' );
    } );

    it( 'excludes ip address when privacy config is off', function (): void {
        config()->set( 'artisanpack.forms.privacy.include_ip_address', false );

        $submission = FormSubmission::factory()->create( ['ip_address' => '192.168.1.1'] );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource )->not->toHaveKey( 'ip_address' );
    } );

    it( 'includes ip address when privacy config is on', function (): void {
        config()->set( 'artisanpack.forms.privacy.include_ip_address', true );

        $submission = FormSubmission::factory()->create( ['ip_address' => '192.168.1.1'] );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource )->toHaveKey( 'ip_address', '192.168.1.1' );
    } );

    it( 'excludes user agent when privacy config is off', function (): void {
        config()->set( 'artisanpack.forms.privacy.include_user_agent', false );

        $submission = FormSubmission::factory()->create( ['user_agent' => 'Mozilla/5.0'] );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource )->not->toHaveKey( 'user_agent' );
    } );

    it( 'includes user agent when privacy config is on', function (): void {
        config()->set( 'artisanpack.forms.privacy.include_user_agent', true );

        $submission = FormSubmission::factory()->create( ['user_agent' => 'Mozilla/5.0'] );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource )->toHaveKey( 'user_agent', 'Mozilla/5.0' );
    } );

    it( 'includes values when loaded', function (): void {
        $submission = FormSubmission::factory()->create();
        FormSubmissionValue::factory()->count( 2 )->create( ['submission_id' => $submission->id] );
        $submission->load( 'values' );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource['values'] )->toHaveCount( 2 );
    } );

    it( 'includes uploads when loaded', function (): void {
        $submission = FormSubmission::factory()->create();
        FormUpload::factory()->count( 2 )->create( ['submission_id' => $submission->id] );
        $submission->load( 'uploads' );

        $resource = ( new FormSubmissionResource( $submission ) )->resolve( Request::create( '/' ) );

        expect( $resource['uploads'] )->toHaveCount( 2 );
    } );
} );
