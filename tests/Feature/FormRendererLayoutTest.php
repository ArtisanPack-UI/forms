<?php

declare( strict_types=1 );

/**
 * Guards issue #141: the React and Vue FormRenderer accept a `layout` prop
 * (`stacked` by default, or `inline`) and a `submitClassName` prop. The
 * surfaces are validated by source content (the package ships no JS build or
 * test harness of its own), matching the convention used by
 * ReactFormRendererTest and ButtonAffordancesTest.
 */

$resource = static fn ( string $path ): string => file_get_contents( __DIR__ . '/../../resources/js/' . $path );

/**
 * Returns the part of `$haystack` between the first `$start` and the next
 * `$end` after it, so an assertion can be scoped to one block of markup.
 */
$between = static function ( string $haystack, string $start, string $end ): string {
    $from = strpos( $haystack, $start );
    expect( $from )->not->toBeFalse();

    $to = strpos( $haystack, $end, $from + strlen( $start ) );
    expect( $to )->not->toBeFalse();

    return substr( $haystack, $from, $to - $from );
};

describe( 'FormRenderer layout and submit class (issue #141)', function () use ( $resource, $between ): void {
    it( 'declares the FormLayout type', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "export type FormLayout = 'stacked' | 'inline';" );
    } );

    describe( 'React', function () use ( $resource, $between ): void {
        beforeEach( function () use ( $resource ): void {
            $this->renderer = $resource( 'react/components/FormRenderer.tsx' );
        } );

        it( 'adds the layout and submitClassName props, defaulting to stacked', function (): void {
            expect( $this->renderer )
                ->toContain( 'layout?: FormLayout;' )
                ->toContain( 'submitClassName?: string;' )
                ->toContain( "layout = 'stacked'," );
        } );

        it( 'keeps the stacked submit button in its own wrapper below the field row', function () use ( $between ): void {
            $singleStep = $between( $this->renderer, '{renderedFields}', '</form>' );

            expect( $singleStep )->toContain( "{!isInline && (\n\t\t\t\t\t\t<div>\n\t\t\t\t\t\t\t{submitButton}\n\t\t\t\t\t\t</div>" );
        } );

        it( 'renders the inline submit button as the last item in the field row', function () use ( $between ): void {
            $fieldRow = $between( $this->renderer, '<div className="flex flex-wrap gap-4">', '// Form-level errors' );

            expect( $fieldRow )->toContain( '{isInline && submitButton}' )
                ->and( strpos( $fieldRow, '{isInline && submitButton}' ) )
                ->toBeGreaterThan( strpos( $fieldRow, 'Honeypot field' ) )
                ->and( $fieldRow )->toContain( "layout={isInline ? 'inline' : 'stacked'}" );
        } );

        it( 'aligns the inline button to the bottom of the row without growing', function (): void {
            expect( $this->renderer )->toContain( "isInline ? 'shrink-0 self-end' : ''" );
        } );

        it( 'appends submitClassName to the submit button in both layouts', function () use ( $between ): void {
            $submitButton = $between( $this->renderer, 'const submitButton = (', '</Button>' );

            expect( $this->renderer )->toContain( "submitClassName ?? ''" )
                ->and( $submitButton )->toContain( 'type="submit"' )
                ->and( $submitButton )->toContain( 'className={submitClasses}' );
        } );

        it( 'ignores the layout for multi-step forms', function () use ( $between ): void {
            $multiStep = $between( $this->renderer, '<MultiStepForm', '</MultiStepForm>' );

            expect( $this->renderer )->toContain( "const isInline = layout === 'inline' && !form.is_multi_step;" )
                ->and( $multiStep )->not->toContain( 'layout' )
                ->and( $multiStep )->not->toContain( 'submitClassName' );
        } );
    } );

    describe( 'React FieldRenderer', function () use ( $resource ): void {
        beforeEach( function () use ( $resource ): void {
            $this->fieldRenderer = $resource( 'react/components/fields/FieldRenderer.tsx' );
        } );

        it( 'lets a full-width field shrink and hides hidden inputs in an inline row', function (): void {
            expect( $this->fieldRenderer )
                ->toContain( "layout = 'stacked'," )
                ->toContain( "width === 'full' ? 'flex-1 min-w-[min(12rem,100%)]' : WIDTH_CLASSES[width]" )
                ->toContain( "if ( field.type === 'hidden' ) {\n\t\treturn 'hidden';" );
        } );

        it( 'keeps the stacked width classes unchanged', function (): void {
            expect( $this->fieldRenderer )
                ->toContain( "full: 'w-full'," )
                ->toContain( ': ( WIDTH_CLASSES[field.width] ?? WIDTH_CLASSES.full );' );
        } );
    } );

    describe( 'Vue', function () use ( $resource, $between ): void {
        beforeEach( function () use ( $resource ): void {
            $this->renderer = $resource( 'vue/components/FormRenderer.vue' );
        } );

        it( 'adds the layout and submitClassName props, defaulting to stacked', function (): void {
            expect( $this->renderer )
                ->toContain( 'layout?: FormLayout;' )
                ->toContain( 'submitClassName?: string;' )
                ->toContain( "layout: 'stacked'," );
        } );

        it( 'keeps the stacked submit button in its own wrapper below the field row', function (): void {
            expect( $this->renderer )->toContain(
                "<div v-if=\"!isInline\">\n\t\t\t\t<Button type=\"submit\" color=\"primary\" :disabled=\"isSubmitting\" :class=\"submitClassName\">",
            );
        } );

        it( 'renders the inline submit button as the last item in the field row', function () use ( $between ): void {
            $singleStep = $between( $this->renderer, '<!-- Single-step form -->', '<div v-if="!isInline">' );
            $inline     = $between( $singleStep, '<Button', '</Button>' );

            expect( $inline )->toContain( 'v-if="isInline"' )
                ->and( $inline )->toContain( 'type="submit"' )
                ->and( strpos( $singleStep, '<Button' ) )->toBeGreaterThan( strpos( $singleStep, '<HoneypotField' ) )
                ->and( $singleStep )->toContain( ':layout="layout"' );
        } );

        it( 'aligns the inline button to the bottom of the row and appends submitClassName', function (): void {
            expect( $this->renderer )->toContain( ":class=\"['shrink-0 self-end', submitClassName]\"" );
        } );

        it( 'ignores the layout for multi-step forms', function () use ( $between ): void {
            $multiStep = $between( $this->renderer, '<MultiStepForm', '</MultiStepForm>' );

            expect( $multiStep )->not->toContain( 'layout' )
                ->and( $multiStep )->not->toContain( 'isInline' )
                ->and( $multiStep )->not->toContain( 'submitClassName' );
        } );
    } );

    describe( 'Vue FieldRenderer', function () use ( $resource ): void {
        beforeEach( function () use ( $resource ): void {
            $this->fieldRenderer = $resource( 'vue/components/fields/FieldRenderer.vue' );
        } );

        it( 'lets a full-width field shrink and hides hidden inputs in an inline row', function (): void {
            expect( $this->fieldRenderer )
                ->toContain( 'layout?: FormLayout;' )
                ->toContain( "props.layout === 'inline'" )
                ->toContain( "width === 'full' ? 'flex-1 min-w-[min(12rem,100%)]' : WIDTH_CLASSES[width]" )
                ->toContain( "if ( field.type === 'hidden' ) {\n\t\treturn 'hidden';" );
        } );

        it( 'keeps the stacked width classes unchanged', function (): void {
            expect( $this->fieldRenderer )
                ->toContain( "full: 'w-full'," )
                ->toContain( ': ( WIDTH_CLASSES[props.field.width] ?? WIDTH_CLASSES.full )' );
        } );
    } );
} );
