<?php

/**
 * Forms service provider.
 *
 * Bootstraps the Forms package by registering configuration, database migrations,
 * Livewire components, event listeners, and authorization policies.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms;

use ArtisanPackUI\Forms\Console\Commands\PruneFormSubmissions;
use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Listeners\SendWebhookOnSubmission;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Livewire\FormsList;
use ArtisanPackUI\Forms\Livewire\NotificationEditor;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Livewire\SubmissionsList;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\FieldService;
use ArtisanPackUI\Forms\Services\FormService;
use ArtisanPackUI\Forms\Services\IntegrationService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\StepService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use RuntimeException;

/**
 * Service provider for the Forms package.
 *
 * Bootstraps the Forms package by registering configuration, database
 * migrations, and the form-uploads disk. Configuration is merged into
 * the main artisanpack.php config file following the ArtisanPack UI
 * package conventions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormsServiceProvider extends ServiceProvider
{
    /**
     * Registers any application services.
     *
     * Merges the package's local forms configuration into a temporary key.
     * The `boot` method will then handle merging this into the main `artisanpack` config.
     * Also registers all service classes as singletons in the container.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/forms.php',
            'artisanpack-forms-temp',
        );

        $this->app->singleton( 'forms', function ( $app ) {
            return new Forms;
        } );

        $this->app->singleton( FormService::class, function ( $app ) {
            return new FormService;
        } );

        $this->app->singleton( FieldService::class, function ( $app ) {
            return new FieldService;
        } );

        $this->app->singleton( StepService::class, function ( $app ) {
            return new StepService;
        } );

        $this->app->singleton( ConditionalLogicService::class, function ( $app ) {
            return new ConditionalLogicService;
        } );

        $this->app->singleton( NotificationService::class, function ( $app ) {
            return new NotificationService( $app->make( ConditionalLogicService::class ) );
        } );

        $this->app->singleton( SubmissionService::class, function ( $app ) {
            return new SubmissionService( $app->make( NotificationService::class ) );
        } );

        $this->app->singleton( ExportService::class, function ( $app ) {
            return new ExportService;
        } );

        $this->app->singleton( IntegrationService::class, function ( $app ) {
            return new IntegrationService;
        } );
    }

    /**
     * Bootstraps any application services.
     *
     * Publishes the configuration, merges it into the main `artisanpack`
     * config array, loads database migrations, views, and routes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        $this->mergeConfiguration();
        $this->validateUserModelConfiguration();
        $this->registerFilesystemDisk();
        $this->registerCommands();
        $this->registerEventListeners();
        $this->registerPolicies();
        $this->publishConfiguration();
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
        $this->loadViewsFrom( __DIR__ . '/../resources/views', 'forms' );
        $this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
        $this->registerLivewireComponents();
        $this->publishViews();
    }

    /**
     * Merges the package's default configuration with the user's customizations.
     *
     * Ensures that the user's settings under the 'forms' key in `config/artisanpack.php`
     * take precedence over the package's default values.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function mergeConfiguration(): void
    {
        $packageDefaults = config( 'artisanpack-forms-temp', [] );
        $userConfig      = config( 'artisanpack.forms', [] );
        $mergedConfig    = array_replace_recursive( $packageDefaults, $userConfig );
        config( ['artisanpack.forms' => $mergedConfig] );
    }

    /**
     * Validates the user model configuration.
     *
     * Ensures the configured user model class exists and extends Eloquent Model.
     * This validation only runs when ownership restriction is enabled to avoid
     * breaking applications that don't use the ownership feature.
     *
     * @since 1.0.0
     *
     * @throws RuntimeException If the user model class is invalid.
     *
     * @return void
     */
    protected function validateUserModelConfiguration(): void
    {
        // Only validate if ownership restriction is enabled
        if ( ! config( 'artisanpack.forms.authorization.restrict_by_owner', false ) ) {
            return;
        }

        $userModel = config( 'artisanpack.forms.authorization.user_model', 'App\\Models\\User' );

        if ( ! class_exists( $userModel ) ) {
            throw new RuntimeException(
                "The configured user model class '{$userModel}' does not exist. " .
                'Please set a valid class in FORMS_USER_MODEL environment variable or ' .
                'artisanpack.forms.authorization.user_model configuration.',
            );
        }

        if ( ! is_subclass_of( $userModel, Model::class ) ) {
            throw new RuntimeException(
                "The configured user model class '{$userModel}' must extend " .
                'Illuminate\\Database\\Eloquent\\Model.',
            );
        }
    }

    /**
     * Registers the form-uploads filesystem disk.
     *
     * Merges the form-uploads disk configuration into the filesystems config
     * if it hasn't already been defined by the user.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerFilesystemDisk(): void
    {
        $diskConfig = config( 'artisanpack.forms.disk_config', [] );

        foreach ( $diskConfig as $diskName => $diskSettings ) {
            // Only add the disk if it doesn't already exist
            if ( null === config( "filesystems.disks.{$diskName}" ) ) {
                config( ["filesystems.disks.{$diskName}" => $diskSettings] );
            }
        }
    }

    /**
     * Registers the package's artisan commands.
     *
     * Commands are only registered when running in the console environment.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                PruneFormSubmissions::class,
            ] );
        }
    }

    /**
     * Registers the package's event listeners.
     *
     * Sets up listeners for form-related events such as submission webhooks.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        Event::listen( FormSubmitted::class, SendWebhookOnSubmission::class );
    }

    /**
     * Registers the package's authorization policies.
     *
     * Maps model classes to their corresponding policy classes for
     * Laravel's Gate authorization system.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerPolicies(): void
    {
        Gate::policy( Models\Form::class, Policies\FormPolicy::class );
        Gate::policy( Models\FormSubmission::class, Policies\SubmissionPolicy::class );
    }

    /**
     * Publishes the configuration file to the application's config directory.
     *
     * Configuration will be published to config/artisanpack/forms.php to maintain
     * the unified ArtisanPack UI configuration structure.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function publishConfiguration(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../config/forms.php' => config_path( 'artisanpack/forms.php' ),
            ], 'artisanpack-package-config' );
        }
    }

    /**
     * Registers the package's Livewire components.
     *
     * Components are only registered if Livewire is available in the application.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        if ( class_exists( Livewire::class ) ) {
            Livewire::component( 'forms-list', FormsList::class );
            Livewire::component( 'form-builder', FormBuilder::class );
            Livewire::component( 'form-renderer', FormRenderer::class );
            Livewire::component( 'notification-editor', NotificationEditor::class );
            Livewire::component( 'submissions-list', SubmissionsList::class );
            Livewire::component( 'submission-detail', SubmissionDetail::class );
        }
    }

    /**
     * Publishes the package's views.
     *
     * Views are published to resources/views/vendor/forms for customization.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function publishViews(): void
    {
        if ( $this->app->runningInConsole() ) {
            $this->publishes( [
                __DIR__ . '/../resources/views' => resource_path( 'views/vendor/forms' ),
            ], 'artisanpack-forms-views');
        }
    }
}
