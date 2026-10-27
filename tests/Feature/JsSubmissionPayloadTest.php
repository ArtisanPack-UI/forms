<?php

declare( strict_types=1 );

/**
 * Guards the multipart body the React and Vue renderers submit
 * (`shared/submission.ts`) and how they read the API's 422 errors.
 */

use Tests\Support\SharedTypeScript;

/**
 * Reads a renderer source file.
 */
function jsSubmissionSource( string $path ): string
{
    return (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );
}

describe( 'boolean values (RX1)', function (): void {
    it( 'sends booleans as 1 and 0, which the boolean rule accepts', function (): void {
        $entries = SharedTypeScript::run( <<<'JS'
            const { appendSubmissionValue } = await load( 'submission' );
            const body = new FormData();
            appendSubmissionValue( body, 'news', false );
            appendSubmissionValue( body, 'agree', true );
            appendSubmissionValue( body, 'tags', ['a', true] );
            appendSubmissionValue( body, 'rows', [{ done: false, name: 'x' }] );
            appendSubmissionValue( body, 'name', { first: 'A', last: null } );
            appendSubmissionValue( body, 'skip', null );
            return [...body.entries()];
            JS );

        if ( null === $entries ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( $entries )->toBe( [
            ['data[news]', '0'],
            ['data[agree]', '1'],
            ['data[tags][]', 'a'],
            ['data[tags][]', '1'],
            ['data[rows][0][done]', '0'],
            ['data[rows][0][name]', 'x'],
            ['data[name][first]', 'A'],
            ['data[name][last]', ''],
        ] );
    } );

    it( 'builds both renderers\' payloads with the shared helper', function ( string $path ): void {
        $source = jsSubmissionSource( $path );

        expect( $source )->toContain( 'appendSubmissionValue( formDataObj, key, value )' )
            ->and( $source )->not->toContain( 'formDataObj.append( `data[${key}]`, String( value ) )' );
    } )->with( ['react/hooks/useForm.ts', 'vue/composables/useForm.ts'] );
} );

describe( 'server errors (VU-04)', function (): void {
    it( 'routes errors for no field on the form to _form', function (): void {
        $errors = SharedTypeScript::run( <<<'JS'
            const { mapServerErrors } = await load( 'submission' );
            const fields = [
                { name: 'email', type: 'email', email_confirmation: {} },
                { name: 'address', type: 'address' },
                { name: 'docs', type: 'file' },
            ];
            return mapServerErrors( {
                data: ['The data field must be present.'],
                'data.email': ['The email is invalid.'],
                'data.address.city': ['City is required.'],
                'data.email_confirmation': ['Confirm it.'],
                'data.gone': ['Unknown field.'],
                'files.docs': ['Too big.'],
            }, fields );
            JS );

        if ( null === $errors ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( $errors )->toBe( [
            '_form'              => ['The data field must be present.', 'Unknown field.'],
            'email'              => ['The email is invalid.'],
            'address.city'       => ['City is required.'],
            'email_confirmation' => ['Confirm it.'],
            'docs'               => ['Too big.'],
        ] );
    } );

    it( 'maps 422 errors with the shared helper in both renderers', function ( string $path, string $call ): void {
        expect( jsSubmissionSource( $path ) )->toContain( $call );
    } )->with( [
        ['react/hooks/useForm.ts', 'const serverErrors = mapServerErrors( errorData.errors, allFields );'],
        ['vue/composables/useForm.ts', 'const serverErrors = mapServerErrors( errorData.errors, allFields.value );'],
    ] );
} );
