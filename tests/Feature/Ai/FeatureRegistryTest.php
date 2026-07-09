<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Forms\Ai\Agents\ResponseClassificationAgent;
use ArtisanPackUI\Forms\Ai\Agents\SmartFieldValidationAgent;
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;
use ArtisanPackUI\Forms\Ai\Agents\SubmissionSummaryAgent;
use ArtisanPackUI\Forms\FormsServiceProvider;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
    $this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'registers all four Forms AI features on the FormsServiceProvider', function (): void {
    $provider = $this->app->make( FormsServiceProvider::class, ['app' => $this->app] );

    $features = $provider->aiFeatures();

    expect( array_keys( $features ) )->toBe( [
        'forms.spam_detection',
        'forms.submission_summary',
        'forms.response_classification',
        'forms.smart_validation',
    ] );

    expect( $features['forms.spam_detection']['agent'] )->toBe( SpamDetectionAgent::class );
    expect( $features['forms.submission_summary']['agent'] )->toBe( SubmissionSummaryAgent::class );
    expect( $features['forms.response_classification']['agent'] )->toBe( ResponseClassificationAgent::class );
    expect( $features['forms.smart_validation']['agent'] )->toBe( SmartFieldValidationAgent::class );

    foreach ( $features as $definition ) {
        expect( $definition['package'] )->toBe( 'artisanpack-ui/forms' );
    }
} );

it( 'returns an empty aiFeatures list when the AI package is not available', function (): void {
    // aiFeatures() gates on FormsServiceProvider::aiPackageAvailable(), which
    // is a static class_exists() probe. Anonymize a subclass that overrides
    // the probe to return false so we can exercise the guarded path without
    // touching the container-wide class-loader state.
    $provider = new class( $this->app ) extends FormsServiceProvider {
        public static function aiPackageAvailable(): bool
        {
            return false;
        }
    };

    expect( $provider->aiFeatures() )->toBe( [] );
} );

it( 'refuses to run the spam agent when the feature toggle is off', function (): void {
    $registry = $this->app->make( FeatureRegistry::class );
    $registry->register(
        'forms.spam_detection',
        SpamDetectionAgent::class,
        ['package' => 'artisanpack-ui/forms'],
    );
    $registry->disable( 'forms.spam_detection' );

    expect( fn () => SpamDetectionAgent::for( [
        'fields' => ['message' => 'hi'],
    ] )->run() )
        ->toThrow( FeatureDisabledException::class );
} );

it( 'refuses to run the submission summary agent when the feature toggle is off', function (): void {
    $registry = $this->app->make( FeatureRegistry::class );
    $registry->register(
        'forms.submission_summary',
        SubmissionSummaryAgent::class,
        ['package' => 'artisanpack-ui/forms'],
    );
    $registry->disable( 'forms.submission_summary' );

    expect( fn () => SubmissionSummaryAgent::for( [
        'form_name'   => 'Contact us',
        'submissions' => [],
    ] )->run() )
        ->toThrow( FeatureDisabledException::class );
} );

it( 'refuses to run the classifier agent when the feature toggle is off', function (): void {
    $registry = $this->app->make( FeatureRegistry::class );
    $registry->register(
        'forms.response_classification',
        ResponseClassificationAgent::class,
        ['package' => 'artisanpack-ui/forms'],
    );
    $registry->disable( 'forms.response_classification' );

    expect( fn () => ResponseClassificationAgent::for( [
        'fields'               => ['message' => 'hi'],
        'available_categories' => ['support-request'],
    ] )->run() )
        ->toThrow( FeatureDisabledException::class );
} );

it( 'refuses to run the smart validator agent when the feature toggle is off', function (): void {
    $registry = $this->app->make( FeatureRegistry::class );
    $registry->register(
        'forms.smart_validation',
        SmartFieldValidationAgent::class,
        ['package' => 'artisanpack-ui/forms'],
    );
    $registry->disable( 'forms.smart_validation' );

    expect( fn () => SmartFieldValidationAgent::for( [
        'field_label' => 'Company',
        'field_kind'  => 'company_name',
        'value'       => 'Anthropic',
    ] )->run() )
        ->toThrow( FeatureDisabledException::class );
} );
