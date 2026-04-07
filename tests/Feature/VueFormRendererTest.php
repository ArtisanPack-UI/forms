<?php

declare( strict_types=1 );

use Illuminate\Support\ServiceProvider;

describe( 'Vue Form Renderer', function (): void {
    describe( 'publishing', function (): void {
        it( 'registers forms-vue as a publishable tag', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;

            expect( $publishGroups )->toHaveKey( 'forms-vue' );
        } );

        it( 'maps the source vue directory to the correct destination', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;
            $paths         = $publishGroups['forms-vue'] ?? [];

            $matchingKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/vue' ),
            );

            expect( $matchingKeys )->not->toBeEmpty();

            $sourcePath  = array_values( $matchingKeys )[0];
            $destination = $paths[ $sourcePath ];
            expect( $destination )->toContain( 'js/vendor/artisanpack-forms/vue' );
        } );

        it( 'publishes the shared utilities alongside vue components', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;
            $paths         = $publishGroups['forms-vue'] ?? [];

            $matchingKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/shared' ),
            );

            expect( $matchingKeys )->not->toBeEmpty();

            $sourcePath  = array_values( $matchingKeys )[0];
            $destination = $paths[ $sourcePath ];
            expect( $destination )->toContain( 'js/vendor/artisanpack-forms/shared' );
        } );
    } );

    describe( 'file structure', function (): void {
        it( 'has the main index entry point', function (): void {
            $path = __DIR__ . '/../../resources/js/vue/index.ts';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the useForm composable', function (): void {
            $path = __DIR__ . '/../../resources/js/vue/composables/useForm.ts';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the FormRenderer component', function (): void {
            $path = __DIR__ . '/../../resources/js/vue/components/FormRenderer.vue';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the MultiStepForm component', function (): void {
            $path = __DIR__ . '/../../resources/js/vue/components/MultiStepForm.vue';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the FieldRenderer component', function (): void {
            $path = __DIR__ . '/../../resources/js/vue/components/fields/FieldRenderer.vue';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has field type components for all categories', function (): void {
            $fieldsDir = __DIR__ . '/../../resources/js/vue/components/fields/';

            expect( file_exists( $fieldsDir . 'TextField.vue' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'ChoiceField.vue' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'AdvancedField.vue' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'LayoutField.vue' ) )->toBeTrue();
        } );

        it( 'shares conditional logic and validation with React renderer', function (): void {
            $sharedDir = __DIR__ . '/../../resources/js/shared/';

            expect( file_exists( $sharedDir . 'conditionalLogic.ts' ) )->toBeTrue()
                ->and( file_exists( $sharedDir . 'validation.ts' ) )->toBeTrue();
        } );
    } );

    describe( 'index exports', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/vue/index.ts',
            );
        } );

        it( 'exports the FormRenderer component', function (): void {
            expect( $this->content )->toContain( 'FormRenderer' );
        } );

        it( 'exports the MultiStepForm component', function (): void {
            expect( $this->content )->toContain( 'MultiStepForm' );
        } );

        it( 'exports the FieldRenderer component', function (): void {
            expect( $this->content )->toContain( 'FieldRenderer' );
        } );

        it( 'exports the useForm composable', function (): void {
            expect( $this->content )->toContain( 'useForm' );
        } );

        it( 'exports field components', function (): void {
            expect( $this->content )->toContain( 'TextField' )
                ->and( $this->content )->toContain( 'ChoiceField' )
                ->and( $this->content )->toContain( 'AdvancedField' )
                ->and( $this->content )->toContain( 'LayoutField' );
        } );

        it( 're-exports shared conditional logic utilities', function (): void {
            expect( $this->content )->toContain( 'compareValues' )
                ->and( $this->content )->toContain( 'evaluateFieldVisibility' )
                ->and( $this->content )->toContain( 'getHiddenFields' );
        } );

        it( 're-exports shared validation utilities', function (): void {
            expect( $this->content )->toContain( 'validateField' )
                ->and( $this->content )->toContain( 'validateFields' );
        } );

        it( 'imports from shared directory not a local utils directory', function (): void {
            expect( $this->content )->toContain( '../shared/conditionalLogic' )
                ->and( $this->content )->toContain( '../shared/validation' );
        } );
    } );

    describe( 'useForm composable content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/vue/composables/useForm.ts',
            );
        } );

        it( 'uses Vue reactivity primitives', function (): void {
            expect( $this->content )->toContain( 'computed' )
                ->and( $this->content )->toContain( 'onMounted' )
                ->and( $this->content )->toContain( 'ref' )
                ->and( $this->content )->toContain( "from 'vue'" );
        } );

        it( 'imports shared conditional logic and validation', function (): void {
            expect( $this->content )->toContain( '../../shared/conditionalLogic' )
                ->and( $this->content )->toContain( '../../shared/validation' );
        } );

        it( 'fetches form definition from the render API', function (): void {
            expect( $this->content )->toContain( '/render' );
        } );

        it( 'submits to the submit API endpoint', function (): void {
            expect( $this->content )->toContain( '/submit' );
        } );

        it( 'manages multi-step navigation', function (): void {
            expect( $this->content )->toContain( 'nextStep' )
                ->and( $this->content )->toContain( 'prevStep' )
                ->and( $this->content )->toContain( 'goToStep' )
                ->and( $this->content )->toContain( 'currentStep' );
        } );

        it( 'manages honeypot spam protection', function (): void {
            expect( $this->content )->toContain( 'honeypot' )
                ->and( $this->content )->toContain( '_form_loaded_at' );
        } );

        it( 'handles file uploads via FormData', function (): void {
            expect( $this->content )->toContain( 'FormData' )
                ->and( $this->content )->toContain( 'files' );
        } );

        it( 'handles server validation errors with step navigation', function (): void {
            expect( $this->content )->toContain( '422' )
                ->and( $this->content )->toContain( 'serverErrors' );
        } );

        it( 'handles rate limiting responses', function (): void {
            expect( $this->content )->toContain( '429' )
                ->and( $this->content )->toContain( 'Too many submissions' );
        } );

        it( 'handles redirect after submission', function (): void {
            expect( $this->content )->toContain( 'redirect_url' )
                ->and( $this->content )->toContain( 'window.location.href' );
        } );

        it( 'validates forward step navigation', function (): void {
            expect( $this->content )->toContain( 'validateCurrentFieldSet' );
        } );
    } );

    describe( 'FormRenderer component content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/vue/components/FormRenderer.vue',
            );
        } );

        it( 'uses script setup with TypeScript', function (): void {
            expect( $this->content )->toContain( '<script setup lang="ts">' );
        } );

        it( 'renders loading state', function (): void {
            expect( $this->content )->toContain( 'v-if="isLoading"' )
                ->and( $this->content )->toContain( 'animate-pulse' );
        } );

        it( 'renders error state', function (): void {
            expect( $this->content )->toContain( 'loadError' )
                ->and( $this->content )->toContain( 'alert-error' );
        } );

        it( 'renders success state', function (): void {
            expect( $this->content )->toContain( 'isSubmitted' )
                ->and( $this->content )->toContain( 'alert-success' );
        } );

        it( 'renders multi-step forms', function (): void {
            expect( $this->content )->toContain( 'MultiStepForm' )
                ->and( $this->content )->toContain( 'is_multi_step' );
        } );

        it( 'renders honeypot field via HoneypotField component', function (): void {
            expect( $this->content )->toContain( 'HoneypotField' )
                ->and( $this->content )->toContain( 'honeypot.enabled' )
                ->and( $this->content )->toContain( 'honeypot.field_name' );
        } );

        it( 'wraps content in a form element', function (): void {
            expect( $this->content )->toContain( '<form' )
                ->and( $this->content )->toContain( 'novalidate' )
                ->and( $this->content )->toContain( '@submit="handleFormSubmit"' );
        } );

        it( 'supports custom slots for loading, error, and success', function (): void {
            expect( $this->content )->toContain( 'name="loading"' )
                ->and( $this->content )->toContain( 'name="error"' )
                ->and( $this->content )->toContain( 'name="success"' );
        } );
    } );

    describe( 'MultiStepForm uses Vue Transition', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/vue/components/MultiStepForm.vue',
            );
        } );

        it( 'uses Vue Transition for step animations', function (): void {
            expect( $this->content )->toContain( '<Transition' )
                ->and( $this->content )->toContain( 'name="fade"' );
        } );

        it( 'renders progress bar', function (): void {
            expect( $this->content )->toContain( 'progress-primary' )
                ->and( $this->content )->toContain( 'progressPercentage' );
        } );

        it( 'renders step indicators', function (): void {
            expect( $this->content )->toContain( 'steps-horizontal' )
                ->and( $this->content )->toContain( 'step-primary' );
        } );
    } );

    describe( 'FieldRenderer maps all field types', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/vue/components/fields/FieldRenderer.vue',
            );
        } );

        it( 'handles text-based field types', function (): void {
            expect( $this->content )->toContain( "'text'" )
                ->and( $this->content )->toContain( "'email'" )
                ->and( $this->content )->toContain( "'phone'" )
                ->and( $this->content )->toContain( "'number'" )
                ->and( $this->content )->toContain( "'url'" )
                ->and( $this->content )->toContain( "'textarea'" )
                ->and( $this->content )->toContain( "'hidden'" )
                ->and( $this->content )->toContain( "'time'" );
        } );

        it( 'handles choice field types', function (): void {
            expect( $this->content )->toContain( "'select'" )
                ->and( $this->content )->toContain( "'radio'" )
                ->and( $this->content )->toContain( "'checkbox'" )
                ->and( $this->content )->toContain( "'checkbox_group'" )
                ->and( $this->content )->toContain( "'select_multiple'" );
        } );

        it( 'handles advanced field types', function (): void {
            expect( $this->content )->toContain( "'file'" )
                ->and( $this->content )->toContain( "'date'" );
        } );

        it( 'handles layout field types', function (): void {
            expect( $this->content )->toContain( "'heading'" )
                ->and( $this->content )->toContain( "'paragraph'" )
                ->and( $this->content )->toContain( "'divider'" )
                ->and( $this->content )->toContain( "'html'" );
        } );

        it( 'supports field width classes', function (): void {
            expect( $this->content )->toContain( 'full' )
                ->and( $this->content )->toContain( 'half' )
                ->and( $this->content )->toContain( 'third' )
                ->and( $this->content )->toContain( 'two-thirds' );
        } );
    } );
} );
