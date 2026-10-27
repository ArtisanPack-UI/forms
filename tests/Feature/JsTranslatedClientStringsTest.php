<?php

declare( strict_types=1 );

/**
 * Guards REL-07, RX7 and VU-09: the React/Vue client validation messages and
 * renderer text go through `t()`, so a host's translations apply to them as
 * they do to the Livewire renderer.
 */

use Tests\Support\SharedTypeScript;

/**
 * Reads a JS source file.
 */
function jsTranslatedSource( string $path ): string
{
    return (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );
}

it( 'pushes no untranslated validation message', function (): void {
    $validation = jsTranslatedSource( 'shared/validation.ts' );

    expect( $validation )->not->toMatch( '/errors\.push\(\s*[`\']/' )
        ->not->toMatch( '/required_message \?\? `/' );
} );

it( 'translates the client validation messages', function (): void {
    $errors = SharedTypeScript::run( <<<'JS'
        const { setTranslations } = await load( 'i18n' );
        const { validateField } = await load( 'validation' );
        setTranslations( {
            'The :field field is required.': 'Le champ :field est obligatoire.',
            'Please enter a valid email address for :field.': 'Adresse e-mail invalide pour :field.',
            'The :field must be at least :min characters.': ':field doit contenir au moins :min caractères.',
        } );
        return [
            validateField( { name: 'name', label: 'Nom', type: 'text', is_required: true }, '' ),
            validateField( { name: 'email', label: 'Courriel', type: 'email' }, 'nope' ),
            validateField( { name: 'name', label: 'Nom', type: 'text', validation_rules: { min: 3 } }, 'ab' ),
        ];
        JS );

    if ( null === $errors ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $errors )->toBe( [
        ['Le champ Nom est obligatoire.'],
        ['Adresse e-mail invalide pour Courriel.'],
        ['Nom doit contenir au moins 3 caractères.'],
    ] );
} );

it( 'translates the renderer text', function ( string $path, array $strings ): void {
    $source = jsTranslatedSource( $path );

    foreach ( $strings as $string ) {
        expect( $source )->toContain( "t( '{$string}' )" );
    }
} )->with( [
    ['react/components/FormRenderer.tsx', ['Form not found.', 'Form submitted successfully.', 'Submit another response', 'Submitting...', 'Leave this field empty']],
    ['vue/components/FormRenderer.vue', ['Form not found.', 'Form submitted successfully.', 'Submit another response', 'Submitting...']],
    ['vue/components/HoneypotField.vue', ['Leave this field empty']],
    ['react/components/MultiStepForm.tsx', ['Previous', 'Next', 'Submitting...', 'Form progress']],
    ['vue/components/MultiStepForm.vue', ['Previous', 'Next', 'Submitting...', 'Form progress']],
    ['react/components/fields/ChoiceField.tsx', ['Select an option...']],
    ['vue/components/fields/ChoiceField.vue', ['Select an option...']],
    ['react/hooks/useForm.ts', ['Failed to load form.', 'Form submission failed.', 'Too many submissions. Please try again later.']],
    ['vue/composables/useForm.ts', ['Failed to load form.', 'Form submission failed.', 'Too many submissions. Please try again later.']],
] );
