<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Foundation\Auth\User;

// Create a simple User model for testing authentication
beforeEach( function (): void {
    // Create a test user using the existing users table from TestCase migrations
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

describe( 'FormController', function (): void {
    describe( 'index', function (): void {
        it( 'displays the forms list page', function (): void {
            $response = $this->actingAs( $this->user )
                ->get( route( 'forms.index' ) );

            $response->assertStatus( 200 )
                ->assertViewIs( 'forms::forms.index' );
        } );

        it( 'requires authentication', function (): void {
            $response = $this->get( route( 'forms.index' ) );

            $response->assertRedirect();
        } );
    } );

    describe( 'create', function (): void {
        it( 'displays the create form page', function (): void {
            $response = $this->actingAs( $this->user )
                ->get( route( 'forms.create' ) );

            $response->assertStatus( 200 )
                ->assertViewIs( 'forms::forms.create' );
        } );

        it( 'renders the create form with a CSRF token', function (): void {
            $this->actingAs( $this->user )
                ->get( route( 'forms.create' ) )
                ->assertOk()
                ->assertSee( 'action="' . route( 'forms.store' ) . '" method="POST"', false )
                ->assertSee( 'name="_token"', false );
        } );
    } );

    describe( 'store', function (): void {
        it( 'creates a new form with valid data', function (): void {
            $response = $this->actingAs( $this->user )
                ->post( route( 'forms.store' ), [
                    'name'        => 'New Contact Form',
                    'description' => 'A test form',
                ] );

            $form = Form::where( 'name', 'New Contact Form' )->first();

            expect( $form )->not->toBeNull()
                ->and( $form->slug )->toBe( 'new-contact-form' );

            $response->assertRedirect( route( 'forms.edit', $form ) )
                ->assertSessionHas( 'success' );
        } );

        it( 'validates required name field', function (): void {
            $response = $this->actingAs( $this->user )
                ->post( route( 'forms.store' ), [
                    'description' => 'No name provided',
                ] );

            $response->assertSessionHasErrors( 'name' );
        } );

        it( 'validates unique slug', function (): void {
            Form::factory()->create( ['slug' => 'existing-slug'] );

            $response = $this->actingAs( $this->user )
                ->post( route( 'forms.store' ), [
                    'name' => 'Test Form',
                    'slug' => 'existing-slug',
                ] );

            $response->assertSessionHasErrors( 'slug' );
        } );

        it( 'validates slug format', function (): void {
            $response = $this->actingAs( $this->user )
                ->post( route( 'forms.store' ), [
                    'name' => 'Test Form',
                    'slug' => 'Invalid Slug!',
                ] );

            $response->assertSessionHasErrors( 'slug' );
        } );

        it( 'auto-generates slug when empty', function (): void {
            $response = $this->actingAs( $this->user )
                ->post( route( 'forms.store' ), [
                    'name' => 'Auto Generated Slug Form',
                    'slug' => '',
                ] );

            $form = Form::where( 'name', 'Auto Generated Slug Form' )->first();

            expect( $form->slug )->toBe( 'auto-generated-slug-form' );
        } );
    } );

    describe( 'edit', function (): void {
        it( 'displays the edit form page', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->get( route( 'forms.edit', $form ) );

            $response->assertStatus( 200 )
                ->assertViewIs( 'forms::forms.edit' )
                ->assertViewHas( 'form', $form );
        } );

        it( 'renders the settings form as a spoofed PUT with a CSRF token', function (): void {
            $form = Form::factory()->create();

            $this->actingAs( $this->user )
                ->get( route( 'forms.edit', $form ) )
                ->assertOk()
                ->assertSee( 'action="' . route( 'forms.update', $form ) . '" method="POST"', false )
                ->assertSee( 'name="_method" value="PUT"', false )
                ->assertSee( 'name="_token"', false )
                ->assertDontSee( 'method="PUT"', false );
        } );

        it( 'returns 404 for non-existent form', function (): void {
            $response = $this->actingAs( $this->user )
                ->get( route( 'forms.edit', 'non-existent-slug' ) );

            $response->assertStatus( 404 );
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a form with valid data', function (): void {
            $form = Form::factory()->create( ['name' => 'Original Name'] );

            $response = $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name'        => 'Updated Name',
                    'description' => 'Updated description',
                ] );

            $response->assertRedirect( route( 'forms.edit', $form ) )
                ->assertSessionHas( 'success' );

            expect( $form->fresh()->name )->toBe( 'Updated Name' )
                ->and( $form->fresh()->description )->toBe( 'Updated description' );
        } );

        it( 'validates required name on update', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name' => '',
                ] );

            $response->assertSessionHasErrors( 'name' );
        } );

        it( 'allows same slug on update', function (): void {
            $form = Form::factory()->create( ['slug' => 'my-form'] );

            $response = $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name' => 'Updated Name',
                    'slug' => 'my-form',
                ] );

            $response->assertSessionHasNoErrors();
        } );

        it( 'prevents duplicate slug from other forms', function (): void {
            Form::factory()->create( ['slug' => 'other-form'] );
            $form = Form::factory()->create( ['slug' => 'my-form'] );

            $response = $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name' => 'Test',
                    'slug' => 'other-form',
                ] );

            $response->assertSessionHasErrors( 'slug' );
        } );

        it( 'updates is_active status', function (): void {
            $form = Form::factory()->inactive()->create();

            $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name'      => $form->name,
                    'is_active' => '1',
                ] );

            expect( $form->fresh()->is_active )->toBeTrue();
        } );

        it( 'saves checked settings checkboxes submitted as "on"', function (): void {
            $form = Form::factory()->inactive()->create( [
                'is_multi_step'         => false,
                'show_progress_bar'     => false,
                'allow_step_navigation' => false,
            ] );

            $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name'                  => $form->name,
                    'is_active'             => 'on',
                    'is_multi_step'         => 'on',
                    'show_progress_bar'     => 'on',
                    'allow_step_navigation' => 'on',
                ] )
                ->assertSessionHasNoErrors();

            $form->refresh();

            expect( $form->is_active )->toBeTrue()
                ->and( $form->is_multi_step )->toBeTrue()
                ->and( $form->show_progress_bar )->toBeTrue()
                ->and( $form->allow_step_navigation )->toBeTrue();
        } );

        it( 'saves unchecked settings checkboxes omitted from the request as false', function (): void {
            $form = Form::factory()->create( [
                'is_active'             => true,
                'is_multi_step'         => true,
                'show_progress_bar'     => true,
                'allow_step_navigation' => true,
            ] );

            $this->actingAs( $this->user )
                ->put( route( 'forms.update', $form ), [
                    'name' => $form->name,
                ] )
                ->assertSessionHasNoErrors();

            $form->refresh();

            expect( $form->is_active )->toBeFalse()
                ->and( $form->is_multi_step )->toBeFalse()
                ->and( $form->show_progress_bar )->toBeFalse()
                ->and( $form->allow_step_navigation )->toBeFalse();
        } );
    } );

    describe( 'destroy', function (): void {
        it( 'deletes a form', function (): void {
            $form   = Form::factory()->create();
            $formId = $form->id;

            $response = $this->actingAs( $this->user )
                ->delete( route( 'forms.destroy', $form ) );

            $response->assertRedirect( route( 'forms.index' ) )
                ->assertSessionHas( 'success' );

            expect( Form::find( $formId ) )->toBeNull();
        } );
    } );
} );
