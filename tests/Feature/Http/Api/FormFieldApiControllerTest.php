<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
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

describe( 'FormFieldApiController', function (): void {

    describe( 'index', function (): void {
        it( 'lists fields for a form', function (): void {
            $form = Form::factory()->withFields( 3 )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.fields.index', $form ) );

            $response->assertSuccessful()
                ->assertJsonCount( 3, 'data' )
                ->assertJsonStructure( [
                    'data' => [['id', 'form_id', 'uuid', 'type', 'label', 'sort_order']],
                ] );
        } );
    } );

    describe( 'store', function (): void {
        it( 'creates a new field', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.fields.store', $form ), [
                    'type'  => 'text',
                    'label' => 'Full Name',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.type', 'text' )
                ->assertJsonPath( 'data.label', 'Full Name' );

            expect( $form->fields()->count() )->toBe( 1 );
        } );

        it( 'validates required type', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.fields.store', $form ), [] );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors( 'type' );
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a field', function (): void {
            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create( ['label' => 'Old Label'] );

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.fields.update', [$form, $field] ), [
                    'label' => 'New Label',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.label', 'New Label' );
        } );

        it( 'returns 404 for field not belonging to form', function (): void {
            $form      = Form::factory()->create();
            $otherForm = Form::factory()->create();
            $field     = FormField::factory()->for( $otherForm )->create();

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.fields.update', [$form, $field] ), [
                    'label' => 'Test',
                ] );

            $response->assertNotFound();
        } );
    } );

    describe( 'destroy', function (): void {
        it( 'deletes a field', function (): void {
            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->deleteJson( route( 'api.forms.fields.destroy', [$form, $field] ) );

            $response->assertNoContent();
            expect( FormField::find( $field->id ) )->toBeNull();
        } );
    } );

    describe( 'reorder', function (): void {
        it( 'reorders fields', function (): void {
            $form   = Form::factory()->create();
            $field1 = FormField::factory()->for( $form )->create( ['sort_order' => 1] );
            $field2 = FormField::factory()->for( $form )->create( ['sort_order' => 2] );

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.fields.reorder', $form ), [
                    'ordered_uuids' => [$field2->uuid, $field1->uuid],
                ] );

            $response->assertSuccessful();

            expect( $field1->fresh()->sort_order )->toBe( 2 )
                ->and( $field2->fresh()->sort_order )->toBe( 1 );
        } );
    } );
} );
