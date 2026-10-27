<?php

declare( strict_types=1 );

/**
 * Guards RX6 / VU-07: every React and Vue form instance has its own ID
 * prefix, so two forms on one page that share a field name produce no
 * duplicate IDs, and labels, descriptions and focus targets stay in their
 * own form.
 */

use Illuminate\Support\Facades\File;
use Tests\Support\SharedTypeScript;

it( 'builds field IDs from the form instance\'s prefix', function (): void {
    $results = SharedTypeScript::run( <<<'JS'
        const { createIdPrefix, fieldIdKey, withIdPrefix } = await load( 'domIds' );
        const { getFieldLayout, getInputNoteIds } = await load( 'fieldSettings' );
        const { getSubInputId } = await load( 'composite' );
        const email = { name: 'email', type: 'text', field_config: { show_counter: true }, validation_rules: { max: 10 } };
        const [first] = withIdPrefix( [email], createIdPrefix( ':r1:' ) );
        const [second] = withIdPrefix( [email], createIdPrefix() );
        const [third] = withIdPrefix( [email], createIdPrefix() );
        return {
            first: getFieldLayout( first ),
            second: getFieldLayout( second ).inputId,
            third: getFieldLayout( third ).inputId,
            preview: getFieldLayout( email ).inputId,
            sub: getSubInputId( first, 'city' ),
            key: fieldIdKey( first ),
        };
        JS );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
    }

    expect( $results['first']['inputId'] )->toBe( 'field-apfr1-email' )
        ->and( $results['first']['labelId'] )->toBe( 'field-apfr1-email-label' )
        ->and( $results['first']['descriptionId'] )->toBe( 'field-apfr1-email-description' )
        ->and( $results['second'] )->not->toBe( $results['third'] )
        ->and( $results['second'] )->not->toBe( 'field-email' )
        ->and( $results['preview'] )->toBe( 'field-email' )
        ->and( $results['sub'] )->toBe( 'field-apfr1-email-city' )
        ->and( $results['key'] )->toBe( 'apfr1-email' );
} );

it( 'gives each form instance\'s fields its prefix', function ( string $path, string $prefix, string $apply ): void {
    $source = (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );

    expect( $source )->toContain( $prefix )->toContain( $apply );
} )->with( [
    ['react/hooks/useForm.ts', 'const idPrefix = useMemo( () => createIdPrefix( reactId ), [reactId] );', 'fields: withIdPrefix( data.data.fields, idPrefixRef.current )'],
    ['vue/composables/useForm.ts', 'const idPrefix = createIdPrefix();', 'fields: withIdPrefix( data.data.fields, idPrefix )'],
] );

it( 'builds no field element ID from the field name alone', function (): void {
    $directories = [
        __DIR__ . '/../../resources/js/react/components/fields',
        __DIR__ . '/../../resources/js/vue/components/fields',
        __DIR__ . '/../../resources/js/shared',
    ];

    foreach ( $directories as $directory ) {
        foreach ( File::allFiles( $directory ) as $file ) {
            expect( $file->getContents() )->not->toMatch(
                '/(-\$\{(props\.)?(field|section)\.name\})|(\$\{(props\.)?(field|section)\.name\}-)/',
                $file->getRelativePathname() . ' builds an element ID from the field name alone',
            );
        }
    }
} );
