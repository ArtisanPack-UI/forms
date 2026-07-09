<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Forms\Ai\Agents\ResponseClassificationAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the shaped category when the prompter responds', function (): void {
    $this->prompter->queue( [
        'category'   => 'support-request',
        'confidence' => 0.9,
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'my login is broken'],
        'available_categories' => ['support-request', 'sales-inquiry', 'feedback'],
    ] )->run();

    expect( $result['category'] )->toBe( 'support-request' );
    expect( $result['confidence'] )->toBe( 0.9 );
    expect( $result )->not->toHaveKey( 'suggested_new' );
} );

it( 'falls back to the first available category when the model returns an unknown label', function (): void {
    $this->prompter->queue( [
        'category'   => 'made-up',
        'confidence' => 0.8,
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'hi'],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ] )->run();

    expect( $result['category'] )->toBe( 'support-request' );
    expect( $result['confidence'] )->toBeLessThanOrEqual( 0.2 );
} );

it( 'clamps confidence to the [0, 1] interval', function (): void {
    $this->prompter->queue( [
        'category'   => 'support-request',
        'confidence' => 2.5,
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'hi'],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ] )->run();

    expect( $result['confidence'] )->toBe( 1.0 );
} );

it( 'accepts a suggested_new only below the confidence threshold', function (): void {
    $this->prompter->queue( [
        'category'      => 'support-request',
        'confidence'    => 0.3,
        'suggested_new' => 'partnership-request',
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'we want to co-brand something'],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ] )->run();

    expect( $result )->toHaveKey( 'suggested_new' );
    expect( $result['suggested_new'] )->toBe( 'partnership-request' );
} );

it( 'strips suggested_new when confidence is at or above the threshold', function (): void {
    $this->prompter->queue( [
        'category'      => 'support-request',
        'confidence'    => 0.7,
        'suggested_new' => 'partnership-request',
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'login broken'],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ] )->run();

    expect( $result )->not->toHaveKey( 'suggested_new' );
} );

it( 'strips suggested_new when it duplicates an existing category', function (): void {
    $this->prompter->queue( [
        'category'      => 'support-request',
        'confidence'    => 0.3,
        'suggested_new' => 'Sales Inquiry',
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'hi'],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ] )->run();

    expect( $result )->not->toHaveKey( 'suggested_new' );
} );

it( 'normalizes a proposed new-category label into a kebab-case slug', function (): void {
    $this->prompter->queue( [
        'category'      => 'support-request',
        'confidence'    => 0.2,
        'suggested_new' => 'Partnership Opportunities!',
    ] );

    $result = ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'hi'],
        'available_categories' => ['support-request', 'sales-inquiry'],
    ] )->run();

    expect( $result['suggested_new'] )->toBe( 'partnership-opportunities' );
} );

it( 'raises FeatureError when available_categories is empty', function (): void {
    expect( fn () => ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'hi'],
        'available_categories' => [],
    ] )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'raises FeatureError when fields is missing', function (): void {
    expect( fn () => ResponseClassificationAgent::for( [
        'available_categories' => ['support-request'],
    ] )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'dedupes available_categories in the prompter message', function (): void {
    $this->prompter->queue( [
        'category'   => 'support-request',
        'confidence' => 0.9,
    ] );

    ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'help'],
        'available_categories' => ['support-request', 'support-request', 'sales-inquiry'],
    ] )->run();

    $parts          = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' );
    $categoriesLine = $parts->first( fn ( string $text ): bool => str_starts_with( $text, 'Available categories:' ) );
    expect( substr_count( (string) $categoriesLine, 'support-request' ) )->toBe( 1 );
});
