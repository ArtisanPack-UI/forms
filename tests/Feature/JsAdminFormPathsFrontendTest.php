<?php

declare( strict_types=1 );

/**
 * Guards VU-10 and VU-02: the API binds `{form}` by slug, so the React and
 * Vue admins build every form API path from the slug, never the ID.
 */

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Reads a JS source file.
 */
function jsAdminSource( string $path ): string
{
    return (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );
}

it( 'never builds an admin API path from the form ID', function ( string $path ): void {
    expect( jsAdminSource( $path ) )
        ->not->toMatch( '/`\/\$\{\s*(props\.)?form(\.value)?\??\.id\s*\}/' );
} )->with( [
    'react/components/admin/FormBuilder.tsx',
    'react/components/admin/SubmissionDetail.tsx',
    'react/components/admin/SubmissionsList.tsx',
    'react/components/admin/NotificationEditor.tsx',
    'vue/components/admin/FormBuilder.vue',
    'vue/components/admin/SubmissionDetail.vue',
    'vue/components/admin/SubmissionsList.vue',
    'vue/components/admin/NotificationEditor.vue',
] );

it( 'builds the React builder paths from the saved slug', function (): void {
    expect( jsAdminSource( 'react/components/admin/FormBuilder.tsx' ) )
        ->toContain( 'const formPath = useCallback( ( path = \'\' ): string => `/${encodeURIComponent( persistedSlugRef.current )}${path}`, [] );' )
        ->toContain( 'persistedSlugRef.current = formData.slug ?? \'\';' )
        ->toContain( "formPath( '/fields/choice-images' )" )
        ->toContain( 'put<{ data: Form }>( formPath(), data )' );
} );

it( 'builds the React submission paths from the form slug', function ( string $path ): void {
    expect( jsAdminSource( $path ) )->toContain( '`/${encodeURIComponent( form.slug )}/submissions' );
} )->with( ['react/components/admin/SubmissionDetail.tsx', 'react/components/admin/SubmissionsList.tsx'] );

it( 'uploads Vue image choices to the slug path', function (): void {
    expect( jsAdminSource( 'vue/components/admin/FormBuilder.vue' ) )
        ->toContain( '`/${encodeURIComponent( persistedSlug.value )}/fields/choice-images`' )
        ->not->toContain( 'form.value?.id}/fields/choice-images' );
} );

it( 'binds the form API by slug, as the admins call it', function (): void {
    Storage::fake( 'public' );

    $form = Form::factory()->create( ['slug' => 'contact-us'] );
    $user = ( new class extends User {
        protected $table = 'users';

        protected $fillable = ['name', 'email', 'password'];
    } )->create( ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt( 'password' )] );

    $image = fn (): UploadedFile => UploadedFile::fake()->image( 'shirt.png', 40, 40 );

    $this->actingAs( $user )
        ->postJson( '/api/v1/forms/' . $form->id . '/fields/choice-images', ['image' => $image()] )
        ->assertNotFound();

    $bySlug = $this->actingAs( $user )
        ->postJson( '/api/v1/forms/' . $form->slug . '/fields/choice-images', ['image' => $image()] );

    expect( $bySlug->status() )->not->toBe( 404 );
} );
