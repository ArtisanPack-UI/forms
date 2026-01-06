<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms;

use ArtisanPackUI\Forms\Console\Commands\PruneFormSubmissions;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Livewire\FormsList;
use ArtisanPackUI\Forms\Livewire\NotificationEditor;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\FieldService;
use ArtisanPackUI\Forms\Services\FormService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\StepService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Service provider for the Forms package.
 *
 * Bootstraps the Forms package by registering configuration, database
 * migrations, and the form-uploads disk. Configuration is merged into
 * the main artisanpack.php config file following the ArtisanPack UI
 * package conventions.
 *
 * @since   1.0.0
 */
class FormsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method merges the package's local forms configuration into a temporary key.
     * The `boot` method will then handle merging this into the main `artisanpack` config.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/forms.php',
            'artisanpack-forms-temp'
        );

        $this->app->singleton('forms', function ($app) {
            return new Forms;
        });

        $this->app->singleton(FormService::class, function ($app) {
            return new FormService;
        });

        $this->app->singleton(FieldService::class, function ($app) {
            return new FieldService;
        });

        $this->app->singleton(StepService::class, function ($app) {
            return new StepService;
        });

        $this->app->singleton(ConditionalLogicService::class, function ($app) {
            return new ConditionalLogicService;
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(ConditionalLogicService::class));
        });

        $this->app->singleton(SubmissionService::class, function ($app) {
            return new SubmissionService($app->make(NotificationService::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * This method publishes the configuration, merges it into the main `artisanpack`
     * config array, and loads database migrations.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $this->mergeConfiguration();
        $this->registerFilesystemDisk();
        $this->registerCommands();
        $this->publishConfiguration();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'forms');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->registerLivewireComponents();
        $this->publishViews();
    }

    /**
     * Merges the package's default configuration with the user's customizations.
     *
     * This method ensures that the user's settings under the 'forms' key
     * in `config/artisanpack.php` take precedence over the package's default values.
     *
     * @since 1.0.0
     */
    protected function mergeConfiguration(): void
    {
        $packageDefaults = config('artisanpack-forms-temp', []);
        $userConfig = config('artisanpack.forms', []);
        $mergedConfig = array_replace_recursive($packageDefaults, $userConfig);
        config(['artisanpack.forms' => $mergedConfig]);
    }

    /**
     * Registers the form-uploads filesystem disk.
     *
     * Merges the form-uploads disk configuration into the filesystems config
     * if it hasn't already been defined by the user.
     *
     * @since 1.0.0
     */
    protected function registerFilesystemDisk(): void
    {
        $diskConfig = config('artisanpack.forms.disk_config', []);

        foreach ($diskConfig as $diskName => $diskSettings) {
            // Only add the disk if it doesn't already exist
            if (config("filesystems.disks.{$diskName}") === null) {
                config(["filesystems.disks.{$diskName}" => $diskSettings]);
            }
        }
    }

    /**
     * Register the package's artisan commands.
     *
     * @since 1.0.0
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneFormSubmissions::class,
            ]);
        }
    }

    /**
     * Publish the configuration file to the application's config directory.
     *
     * Configuration will be published to config/artisanpack/forms.php to maintain
     * the unified ArtisanPack UI configuration structure.
     *
     * @since 1.0.0
     */
    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/forms.php' => config_path('artisanpack/forms.php'),
            ], 'artisanpack-package-config');
        }
    }

    /**
     * Register the package's Livewire components.
     *
     * @since 1.0.0
     */
    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('forms-list', FormsList::class);
            Livewire::component('form-builder', FormBuilder::class);
            Livewire::component('form-renderer', FormRenderer::class);
            Livewire::component('notification-editor', NotificationEditor::class);
        }
    }

    /**
     * Publish the package's views.
     *
     * @since 1.0.0
     */
    protected function publishViews(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/forms'),
            ], 'artisanpack-forms-views');
        }
    }
}
