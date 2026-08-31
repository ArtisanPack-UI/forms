<?php

declare( strict_types=1 );

/**
 * Guards the fix for issue #70: every package-rendered <button> must carry a
 * cursor, hover and :focus-visible affordance rather than falling through to
 * the browser UA defaults. The React and Vue surfaces are validated by source
 * content (the package ships no JS build or test harness of its own), matching
 * the convention used by ReactFormRendererTest and TypeDefinitionsTest.
 */

$resource = static fn ( string $path ): string => file_get_contents( __DIR__ . '/../../resources/' . $path );

describe( 'Button interaction affordances (issue #70)', function () use ( $resource ): void {
    describe( 'React admin surfaces', function () use ( $resource ): void {
        it( 'gives the view-submission button a visible hover state', function () use ( $resource ): void {
            expect( $resource( 'js/react/components/admin/SubmissionsList.tsx' ) )
                ->toContain( 'className="link link-hover link-primary"' )
                ->not->toContain( 'className="link link-primary"' );
        } );
    } );

    describe( 'Vue admin surfaces', function () use ( $resource ): void {
        it( 'gives the view-submission button a visible hover state', function () use ( $resource ): void {
            expect( $resource( 'js/vue/components/admin/SubmissionsList.vue' ) )
                ->toContain( 'class="link link-hover link-primary"' )
                ->not->toContain( 'class="link link-primary"' );
        } );
    } );

    describe( 'Livewire admin surfaces', function () use ( $resource ): void {
        it( 'gives every forms-list sort header a cursor, hover and focus-visible ring', function () use ( $resource ): void {
            $markup = $resource( 'views/livewire/forms-list.blade.php' );

            expect( substr_count( $markup, 'group inline-flex items-center gap-1 cursor-pointer rounded hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' ) )
                ->toBe( 3 );
            expect( $markup )->not->toContain( 'class="group inline-flex items-center gap-1"' );
        } );

        it( 'gives every submissions-list sort header a cursor, hover and focus-visible ring', function () use ( $resource ): void {
            $markup = $resource( 'views/livewire/submissions-list.blade.php' );

            expect( substr_count( $markup, 'group inline-flex items-center gap-1 cursor-pointer rounded hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' ) )
                ->toBe( 2 );
            expect( $markup )->not->toContain( 'class="group inline-flex items-center gap-1"' );
        } );

        it( 'gives the field-card select button a cursor, hover and focus-visible ring', function () use ( $resource ): void {
            expect( $resource( 'views/livewire/form-builder/field-card.blade.php' ) )
                ->toContain( 'cursor-pointer rounded text-left hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' );
        } );

        it( 'gives the notification select button a cursor and focus-visible ring', function () use ( $resource ): void {
            expect( $resource( 'views/livewire/notification-editor.blade.php' ) )
                ->toContain( 'cursor-pointer items-center gap-3 rounded-lg p-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' );
        } );
    } );

    describe( 'AI feature partials', function () use ( $resource ): void {
        it( 'gives each AI action button a cursor, hover, focus-visible and disabled affordance', function ( string $file, string $hook ) use ( $resource ): void {
            expect( $resource( 'views/livewire/ai/' . $file ) )
                ->toContain( $hook . ' cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed' );
        } )->with( [
            'response-classifier'   => ['response-classifier.blade.php', 'forms-ai-classifier__button'],
            'spam-check'            => ['spam-check.blade.php', 'forms-ai-spam-check__button'],
            'smart-field-validator' => ['smart-field-validator.blade.php', 'forms-ai-smart-validator__button'],
            'submission-summary'    => ['submission-summary.blade.php', 'forms-ai-summary__button'],
        ] );

        it( 'gives the classifier accept button a cursor, hover and focus-visible affordance', function () use ( $resource ): void {
            expect( $resource( 'views/livewire/ai/response-classifier.blade.php' ) )
                ->toContain( 'forms-ai-classifier__accept cursor-pointer hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2' );
        } );
    } );
} );
