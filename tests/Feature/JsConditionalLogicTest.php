<?php

declare( strict_types=1 );

/**
 * Guards the shared client conditional logic (`shared/conditionalLogic.ts`)
 * against the server's: rules read normalized values (BE-2) and "is empty"
 * treats an unchecked box as empty (FT-06).
 */

use Tests\Support\SharedTypeScript;

/**
 * Evaluates which fields are hidden for each set of values.
 *
 * @param  array<int, array<string, mixed>>  $fields  The fields.
 * @param  array<int, array<string, mixed>>  $valueSets  The values to evaluate.
 *
 * @return array<int, array<string, bool>>|null The hidden maps, or null without Node.
 */
function jsHiddenFields( array $fields, array $valueSets ): ?array
{
    return SharedTypeScript::run( <<<'JS'
        const { getHiddenFields } = await load( 'conditionalLogic' );
        return input.valueSets.map( ( values ) => getHiddenFields( input.fields, values ) );
        JS, ['fields' => $fields, 'valueSets' => $valueSets] );
}

/**
 * A field shown when one rule holds.
 *
 * @return array<string, mixed>
 */
function jsShownWhen( string $name, string $field, string $operator, mixed $value = '' ): array
{
    return [
        'name'              => $name,
        'uuid'              => $name . '-uuid',
        'type'              => 'textarea',
        'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => $field, 'operator' => $operator, 'value' => $value]]],
    ];
}

describe( 'normalized values (BE-2)', function (): void {
    it( 'reads formatted numbers, URLs, colors and rich text as the server does', function (): void {
        $fields = [
            ['name' => 'amount', 'uuid' => 'amount-uuid', 'type' => 'number', 'field_config' => ['number_format' => 'decimal_dot']],
            ['name' => 'euros', 'uuid' => 'euros-uuid', 'type' => 'number', 'field_config' => ['number_format' => 'decimal_comma']],
            ['name' => 'site', 'uuid' => 'site-uuid', 'type' => 'url'],
            ['name' => 'shade', 'uuid' => 'shade-uuid', 'type' => 'color'],
            ['name' => 'bio', 'uuid' => 'bio-uuid', 'type' => 'textarea', 'field_config' => ['rich_text' => true]],
            jsShownWhen( 'details', 'amount', 'greater_than', '500' ),
            jsShownWhen( 'eu_details', 'euros', 'greater_than', '500' ),
            jsShownWhen( 'secure', 'site', 'starts_with', 'https://' ),
            jsShownWhen( 'orange', 'shade', 'equals', '#ff8800' ),
            jsShownWhen( 'about', 'bio', 'is_empty' ),
        ];

        $hidden = jsHiddenFields( $fields, [
            ['amount' => '1,000', 'euros' => '1.000,50', 'site' => 'example.com', 'shade' => 'FF8800', 'bio' => '<p><br></p>'],
            ['amount' => '400', 'euros' => '400,00', 'site' => 'ftp.example.com', 'shade' => '#000', 'bio' => '<p>Hi</p>'],
        ] );

        if ( null === $hidden ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( array_intersect_key( $hidden[0], array_flip( ['details', 'eu_details', 'secure', 'orange', 'about'] ) ) )
            ->toBe( ['details' => false, 'eu_details' => false, 'secure' => false, 'orange' => false, 'about' => false] )
            ->and( array_intersect_key( $hidden[1], array_flip( ['details', 'eu_details', 'orange', 'about'] ) ) )
            ->toBe( ['details' => true, 'eu_details' => true, 'orange' => true, 'about' => true] );
    } );
} );
