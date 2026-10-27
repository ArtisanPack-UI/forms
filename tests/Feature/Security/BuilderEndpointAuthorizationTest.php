<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Models\User;

/**
 * Guards REL-09: the builder's choice-image upload and predefined-choices
 * endpoints are forbidden to a signed-in user who may not update the form.
 */
beforeEach( function (): void {
    config( ['artisanpack.forms.authorization.restrict_by_owner' => true] );
    Storage::fake( 'public' );

    $this->owner    = User::factory()->create( ['is_admin' => false] );
    $this->stranger = User::factory()->create( ['is_admin' => false] );
    $this->form     = Form::factory()->create( ['user_id' => $this->owner->id] );
} );

it( 'forbids uploading a choice image to someone else\'s form', function (): void {
    $this->actingAs( $this->stranger )
        ->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => UploadedFile::fake()->image( 'a.png' )] )
        ->assertForbidden();

    expect( Storage::disk( 'public' )->allFiles() )->toBe( [] );
} );

it( 'forbids reading predefined choices for someone else\'s form', function (): void {
    $this->actingAs( $this->stranger )
        ->getJson( route( 'api.forms.fields.predefined-choices', $this->form ) )
        ->assertForbidden();
} );

it( 'allows the owner', function (): void {
    $this->actingAs( $this->owner )
        ->getJson( route( 'api.forms.fields.predefined-choices', $this->form ) )
        ->assertSuccessful();

    $this->actingAs( $this->owner )
        ->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => UploadedFile::fake()->image( 'a.png' )] )
        ->assertSuccessful();
} );
