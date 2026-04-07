<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
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

describe( 'FormApiController', function (): void {

    describe( 'index', function (): void {
        it( 'lists forms with pagination', function (): void {
            Form::factory()->count( 3 )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.index' ) );

            $response->assertSuccessful()
                ->assertJsonCount( 3, 'data' )
                ->assertJsonStructure( [
                    'data' => [['id', 'name', 'slug', 'is_active', 'created_at']],
                    'meta',
                    'links',
                ] );
        } );

        it( 'filters forms by status', function (): void {
            Form::factory()->count( 2 )->create( ['is_active' => true] );
            Form::factory()->inactive()->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.index', ['status' => 'active'] ) );

            $response->assertSuccessful()
                ->assertJsonCount( 2, 'data' );
        } );

        it( 'requires authentication', function (): void {
            $response = $this->getJson( route( 'api.forms.index' ) );

            $response->assertUnauthorized();
        } );
    } );

    describe( 'store', function (): void {
        it( 'creates a new form', function (): void {
            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.store' ), [
                    'name'        => 'Contact Form',
                    'description' => 'A contact form',
                ] );

            $response->assertCreated()
                ->assertJsonPath( 'data.name', 'Contact Form' )
                ->assertJsonPath( 'data.slug', 'contact-form' );

            expect( Form::where( 'name', 'Contact Form' )->exists() )->toBeTrue();
        } );

        it( 'validates required fields', function (): void {
            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.store' ), [] );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors( 'name' );
        } );

        it( 'validates unique slug', function (): void {
            Form::factory()->create( ['slug' => 'existing-slug'] );

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.store' ), [
                    'name' => 'Test',
                    'slug' => 'existing-slug',
                ] );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors( 'slug' );
        } );
    } );

    describe( 'show', function (): void {
        it( 'returns a form with relationships', function (): void {
            $form = Form::factory()->withFields( 2 )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.show', $form ) );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.id', $form->id )
                ->assertJsonPath( 'data.name', $form->name )
                ->assertJsonCount( 2, 'data.fields' );
        } );

        it( 'returns 404 for non-existent form', function (): void {
            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.show', 'non-existent' ) );

            $response->assertNotFound();
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a form', function (): void {
            $form = Form::factory()->create( ['name' => 'Old Name'] );

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.update', $form ), [
                    'name'        => 'New Name',
                    'description' => 'Updated',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.name', 'New Name' );

            expect( $form->fresh()->name )->toBe( 'New Name' );
        } );

        it( 'validates required name', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.update', $form ), [
                    'name' => '',
                ] );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors( 'name' );
        } );
    } );

    describe( 'destroy', function (): void {
        it( 'deletes a form', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->deleteJson( route( 'api.forms.destroy', $form ) );

            $response->assertNoContent();
            expect( Form::find( $form->id ) )->toBeNull();
        } );
    } );

    describe( 'render', function (): void {
        it( 'returns form definition for rendering', function (): void {
            $form = Form::factory()->withFields( 2 )->create( ['is_active' => true] );

            $response = $this->getJson( route( 'api.forms.render', $form ) );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.id', $form->id )
                ->assertJsonCount( 2, 'data.fields' )
                ->assertJsonStructure( [
                    'data' => ['id', 'name', 'slug', 'fields', 'steps', 'config'],
                ] );
        } );

        it( 'returns 404 for inactive form', function (): void {
            $form = Form::factory()->inactive()->create();

            $response = $this->getJson( route( 'api.forms.render', $form ) );

            $response->assertNotFound();
        } );

        it( 'does not require authentication', function (): void {
            $form = Form::factory()->create( ['is_active' => true] );

            $response = $this->getJson( route( 'api.forms.render', $form ) );

            $response->assertSuccessful();
        } );
    } );
} );
