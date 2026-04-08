<?php

declare( strict_types=1 );

use Illuminate\Support\ServiceProvider;

describe( 'InstallFrontend Command', function (): void {
    describe( 'command registration', function (): void {
        it( 'is registered as an artisan command', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'react'] )
                ->assertSuccessful();
        } );
    } );

    describe( 'stack option', function (): void {
        it( 'accepts react as a valid stack', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'react'] )
                ->assertSuccessful();
        } );

        it( 'accepts vue as a valid stack', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'vue'] )
                ->assertSuccessful();
        } );

        it( 'rejects an invalid stack', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'angular'] )
                ->assertFailed();
        } );

        it( 'is case insensitive for stack names', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'React'] )
                ->assertSuccessful();
        } );

        it( 'prompts for stack when not provided', function (): void {
            $this->artisan( 'forms:install-frontend' )
                ->expectsChoice(
                    'Which frontend stack would you like to install?',
                    'react',
                    ['react', 'vue'],
                )
                ->assertSuccessful();
        } );
    } );

    describe( 'publishing react stack', function (): void {
        it( 'publishes the forms-react tag assets', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;

            expect( $publishGroups )->toHaveKey( 'forms-react' );

            $paths = $publishGroups['forms-react'];

            // Should publish react components
            $reactKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/react' ),
            );
            expect( $reactKeys )->not->toBeEmpty();

            // Should publish shared utilities
            $sharedKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/shared' ),
            );
            expect( $sharedKeys )->not->toBeEmpty();

            // Should publish type definitions
            $typeKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'artisanpack-forms.d.ts' ),
            );
            expect( $typeKeys )->not->toBeEmpty();
        } );

        it( 'outputs success message for react', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'react'] )
                ->expectsOutputToContain( 'react' )
                ->assertSuccessful();
        } );
    } );

    describe( 'publishing vue stack', function (): void {
        it( 'publishes the forms-vue tag assets', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;

            expect( $publishGroups )->toHaveKey( 'forms-vue' );

            $paths = $publishGroups['forms-vue'];

            // Should publish vue components
            $vueKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/vue' ),
            );
            expect( $vueKeys )->not->toBeEmpty();

            // Should publish shared utilities
            $sharedKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/shared' ),
            );
            expect( $sharedKeys )->not->toBeEmpty();

            // Should publish type definitions
            $typeKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'artisanpack-forms.d.ts' ),
            );
            expect( $typeKeys )->not->toBeEmpty();
        } );

        it( 'outputs success message for vue', function (): void {
            $this->artisan( 'forms:install-frontend', ['--stack' => 'vue'] )
                ->expectsOutputToContain( 'vue' )
                ->assertSuccessful();
        } );
    } );

    describe( 'force option', function (): void {
        it( 'accepts the force flag', function (): void {
            $this->artisan( 'forms:install-frontend', [
                '--stack' => 'react',
                '--force' => true,
            ] )->assertSuccessful();
        } );
    } );
} );
