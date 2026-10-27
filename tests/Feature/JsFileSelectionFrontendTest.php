<?php

declare( strict_types=1 );

/**
 * Guards RX3 / VU-06: a file field's selection is the form's, so it is still
 * listed after a step change or conditional logic remounts the field, and
 * adding a file keeps the earlier ones.
 */

use Tests\Support\SharedTypeScript;

/**
 * Reads a JS source file.
 */
function jsFileSelectionSource( string $path ): string
{
    return (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );
}

it( 'reads the form\'s files as a list', function (): void {
    $results = SharedTypeScript::run( <<<'JS'
        const { toFileList } = await load( 'fieldSettings' );
        const a = new File( ['a'], 'a.pdf' );
        const b = new File( ['b'], 'b.pdf' );
        return [toFileList( undefined ), toFileList( a ), toFileList( [a, b] )].map( ( list ) => list.map( ( file ) => file.name ) );
        JS );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $results )->toBe( [[], ['a.pdf'], ['a.pdf', 'b.pdf']] );
} );

it( 'lists the form\'s files in React file fields', function (): void {
    $field = jsFileSelectionSource( 'react/components/fields/AdvancedField.tsx' );

    expect( $field )
        ->not->toContain( 'useState<File[]>' )
        ->toContain( 'const files = toFileList( selected );' )
        ->toContain( 'const [file] = toFileList( selected );' )
        ->toContain( 'onFileChange?.( [] );' )
        ->and( jsFileSelectionSource( 'react/components/fields/FieldRenderer.tsx' ) )->toContain( 'files={files}' )
        ->and( jsFileSelectionSource( 'react/components/FormRenderer.tsx' ) )->toContain( 'files={files[field.name]}' );
} );

it( 'lists the form\'s files in Vue file fields', function (): void {
    $field = jsFileSelectionSource( 'vue/components/fields/AdvancedField.vue' );

    expect( $field )
        ->not->toContain( 'ref<File[]>' )
        ->toContain( 'const selectedFiles = computed( () => toFileList( props.files ) );' )
        ->toContain( '@click="clearFile"' )
        ->and( jsFileSelectionSource( 'vue/components/fields/FieldRenderer.vue' ) )->toContain( ':files="files"' )
        ->and( substr_count( jsFileSelectionSource( 'vue/components/FormRenderer.vue' ), ':files="files[field.name]"' ) )->toBe( 2 );
} );
