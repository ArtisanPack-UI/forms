<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormNotificationResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use Illuminate\Http\Request;

describe( 'FormNotificationResource', function (): void {

    it( 'transforms a notification into an array', function (): void {
        $form         = Form::factory()->create();
        $notification = FormNotification::factory()->admin()->for( $form )->create( [
            'name'                    => 'Admin Alert',
            'to_email'                => 'admin@example.com',
            'cc_emails'               => 'cc@example.com',
            'bcc_emails'              => 'bcc@example.com',
            'reply_to_email'          => 'reply@example.com',
            'from_name'               => 'Contact Form',
            'from_email'              => 'noreply@example.com',
            'subject'                 => 'New Submission',
            'message'                 => 'A new submission was received.',
            'include_submission_data' => true,
            'is_active'               => true,
            'sort_order'              => 0,
        ] );

        $resource = ( new FormNotificationResource( $notification ) )->toArray( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $notification->id )
            ->toHaveKey( 'form_id', $form->id )
            ->toHaveKey( 'type', FormNotification::TYPE_ADMIN )
            ->toHaveKey( 'name', 'Admin Alert' )
            ->toHaveKey( 'to_email', 'admin@example.com' )
            ->toHaveKey( 'cc_emails', 'cc@example.com' )
            ->toHaveKey( 'bcc_emails', 'bcc@example.com' )
            ->toHaveKey( 'reply_to_email', 'reply@example.com' )
            ->toHaveKey( 'from_name', 'Contact Form' )
            ->toHaveKey( 'from_email', 'noreply@example.com' )
            ->toHaveKey( 'subject', 'New Submission' )
            ->toHaveKey( 'message', 'A new submission was received.' )
            ->toHaveKey( 'include_submission_data', true )
            ->toHaveKey( 'is_active', true )
            ->toHaveKey( 'sort_order', 0 )
            ->toHaveKey( 'created_at' )
            ->toHaveKey( 'updated_at' );
    } );

    it( 'transforms an autoresponder notification', function (): void {
        $notification = FormNotification::factory()->autoresponder()->create();

        $resource = ( new FormNotificationResource( $notification ) )->toArray( Request::create( '/' ) );

        expect( $resource['type'] )->toBe( FormNotification::TYPE_AUTORESPONDER )
            ->and( $resource['to_field'] )->toBe( 'email' );
    } );

    it( 'includes conditional logic when set', function (): void {
        $notification = FormNotification::factory()->withConditions()->create();

        $resource = ( new FormNotificationResource( $notification ) )->toArray( Request::create( '/' ) );

        expect( $resource['conditional_logic'] )->toBeArray()->not->toBeEmpty();
    } );
} );
