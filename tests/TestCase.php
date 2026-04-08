<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Forms\FormsServiceProvider;
use Illuminate\Support\Facades\Blade;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base Test Case
 *
 * Provides base functionality for all package tests.
 *
 * @since   1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Register stub components for testing (replaces artisanpack-ui/livewire-ui-components)
        Blade::component( 'artisanpack-icon', Stubs\IconStub::class );
        Blade::component( 'artisanpack-button', Stubs\ButtonStub::class );
        Blade::component( 'artisanpack-input', Stubs\InputStub::class );
        Blade::component( 'artisanpack-textarea', Stubs\TextareaStub::class );
        Blade::component( 'artisanpack-select', Stubs\SelectStub::class );
        Blade::component( 'artisanpack-badge', Stubs\BadgeStub::class );
        Blade::component( 'artisanpack-alert', Stubs\AlertStub::class );
        Blade::component( 'artisanpack-card', Stubs\CardStub::class );
        Blade::component( 'artisanpack-checkbox', Stubs\CheckboxStub::class );
        Blade::component( 'artisanpack-collapse', Stubs\CollapseStub::class );
        Blade::component( 'artisanpack-dropdown', Stubs\DropdownStub::class );
        Blade::component( 'artisanpack-form', Stubs\FormStub::class );
        Blade::component( 'artisanpack-menu', Stubs\MenuStub::class );
        Blade::component( 'artisanpack-menu-item', Stubs\MenuItemStub::class );
        Blade::component( 'artisanpack-tabs', Stubs\TabsStub::class );
        Blade::component( 'artisanpack-tab', Stubs\TabStub::class );
        Blade::component( 'artisanpack-toggle', Stubs\ToggleStub::class );
        Blade::component( 'artisanpack-breadcrumbs', Stubs\BreadcrumbsStub::class );
    }

    /**
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     *
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            LivewireServiceProvider::class,
            FormsServiceProvider::class,
        ];
    }

    /**
     * Defines environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        // Setup app key for encryption
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

        // Setup default database to use sqlite :memory:
        $app['config']->set( 'database.default', 'testbench' );
        $app['config']->set( 'database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ] );

        // Override API middleware for testing (Sanctum not available in package tests)
        $app['config']->set( 'artisanpack.forms.api.middleware', ['api', 'auth'] );

        // Setup filesystem
        $app['config']->set( 'filesystems.default', 'local' );
        $app['config']->set( 'filesystems.disks.local', [
            'driver' => 'local',
            'root'   => storage_path( 'app' ),
        ] );

        // Add test stub views path
        $app['config']->set( 'view.paths', array_merge(
            $app['config']->get( 'view.paths', [] ),
            [__DIR__ . '/views'],
        ) );
    }

    /**
     * Defines routes for testing.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Routing\Router  $router  The router instance.
     */
    protected function defineRoutes( $router ): void
    {
        // Define a stub login route for authentication testing
        $router->get( '/login', fn () => 'Login Page' )->name( 'login' );
    }

    /**
     * Defines database migrations.
     *
     * @since 1.0.0
     */
    protected function defineDatabaseMigrations(): void
    {
        // Load testing migrations (users table - only for tests)
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations/testing' );

        // Load main package migrations (forms tables - will run in consuming apps)
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
    }
}
