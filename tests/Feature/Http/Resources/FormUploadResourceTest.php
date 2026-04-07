<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormUploadResource;
use ArtisanPackUI\Forms\Models\FormUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

describe( 'FormUploadResource', function (): void {

    it( 'transforms an upload into an array', function (): void {
        Storage::fake( 'form-uploads' );

        $upload = FormUpload::factory()->pdf()->create( [
            'original_name' => 'document.pdf',
            'size'          => 51200,
        ] );

        $resource = ( new FormUploadResource( $upload ) )->toArray( Request::create( '/' ) );

        expect( $resource )
            ->toHaveKey( 'id', $upload->id )
            ->toHaveKey( 'submission_id', $upload->submission_id )
            ->toHaveKey( 'field_id' )
            ->toHaveKey( 'original_name', 'document.pdf' )
            ->toHaveKey( 'mime_type', 'application/pdf' )
            ->toHaveKey( 'size', 51200 )
            ->toHaveKey( 'human_size' )
            ->toHaveKey( 'is_image', false )
            ->toHaveKey( 'url' )
            ->toHaveKey( 'created_at' )
            ->toHaveKey( 'updated_at' );
    } );

    it( 'identifies images correctly', function (): void {
        Storage::fake( 'form-uploads' );

        $upload = FormUpload::factory()->image()->create();

        $resource = ( new FormUploadResource( $upload ) )->toArray( Request::create( '/' ) );

        expect( $resource['is_image'] )->toBeTrue();
    } );

    it( 'provides human-readable file size', function (): void {
        Storage::fake( 'form-uploads' );

        $upload = FormUpload::factory()->size( 1048576 )->create();

        $resource = ( new FormUploadResource( $upload ) )->toArray( Request::create( '/' ) );

        expect( $resource['human_size'] )->toBe( '1024 KB' );
    } );

    it( 'includes url for file access', function (): void {
        Storage::fake( 'form-uploads' );

        $upload = FormUpload::factory()->create();

        $resource = ( new FormUploadResource( $upload ) )->toArray( Request::create( '/' ) );

        $expectedUrl = Storage::disk( $upload->disk )->url( $upload->path );

        expect( $resource['url'] )->toBe( $expectedUrl );
    } );
} );
