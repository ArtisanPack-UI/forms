<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormStep;
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

describe( 'FormStepApiController', function (): void {

    describe( 'index', function (): void {
        it( 'lists steps for a form with fields', function (): void {
            $form = Form::factory()->withSteps( 2 )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.steps.index', $form ) );

            $response->assertSuccessful()
                ->assertJsonCount( 2, 'data' )
                ->assertJsonStructure( [
                    'data' => [['id', 'form_id', 'title', 'sort_order', 'fields']],
                ] );
        } );
    } );

    describe( 'store', function (): void {
        it( 'creates a new step', function (): void {
            $form = Form::factory()->multiStep()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.steps.store', $form ), [
                    'title'       => 'Personal Info',
                    'description' => 'Enter your details',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.title', 'Personal Info' );

            expect( $form->steps()->count() )->toBe( 1 );
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a step', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step = FormStep::factory()->for( $form )->create( ['title' => 'Old Title'] );

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.steps.update', [$form, $step] ), [
                    'title' => 'New Title',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.title', 'New Title' );
        } );

        it( 'returns 404 for step not belonging to form', function (): void {
            $form      = Form::factory()->create();
            $otherForm = Form::factory()->create();
            $step      = FormStep::factory()->for( $otherForm )->create();

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.steps.update', [$form, $step] ), [
                    'title' => 'Test',
                ] );

            $response->assertNotFound();
        } );
    } );

    describe( 'destroy', function (): void {
        it( 'deletes a step', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step = FormStep::factory()->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->deleteJson( route( 'api.forms.steps.destroy', [$form, $step] ) );

            $response->assertNoContent();
        } );
    } );

    describe( 'reorder', function (): void {
        it( 'reorders steps', function (): void {
            $form  = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for( $form )->create( ['sort_order' => 1] );
            $step2 = FormStep::factory()->for( $form )->create( ['sort_order' => 2] );

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.steps.reorder', $form ), [
                    'ordered_ids' => [$step2->id, $step1->id],
                ] );

            $response->assertSuccessful();

            expect( $step1->fresh()->sort_order )->toBe( 2 )
                ->and( $step2->fresh()->sort_order )->toBe( 1 );
        } );
    } );
} );
