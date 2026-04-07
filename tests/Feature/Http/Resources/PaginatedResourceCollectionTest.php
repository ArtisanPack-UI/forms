<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormResource;
use ArtisanPackUI\Forms\Http\Resources\PaginatedResourceCollection;
use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Http\Request;

describe( 'PaginatedResourceCollection', function (): void {

    it( 'wraps paginated data with meta and links', function (): void {
        Form::factory()->count( 5 )->create();

        $paginator  = Form::query()->paginate( 2 );
        $collection = new PaginatedResourceCollection( $paginator, FormResource::class );

        $response = $collection->toResponse( Request::create( '/' ) );
        $data     = json_decode( $response->getContent(), true );

        expect( $data )
            ->toHaveKey( 'data' )
            ->toHaveKey( 'meta' )
            ->toHaveKey( 'links' )
            ->and( $data['data'] )->toHaveCount( 2 )
            ->and( $data['meta']['current_page'] )->toBe( 1 )
            ->and( $data['meta']['per_page'] )->toBe( 2 )
            ->and( $data['meta']['total'] )->toBe( 5 )
            ->and( $data['meta']['last_page'] )->toBe( 3 )
            ->and( $data['meta']['from'] )->toBe( 1 )
            ->and( $data['meta']['to'] )->toBe( 2 )
            ->and( $data['links'] )->toHaveKey( 'first' )
            ->and( $data['links'] )->toHaveKey( 'last' )
            ->and( $data['links'] )->toHaveKey( 'prev' )
            ->and( $data['links'] )->toHaveKey( 'next' );
    } );

    it( 'returns null prev link on first page', function (): void {
        Form::factory()->count( 3 )->create();

        $paginator  = Form::query()->paginate( 2 );
        $collection = new PaginatedResourceCollection( $paginator, FormResource::class );

        $response = $collection->toResponse( Request::create( '/' ) );
        $data     = json_decode( $response->getContent(), true );

        expect( $data['links']['prev'] )->toBeNull()
            ->and( $data['links']['next'] )->not->toBeNull();
    } );

    it( 'returns null next link on last page', function (): void {
        Form::factory()->count( 3 )->create();

        $paginator  = Form::query()->paginate( 2, ['*'], 'page', 2 );
        $collection = new PaginatedResourceCollection( $paginator, FormResource::class );

        $response = $collection->toResponse( Request::create( '/' ) );
        $data     = json_decode( $response->getContent(), true );

        expect( $data['links']['next'] )->toBeNull()
            ->and( $data['links']['prev'] )->not->toBeNull();
    } );

    it( 'transforms items using the specified resource class', function (): void {
        Form::factory()->create( ['name' => 'Test Form'] );

        $paginator  = Form::query()->paginate( 10 );
        $collection = new PaginatedResourceCollection( $paginator, FormResource::class );

        $response = $collection->toResponse( Request::create( '/' ) );
        $data     = json_decode( $response->getContent(), true );

        expect( $data['data'][0] )->toHaveKey( 'name', 'Test Form' )
            ->and( $data['data'][0] )->toHaveKey( 'slug' )
            ->and( $data['data'][0] )->toHaveKey( 'is_active' );
    } );

    it( 'handles empty paginated results', function (): void {
        $paginator  = Form::query()->paginate( 10 );
        $collection = new PaginatedResourceCollection( $paginator, FormResource::class );

        $response = $collection->toResponse( Request::create( '/' ) );
        $data     = json_decode( $response->getContent(), true );

        expect( $data['data'] )->toBeEmpty()
            ->and( $data['meta']['total'] )->toBe( 0 )
            ->and( $data['meta']['last_page'] )->toBe( 1 );
    } );
} );
