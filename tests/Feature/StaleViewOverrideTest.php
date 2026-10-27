<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FormsServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Guards REL-01: a published 1.5 renderer view, which writes to the now
 * locked `hiddenFields`, is reported with the command that fixes it.
 */
beforeEach( function (): void {
    $this->path = resource_path( 'views/vendor/forms/livewire/form-renderer.blade.php' );
    $this->warn = fn (): bool => ( new ReflectionMethod( FormsServiceProvider::class, 'warnAboutStalePublishedViews' ) )
        ->invoke( $this->app->getProvider( FormsServiceProvider::class ) );
} );

afterEach( function (): void {
    File::deleteDirectory( resource_path( 'views/vendor/forms' ) );
} );

it( 'warns about a published view that writes to hiddenFields', function (): void {
    File::ensureDirectoryExists( dirname( $this->path ) );
    File::put( $this->path, "<div x-init=\"this.\$wire.set('hiddenFields', newHiddenFields)\"></div>" );

    Log::spy();

    expect( ( $this->warn )() )->toBeTrue();

    Log::shouldHaveReceived( 'warning' )->once()->withArgs(
        fn ( string $message, array $context ): bool => str_contains( $message, 'vendor:publish --tag=forms-views --force' ) && $this->path === $context['path'],
    );
} );

it( 'stays quiet for a current published view or none', function (): void {
    Log::spy();

    expect( ( $this->warn )() )->toBeFalse();

    File::ensureDirectoryExists( dirname( $this->path ) );
    File::put( $this->path, '<div wire:model="formData.name"></div>' );

    expect( ( $this->warn )() )->toBeFalse();

    Log::shouldNotHaveReceived( 'warning' );
} );
