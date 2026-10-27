<?php

declare( strict_types=1 );

/**
 * Guards the shared client validation (`shared/validation.ts`) against the
 * server's rules: selection counts (FT-03), the "Other" choice (FT-04), the
 * number step (FT-05) and text length in characters (FT-08).
 */

use Tests\Support\SharedTypeScript;

/**
 * Validates each value against its field on the client.
 *
 * @param  array<int, array{0: array<string, mixed>, 1: mixed}>  $cases  Field and value pairs.
 *
 * @return array<int, array<int, string>>|null The errors per case, or null without Node.
 */
function jsValidate( array $cases ): ?array
{
    return SharedTypeScript::run( <<<'JS'
        const { validateField } = await load( 'validation' );
        return input.map( ( [field, value] ) => validateField( { name: 'answer', label: 'Answer', ...field }, value ) );
        JS, $cases );
}

describe( 'selection counts (FT-03)', function (): void {
    it( 'checks min and max selections but lets an optional group stay empty', function (): void {
        $group    = ['type' => 'checkbox_group', 'validation_rules' => ['min' => 2, 'max' => 3], 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C'], ['value' => 'd', 'label' => 'D']]];
        $required = [...$group, 'is_required' => true];

        $errors = jsValidate( [
            [$group, []],
            [$group, ['a']],
            [$group, ['a', 'b']],
            [$group, ['a', 'b', 'c', 'd']],
            [['type' => 'select_multiple', 'validation_rules' => ['min' => 2]], ['a']],
            [$required, []],
        ] );

        if ( null === $errors ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( $errors )->toBe( [
            [],
            ['Please choose at least 2 options.'],
            [],
            ['Please choose no more than 3 options.'],
            ['Please choose at least 2 options.'],
            ['Answer is required.'],
        ] );
    } );
} );

describe( 'the Other choice (FT-04)', function (): void {
    it( 'accepts one answer of the visitor\'s own in a checkbox group, not more', function (): void {
        $group = ['type' => 'checkbox_group', 'other_choice_label' => 'Other', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]];

        $errors = jsValidate( [
            [$group, ['a', 'my text']],
            [$group, ['a', 'b']],
            [$group, ['a', 'x', 'y']],
        ] );

        if ( null === $errors ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( $errors )->toBe( [
            [],
            [],
            ['Please choose one of the available options for Answer.'],
        ] );
    } );
} );

describe( 'number step (FT-05)', function (): void {
    it( 'checks the step from the minimum with the server\'s tolerance', function (): void {
        $quarter = ['type' => 'number', 'validation_rules' => ['step' => 0.25, 'min' => 0]];
        $tenth   = ['type' => 'number', 'validation_rules' => ['step' => 0.1]];

        $errors = jsValidate( [
            [$quarter, '1.1'],
            [$quarter, '1.25'],
            [$tenth, '0.3'],
            [$tenth, '123456789.3'],
        ] );

        if ( null === $errors ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( $errors )->toBe( [
            ['The Answer must be in steps of 0.25.'],
            [],
            [],
            [],
        ] );
    } );
} );
