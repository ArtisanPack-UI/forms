<?php

declare(strict_types=1);

namespace Tests;

use ArtisanPackUI\Forms\FormsServiceProvider;
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
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders($app): array
    {
        return [
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
    protected function defineEnvironment($app): void
    {
        // Setup app key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Setup filesystem
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);
    }

    /**
     * Defines database migrations.
     *
     * @since 1.0.0
     */
    protected function defineDatabaseMigrations(): void
    {
        // Load testing migrations (users table - only for tests)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/testing');

        // Load main package migrations (forms tables - will run in consuming apps)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
