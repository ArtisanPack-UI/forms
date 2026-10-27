<?php

declare( strict_types=1 );

/**
 * Guards LW-03 (point 3) for the React and Vue builders: `field_config` is
 * saved whole, so an options change must start from the latest settings,
 * including after an image upload's await, never from an older snapshot.
 */

/**
 * Reads a JS source file.
 */
function jsOptionsEditorSource( string $path ): string
{
    return (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );
}

it( 'builds React option updates from the latest field mirror', function (): void {
    $source = jsOptionsEditorSource( 'react/components/admin/FieldEditor.tsx' );

    expect( $source )
        ->toContain( "updateField( { field_config: { ...( fieldRef.current.field_config ?? {} ), options: newOptions } } );\n\t\t},\n\t\t[updateField]," )
        ->toContain( 'fieldRef.current = { ...fieldRef.current, ...data } as FormField;' )
        ->not->toContain( '...( field.field_config ?? {} ), options: newOptions' );
} );

it( 'builds Vue option updates from the field with its buffered edits', function (): void {
    expect( jsOptionsEditorSource( 'vue/components/admin/FieldEditor.vue' ) )
        ->toContain( 'updateField( { field_config: { ...( currentField.value.field_config ?? {} ), options: newOptions } } );' )
        ->and( jsOptionsEditorSource( 'vue/components/admin/OptionsEditor.vue' ) )
        ->toContain( "updateOption( index, 'image', await props.uploadImage( file ) );" );
} );
