<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Guards BE-8 (the page URL is the page the form was loaded on, not the
 * Livewire endpoint) and LW-11 (renderer messages are translatable).
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name'] );
} );

it( 'records the page the form was loaded on, not the Livewire update endpoint', function (): void {
    // The update request that runs submit() has another URL and no referrer
    $service = Mockery::mock( app( SubmissionService::class ) );
    $this->instance( SubmissionService::class, $service );
    $service->shouldReceive( 'getRequestMetadata' )->andReturn( [
        'page_url'     => 'https://example.com/livewire/update',
        'referrer_url' => null,
        'ip_address'   => '127.0.0.1',
        'user_agent'   => 'Test',
    ] );

    $component = Livewire::withHeaders( ['Referer' => 'https://search.example/'] )
        ->test( FormRenderer::class, ['form' => $this->form] );

    $pageUrl = $component->get( 'pageUrl' );

    expect( $pageUrl )->toBeString()->not->toBe( '' )
        ->and( $component->get( 'referrerUrl' ) )->toBe( 'https://search.example/' );

    $component->afterSpamDelay()
        ->set( 'formData.name', 'Ada' )
        ->call( 'submit' )
        ->assertSet( 'isSubmitted', true );

    $submission = $this->form->submissions()->first();

    expect( $submission->page_url )->toBe( $pageUrl )
        ->and( $submission->page_url )->not->toContain( '/livewire/update' )
        ->and( $submission->referrer_url )->toBe( 'https://search.example/' )
        ->and( $submission->ip_address )->toBe( '127.0.0.1' );
} );

it( 'locks the page and referrer URLs', function ( string $property ): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->set( $property, 'https://evil.example/' );
} )->with( ['pageUrl', 'referrerUrl'] )->throws( CannotUpdateLockedPropertyException::class );

it( 'translates the rate limit message', function (): void {
    $this->partialMock( SubmissionService::class, fn ( $mock ) => $mock->shouldReceive( 'isRateLimited' )->andReturn( true ) );

    app( 'translator' )->addLines( ['Too many submissions. Please try again later.' => 'Trop de soumissions.'], 'fr', '*' );
    app()->setLocale( 'fr' );

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->call( 'submit' )
        ->assertSet( 'errorMessage', 'Trop de soumissions.' )
        ->assertSee( 'Trop de soumissions.' );
} );

it( 'translates the submission failure message', function (): void {
    $this->partialMock( SubmissionService::class, fn ( $mock ) => $mock->shouldReceive( 'create' )->andThrow( new RuntimeException( 'boom' ) ) );

    app( 'translator' )->addLines( ['An error occurred while submitting the form. Please try again.' => 'Une erreur est survenue.'], 'fr', '*' );
    app()->setLocale( 'fr' );

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.name', 'Ada' )
        ->call( 'submit' )
        ->assertSet( 'errorMessage', 'Une erreur est survenue.' );
} );

it( 'shows the US placeholder only on phone fields without a country selector', function (): void {
    FormField::factory()->for( $this->form )->create( ['type' => 'phone', 'name' => 'us_phone', 'placeholder' => null, 'sort_order' => 5] );
    $international = FormField::factory()->for( $this->form )->create( ['type' => 'phone', 'name' => 'intl_phone', 'placeholder' => null, 'sort_order' => 6, 'field_config' => ['phone_format' => 'international']] );

    $html = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )->html();

    expect( $international->usesPhoneCountrySelector() )->toBeTrue()
        ->and( substr_count( $html, 'placeholder="(123) 456-7890"' ) )->toBe( 1 );
} );
