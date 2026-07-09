<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the shaped verdict when the prompter responds', function (): void {
    $this->prompter->queue( [
        'spam_score' => 82,
        'verdict'    => 'spam',
        'reasons'    => ['message body is a marketing pitch unrelated to the form purpose'],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['name' => 'Bob', 'message' => 'Buy cheap watches now!'],
        'meta'   => ['ip_country' => 'RU', 'submission_speed_ms' => 400],
    ] )->run();

    expect( $result['spam_score'] )->toBe( 82 );
    expect( $result['verdict'] )->toBe( 'spam' );
    expect( $result['reasons'] )->toHaveCount( 1 );
} );

it( 'clamps out-of-range scores back into [0, 100]', function (): void {
    $this->prompter->queue( [
        'spam_score' => 250,
        'verdict'    => 'spam',
        'reasons'    => [],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['message' => 'hi'],
    ] )->run();

    expect( $result['spam_score'] )->toBe( 100 );
} );

it( 'derives a verdict from the score when the model returns an unknown label', function (): void {
    $this->prompter->queue( [
        'spam_score' => 20,
        'verdict'    => 'nope',
        'reasons'    => [],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['message' => 'hello there'],
    ] )->run();

    expect( $result['verdict'] )->toBe( 'ham' );
} );

it( 'trims the reasons list to five entries', function (): void {
    $this->prompter->queue( [
        'spam_score' => 60,
        'verdict'    => 'suspicious',
        'reasons'    => ['one', 'two', 'three', 'four', 'five', 'six', 'seven'],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['message' => 'hey'],
    ] )->run();

    expect( $result['reasons'] )->toHaveCount( 5 );
} );

it( 'drops non-string reasons and blank entries', function (): void {
    $this->prompter->queue( [
        'spam_score' => 50,
        'verdict'    => 'suspicious',
        'reasons'    => ['valid reason', '', '   ', 123, ['nested'], 'another valid one'],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['message' => 'hey'],
    ] )->run();

    expect( $result['reasons'] )->toBe( ['valid reason', 'another valid one'] );
} );

it( 'raises FeatureError when fields is missing or empty', function (): void {
    expect( fn () => SpamDetectionAgent::for( [] )->run() )
        ->toThrow( FeatureError::class );

    expect( fn () => SpamDetectionAgent::for( ['fields' => []] )->run() )
        ->toThrow( FeatureError::class );
} );

it( 'synthesizes a fallback reason when a non-ham verdict has no reasons', function (): void {
    $this->prompter->queue( [
        'spam_score' => 88,
        'verdict'    => 'spam',
        'reasons'    => [],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['message' => 'unclear'],
    ] )->run();

    expect( $result['verdict'] )->toBe( 'spam' );
    expect( $result['reasons'] )->toHaveCount( 1 );
} );

it( 'does not synthesize reasons when the verdict is ham', function (): void {
    $this->prompter->queue( [
        'spam_score' => 5,
        'verdict'    => 'ham',
        'reasons'    => [],
    ] );

    $result = SpamDetectionAgent::for( [
        'fields' => ['message' => 'legitimate customer message'],
    ] )->run();

    expect( $result['reasons'] )->toBe( [] );
} );

it( 'includes the submission metadata in the prompter message when supplied', function (): void {
    $this->prompter->queue( [
        'spam_score' => 5,
        'verdict'    => 'ham',
        'reasons'    => [],
    ] );

    SpamDetectionAgent::for( [
        'fields' => ['message' => 'legit customer question'],
        'meta'   => ['ip_country' => 'US', 'submission_speed_ms' => 4500],
    ] )->run();

    $parts = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' );
    expect( $parts->contains( fn ( string $text ): bool => str_contains( $text, 'ip_country' ) ) )
        ->toBeTrue();
    expect( $parts->contains( fn ( string $text ): bool => str_contains( $text, 'submission_speed_ms' ) ) )
        ->toBeTrue();
});
