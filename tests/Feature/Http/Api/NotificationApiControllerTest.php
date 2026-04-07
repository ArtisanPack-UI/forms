<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use Illuminate\Foundation\Auth\User;

beforeEach( function (): void {
    $this->user = new class extends User {
        protected $table = 'users';

        protected $fillable = ['name', 'email', 'password'];
    };

    $this->user = $this->user->create( [
        'name'     => 'Test User',
        'email'    => 'test@example.com',
        'password' => bcrypt( 'password' ),
    ] );
} );

describe( 'NotificationApiController', function (): void {

    describe( 'index', function (): void {
        it( 'lists notifications for a form', function (): void {
            $form = Form::factory()->withNotifications( 2 )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.notifications.index', $form ) );

            $response->assertSuccessful()
                ->assertJsonCount( 2, 'data' )
                ->assertJsonStructure( [
                    'data' => [['id', 'form_id', 'type', 'name', 'is_active']],
                ] );
        } );
    } );

    describe( 'store', function (): void {
        it( 'creates an admin notification', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.notifications.store', $form ), [
                    'type'     => 'admin',
                    'name'     => 'Admin Alert',
                    'to_email' => 'admin@example.com',
                    'subject'  => 'New submission',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.type', 'admin' )
                ->assertJsonPath( 'data.name', 'Admin Alert' );
        } );

        it( 'validates notification type', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.notifications.store', $form ), [
                    'type' => 'invalid',
                ] );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors( 'type' );
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a notification', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->admin()->create();

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.notifications.update', [$form, $notification] ), [
                    'name'      => 'Updated Name',
                    'is_active' => false,
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.name', 'Updated Name' )
                ->assertJsonPath( 'data.is_active', false );
        } );

        it( 'returns 404 for notification not belonging to form', function (): void {
            $form          = Form::factory()->create();
            $otherForm     = Form::factory()->create();
            $notification  = FormNotification::factory()->for( $otherForm )->create();

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.notifications.update', [$form, $notification] ), [
                    'name' => 'Test',
                ] );

            $response->assertNotFound();
        } );
    } );

    describe( 'destroy', function (): void {
        it( 'deletes a notification', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->deleteJson( route( 'api.forms.notifications.destroy', [$form, $notification] ) );

            $response->assertNoContent();
            expect( FormNotification::find( $notification->id ) )->toBeNull();
        } );
    } );
} );
