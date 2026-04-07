<?php

declare( strict_types=1 );

use Illuminate\Support\ServiceProvider;

describe( 'React Form Renderer', function (): void {
    describe( 'publishing', function (): void {
        it( 'registers forms-react as a publishable tag', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;

            expect( $publishGroups )->toHaveKey( 'forms-react' );
        } );

        it( 'maps the source react directory to the correct destination', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;
            $paths         = $publishGroups['forms-react'] ?? [];

            $matchingKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/js/react' ),
            );

            expect( $matchingKeys )->not->toBeEmpty();

            $sourcePath  = array_values( $matchingKeys )[0];
            $destination = $paths[ $sourcePath ];
            expect( $destination )->toContain( 'js/vendor/artisanpack-forms' );
        } );
    } );

    describe( 'file structure', function (): void {
        it( 'has the main index entry point', function (): void {
            $path = __DIR__ . '/../../resources/js/react/index.ts';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the useForm hook', function (): void {
            $path = __DIR__ . '/../../resources/js/react/hooks/useForm.ts';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the conditional logic utility', function (): void {
            $path = __DIR__ . '/../../resources/js/shared/conditionalLogic.ts';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the validation utility', function (): void {
            $path = __DIR__ . '/../../resources/js/shared/validation.ts';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the FormRenderer component', function (): void {
            $path = __DIR__ . '/../../resources/js/react/components/FormRenderer.tsx';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the MultiStepForm component', function (): void {
            $path = __DIR__ . '/../../resources/js/react/components/MultiStepForm.tsx';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has the FieldRenderer component', function (): void {
            $path = __DIR__ . '/../../resources/js/react/components/fields/FieldRenderer.tsx';

            expect( file_exists( $path ) )->toBeTrue();
        } );

        it( 'has field type components for all field categories', function (): void {
            $fieldsDir = __DIR__ . '/../../resources/js/react/components/fields/';

            expect( file_exists( $fieldsDir . 'TextField.tsx' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'ChoiceField.tsx' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'AdvancedField.tsx' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'LayoutField.tsx' ) )->toBeTrue()
                ->and( file_exists( $fieldsDir . 'types.ts' ) )->toBeTrue();
        } );
    } );

    describe( 'index exports', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/react/index.ts',
            );
        } );

        it( 'exports the FormRenderer component', function (): void {
            expect( $this->content )->toContain( 'export { FormRenderer }' );
        } );

        it( 'exports the MultiStepForm component', function (): void {
            expect( $this->content )->toContain( 'export { MultiStepForm }' );
        } );

        it( 'exports the FieldRenderer component', function (): void {
            expect( $this->content )->toContain( 'export { FieldRenderer }' );
        } );

        it( 'exports the useForm hook', function (): void {
            expect( $this->content )->toContain( 'export { useForm }' );
        } );

        it( 'exports conditional logic utilities', function (): void {
            expect( $this->content )->toContain( 'compareValues' )
                ->and( $this->content )->toContain( 'evaluateFieldVisibility' )
                ->and( $this->content )->toContain( 'getHiddenFields' );
        } );

        it( 'exports validation utilities', function (): void {
            expect( $this->content )->toContain( 'validateField' )
                ->and( $this->content )->toContain( 'validateFields' );
        } );

        it( 'exports all text-based field components', function (): void {
            expect( $this->content )->toContain( 'TextField' )
                ->and( $this->content )->toContain( 'EmailField' )
                ->and( $this->content )->toContain( 'PhoneField' )
                ->and( $this->content )->toContain( 'NumberField' )
                ->and( $this->content )->toContain( 'UrlField' )
                ->and( $this->content )->toContain( 'TextareaField' )
                ->and( $this->content )->toContain( 'HiddenField' )
                ->and( $this->content )->toContain( 'TimeField' );
        } );

        it( 'exports all choice field components', function (): void {
            expect( $this->content )->toContain( 'SelectField' )
                ->and( $this->content )->toContain( 'RadioField' )
                ->and( $this->content )->toContain( 'CheckboxField' )
                ->and( $this->content )->toContain( 'CheckboxGroupField' )
                ->and( $this->content )->toContain( 'SelectMultipleField' );
        } );

        it( 'exports advanced field components', function (): void {
            expect( $this->content )->toContain( 'FileField' )
                ->and( $this->content )->toContain( 'DateField' );
        } );

        it( 'exports layout field components', function (): void {
            expect( $this->content )->toContain( 'HeadingField' )
                ->and( $this->content )->toContain( 'ParagraphField' )
                ->and( $this->content )->toContain( 'DividerField' )
                ->and( $this->content )->toContain( 'HtmlField' );
        } );
    } );

    describe( 'conditional logic engine content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/shared/conditionalLogic.ts',
            );
        } );

        it( 'implements all 18 comparison operators', function (): void {
            $operators = [
                "'equals'",
                "'not_equals'",
                "'contains'",
                "'not_contains'",
                "'starts_with'",
                "'ends_with'",
                "'is_empty'",
                "'is_not_empty'",
                "'greater_than'",
                "'less_than'",
                "'greater_or_equal'",
                "'less_or_equal'",
                "'in'",
                "'not_in'",
                "'checked'",
                "'unchecked'",
                "'includes'",
                "'not_includes'",
            ];

            foreach ( $operators as $operator ) {
                expect( $this->content )->toContain( $operator );
            }
        } );

        it( 'exports the getHiddenFields function', function (): void {
            expect( $this->content )->toContain( 'export function getHiddenFields' );
        } );

        it( 'exports the evaluateFieldVisibility function', function (): void {
            expect( $this->content )->toContain( 'export function evaluateFieldVisibility' );
        } );

        it( 'exports the compareValues function', function (): void {
            expect( $this->content )->toContain( 'export function compareValues' );
        } );

        it( 'handles show action and inverts for hide', function (): void {
            expect( $this->content )->toContain( "action === 'show'" )
                ->and( $this->content )->toContain( 'conditionsMet' );
        } );

        it( 'handles both all and any logic types', function (): void {
            expect( $this->content )->toContain( "logicType === 'all'" )
                ->and( $this->content )->toContain( '.every' )
                ->and( $this->content )->toContain( '.some' );
        } );

        it( 'resolves field references by UUID', function (): void {
            expect( $this->content )->toContain( 'resolveFieldName' )
                ->and( $this->content )->toContain( 'uuidToName' );
        } );
    } );

    describe( 'validation engine content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/shared/validation.ts',
            );
        } );

        it( 'validates required fields', function (): void {
            expect( $this->content )->toContain( 'is_required' )
                ->and( $this->content )->toContain( 'is required' );
        } );

        it( 'validates email fields', function (): void {
            expect( $this->content )->toContain( "'email'" )
                ->and( $this->content )->toContain( 'valid email address' );
        } );

        it( 'validates url fields', function (): void {
            expect( $this->content )->toContain( "'url'" )
                ->and( $this->content )->toContain( 'valid URL' );
        } );

        it( 'validates numeric fields', function (): void {
            expect( $this->content )->toContain( "'number'" )
                ->and( $this->content )->toContain( 'valid number' );
        } );

        it( 'validates date fields', function (): void {
            expect( $this->content )->toContain( "'date'" )
                ->and( $this->content )->toContain( 'valid date' );
        } );

        it( 'validates time fields', function (): void {
            expect( $this->content )->toContain( "'time'" )
                ->and( $this->content )->toContain( 'min_time' )
                ->and( $this->content )->toContain( 'max_time' );
        } );

        it( 'validates file fields', function (): void {
            expect( $this->content )->toContain( "'file'" )
                ->and( $this->content )->toContain( 'max_size' )
                ->and( $this->content )->toContain( 'allowed_types' );
        } );

        it( 'skips layout fields', function (): void {
            expect( $this->content )->toContain( 'LAYOUT_FIELDS' )
                ->and( $this->content )->toContain( "'heading'" )
                ->and( $this->content )->toContain( "'paragraph'" )
                ->and( $this->content )->toContain( "'divider'" );
        } );

        it( 'skips hidden fields during validation', function (): void {
            expect( $this->content )->toContain( 'hiddenFields' );
        } );
    } );

    describe( 'useForm hook content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/react/hooks/useForm.ts',
            );
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

        it( 'calculates progress percentage', function (): void {
            expect( $this->content )->toContain( 'progressPercentage' );
        } );

        it( 'manages honeypot spam protection', function (): void {
            expect( $this->content )->toContain( 'honeypot' )
                ->and( $this->content )->toContain( '_form_loaded_at' );
        } );

        it( 'handles file uploads via FormData', function (): void {
            expect( $this->content )->toContain( 'FormData' )
                ->and( $this->content )->toContain( 'files' );
        } );

        it( 'handles server validation errors', function (): void {
            expect( $this->content )->toContain( '422' )
                ->and( $this->content )->toContain( 'errors' );
        } );

        it( 'handles rate limiting responses', function (): void {
            expect( $this->content )->toContain( '429' )
                ->and( $this->content )->toContain( 'Too many submissions' );
        } );

        it( 'handles redirect after submission', function (): void {
            expect( $this->content )->toContain( 'redirect_url' )
                ->and( $this->content )->toContain( 'window.location.href' );
        } );

        it( 'supports form reset', function (): void {
            expect( $this->content )->toContain( 'reset' )
                ->and( $this->content )->toContain( 'setIsSubmitted( false )' );
        } );

        it( 'initializes default values from field definitions', function (): void {
            expect( $this->content )->toContain( 'default_value' )
                ->and( $this->content )->toContain( 'defaults' );
        } );
    } );

    describe( 'FormRenderer component content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/react/components/FormRenderer.tsx',
            );
        } );

        it( 'renders loading state', function (): void {
            expect( $this->content )->toContain( 'isLoading' )
                ->and( $this->content )->toContain( 'DefaultLoading' );
        } );

        it( 'renders error state', function (): void {
            expect( $this->content )->toContain( 'loadError' )
                ->and( $this->content )->toContain( 'ErrorComponent' );
        } );

        it( 'renders success state after submission', function (): void {
            expect( $this->content )->toContain( 'isSubmitted' )
                ->and( $this->content )->toContain( 'SuccessComponent' );
        } );

        it( 'renders multi-step forms', function (): void {
            expect( $this->content )->toContain( 'MultiStepForm' )
                ->and( $this->content )->toContain( 'is_multi_step' );
        } );

        it( 'renders honeypot field', function (): void {
            expect( $this->content )->toContain( 'honeypot' )
                ->and( $this->content )->toContain( 'aria-hidden' )
                ->and( $this->content )->toContain( 'Leave this field empty' );
        } );

        it( 'hides fields based on conditional logic', function (): void {
            expect( $this->content )->toContain( 'hiddenFields' );
        } );

        it( 'supports custom loading, error, and success components', function (): void {
            expect( $this->content )->toContain( 'loadingComponent' )
                ->and( $this->content )->toContain( 'errorComponent' )
                ->and( $this->content )->toContain( 'successComponent' );
        } );
    } );

    describe( 'FieldRenderer maps all field types', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/js/react/components/fields/FieldRenderer.tsx',
            );
        } );

        it( 'maps all 19 field types to components', function (): void {
            $fieldTypes = [
                'text',
                'email',
                'phone',
                'number',
                'url',
                'textarea',
                'hidden',
                'select',
                'radio',
                'checkbox',
                'checkbox_group',
                'select_multiple',
                'file',
                'date',
                'time',
                'heading',
                'paragraph',
                'divider',
                'html',
            ];

            foreach ( $fieldTypes as $type ) {
                expect( $this->content )->toContain( $type );
            }
        } );

        it( 'supports field width classes', function (): void {
            expect( $this->content )->toContain( 'full' )
                ->and( $this->content )->toContain( 'half' )
                ->and( $this->content )->toContain( 'third' )
                ->and( $this->content )->toContain( 'two-thirds' );
        } );
    } );
} );
