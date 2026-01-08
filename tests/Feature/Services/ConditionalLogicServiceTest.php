<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;

beforeEach( function (): void {
    $this->service = new ConditionalLogicService;
    $this->form    = Form::factory()->create();
} );

describe( 'ConditionalLogicService', function (): void {
    describe( 'evaluateField', function (): void {
        it( 'returns true for field without conditional logic', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'conditional_logic' => null,
            ] );

            $result = $this->service->evaluateField( $field, [] );

            expect( $result )->toBeTrue();
        } );

        it( 'returns true for field with empty rules', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'conditional_logic' => [
                    'action' => 'show',
                    'logic'  => 'all',
                    'rules'  => [],
                ],
            ] );

            $result = $this->service->evaluateField( $field, [] );

            expect( $result )->toBeTrue();
        } );

        it( 'evaluates show action with equals operator', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'conditional_logic' => [
                    'action' => 'show',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
                    ],
                ],
            ] );

            // Condition met - field should be visible
            $result = $this->service->evaluateField( $field, ['country' => 'US'] );
            expect( $result )->toBeTrue();

            // Condition not met - field should be hidden
            $result = $this->service->evaluateField( $field, ['country' => 'UK'] );
            expect( $result )->toBeFalse();
        } );

        it( 'evaluates hide action correctly', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'conditional_logic' => [
                    'action' => 'hide',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'hide_details', 'operator' => 'checked', 'value' => ''],
                    ],
                ],
            ] );

            // Condition met - field should be hidden
            $result = $this->service->evaluateField( $field, ['hide_details' => true] );
            expect( $result )->toBeFalse();

            // Condition not met - field should be visible
            $result = $this->service->evaluateField( $field, ['hide_details' => false] );
            expect( $result )->toBeTrue();
        } );

        it( 'evaluates all logic (AND) correctly', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'conditional_logic' => [
                    'action' => 'show',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'age', 'operator' => 'greater_than', 'value' => '18'],
                        ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
                    ],
                ],
            ] );

            // Both conditions met
            $result = $this->service->evaluateField( $field, ['age' => 25, 'country' => 'US'] );
            expect( $result )->toBeTrue();

            // Only one condition met
            $result = $this->service->evaluateField( $field, ['age' => 25, 'country' => 'UK'] );
            expect( $result )->toBeFalse();

            // No conditions met
            $result = $this->service->evaluateField( $field, ['age' => 15, 'country' => 'UK'] );
            expect( $result )->toBeFalse();
        } );

        it( 'evaluates any logic (OR) correctly', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'conditional_logic' => [
                    'action' => 'show',
                    'logic'  => 'any',
                    'rules'  => [
                        ['field' => 'is_admin', 'operator' => 'checked', 'value' => ''],
                        ['field' => 'is_moderator', 'operator' => 'checked', 'value' => ''],
                    ],
                ],
            ] );

            // Both conditions met
            $result = $this->service->evaluateField( $field, ['is_admin' => true, 'is_moderator' => true] );
            expect( $result )->toBeTrue();

            // Only one condition met
            $result = $this->service->evaluateField( $field, ['is_admin' => true, 'is_moderator' => false] );
            expect( $result )->toBeTrue();

            // No conditions met
            $result = $this->service->evaluateField( $field, ['is_admin' => false, 'is_moderator' => false] );
            expect( $result )->toBeFalse();
        } );
    } );

    describe( 'compareValues', function (): void {
        it( 'compares equals correctly', function (): void {
            expect( $this->service->compareValues( 'test', 'equals', 'test' ) )->toBeTrue();
            expect( $this->service->compareValues( 'test', 'equals', 'other' ) )->toBeFalse();
            expect( $this->service->compareValues( 42, 'equals', '42' ) )->toBeTrue();
            expect( $this->service->compareValues( true, 'equals', 'true' ) )->toBeTrue();
        } );

        it( 'compares not_equals correctly', function (): void {
            expect( $this->service->compareValues( 'test', 'not_equals', 'other' ) )->toBeTrue();
            expect( $this->service->compareValues( 'test', 'not_equals', 'test' ) )->toBeFalse();
        } );

        it( 'compares contains correctly', function (): void {
            expect( $this->service->compareValues( 'hello world', 'contains', 'world' ) )->toBeTrue();
            expect( $this->service->compareValues( 'hello', 'contains', 'world' ) )->toBeFalse();
        } );

        it( 'compares not_contains correctly', function (): void {
            expect( $this->service->compareValues( 'hello', 'not_contains', 'world' ) )->toBeTrue();
            expect( $this->service->compareValues( 'hello world', 'not_contains', 'world' ) )->toBeFalse();
        } );

        it( 'compares starts_with correctly', function (): void {
            expect( $this->service->compareValues( 'hello world', 'starts_with', 'hello' ) )->toBeTrue();
            expect( $this->service->compareValues( 'hello world', 'starts_with', 'world' ) )->toBeFalse();
        } );

        it( 'compares ends_with correctly', function (): void {
            expect( $this->service->compareValues( 'hello world', 'ends_with', 'world' ) )->toBeTrue();
            expect( $this->service->compareValues( 'hello world', 'ends_with', 'hello' ) )->toBeFalse();
        } );

        it( 'compares is_empty correctly', function (): void {
            expect( $this->service->compareValues( '', 'is_empty', '' ) )->toBeTrue();
            expect( $this->service->compareValues( null, 'is_empty', '' ) )->toBeTrue();
            expect( $this->service->compareValues( [], 'is_empty', '' ) )->toBeTrue();
            expect( $this->service->compareValues( 'test', 'is_empty', '' ) )->toBeFalse();
        } );

        it( 'compares is_not_empty correctly', function (): void {
            expect( $this->service->compareValues( 'test', 'is_not_empty', '' ) )->toBeTrue();
            expect( $this->service->compareValues( ['item'], 'is_not_empty', '' ) )->toBeTrue();
            expect( $this->service->compareValues( '', 'is_not_empty', '' ) )->toBeFalse();
        } );

        it( 'compares greater_than correctly', function (): void {
            expect( $this->service->compareValues( 10, 'greater_than', '5' ) )->toBeTrue();
            expect( $this->service->compareValues( 5, 'greater_than', '5' ) )->toBeFalse();
            expect( $this->service->compareValues( 3, 'greater_than', '5' ) )->toBeFalse();
            expect( $this->service->compareValues( 'not a number', 'greater_than', '5' ) )->toBeFalse();
        } );

        it( 'compares less_than correctly', function (): void {
            expect( $this->service->compareValues( 3, 'less_than', '5' ) )->toBeTrue();
            expect( $this->service->compareValues( 5, 'less_than', '5' ) )->toBeFalse();
            expect( $this->service->compareValues( 10, 'less_than', '5' ) )->toBeFalse();
        } );

        it( 'compares greater_or_equal correctly', function (): void {
            expect( $this->service->compareValues( 10, 'greater_or_equal', '5' ) )->toBeTrue();
            expect( $this->service->compareValues( 5, 'greater_or_equal', '5' ) )->toBeTrue();
            expect( $this->service->compareValues( 3, 'greater_or_equal', '5' ) )->toBeFalse();
        } );

        it( 'compares less_or_equal correctly', function (): void {
            expect( $this->service->compareValues( 3, 'less_or_equal', '5' ) )->toBeTrue();
            expect( $this->service->compareValues( 5, 'less_or_equal', '5' ) )->toBeTrue();
            expect( $this->service->compareValues( 10, 'less_or_equal', '5' ) )->toBeFalse();
        } );

        it( 'compares in correctly', function (): void {
            expect( $this->service->compareValues( 'apple', 'in', 'apple,banana,cherry' ) )->toBeTrue();
            expect( $this->service->compareValues( 'orange', 'in', 'apple,banana,cherry' ) )->toBeFalse();
        } );

        it( 'compares not_in correctly', function (): void {
            expect( $this->service->compareValues( 'orange', 'not_in', 'apple,banana,cherry' ) )->toBeTrue();
            expect( $this->service->compareValues( 'apple', 'not_in', 'apple,banana,cherry' ) )->toBeFalse();
        } );

        it( 'compares checked correctly', function (): void {
            expect( $this->service->compareValues( true, 'checked', '' ) )->toBeTrue();
            expect( $this->service->compareValues( 'true', 'checked', '' ) )->toBeTrue();
            expect( $this->service->compareValues( '1', 'checked', '' ) )->toBeTrue();
            expect( $this->service->compareValues( false, 'checked', '' ) )->toBeFalse();
        } );

        it( 'compares unchecked correctly', function (): void {
            expect( $this->service->compareValues( false, 'unchecked', '' ) )->toBeTrue();
            expect( $this->service->compareValues( 'false', 'unchecked', '' ) )->toBeTrue();
            expect( $this->service->compareValues( true, 'unchecked', '' ) )->toBeFalse();
        } );

        it( 'compares includes correctly for arrays', function (): void {
            expect( $this->service->compareValues( ['apple', 'banana'], 'includes', 'apple' ) )->toBeTrue();
            expect( $this->service->compareValues( ['apple', 'banana'], 'includes', 'cherry' ) )->toBeFalse();
            expect( $this->service->compareValues( 'not an array', 'includes', 'test' ) )->toBeFalse();
        } );

        it( 'compares not_includes correctly for arrays', function (): void {
            expect( $this->service->compareValues( ['apple', 'banana'], 'not_includes', 'cherry' ) )->toBeTrue();
            expect( $this->service->compareValues( ['apple', 'banana'], 'not_includes', 'apple' ) )->toBeFalse();
        } );

        it( 'returns true for unknown operator', function (): void {
            expect( $this->service->compareValues( 'test', 'unknown', 'test' ) )->toBeTrue();
        } );
    } );

    describe( 'getHiddenFields', function (): void {
        it( 'returns hidden state for all fields', function (): void {
            FormField::factory()->for( $this->form )->create( [
                'name'              => 'always_visible',
                'conditional_logic' => null,
            ] );

            FormField::factory()->for( $this->form )->create( [
                'name'              => 'conditional_field',
                'conditional_logic' => [
                    'action' => 'show',
                    'logic'  => 'all',
                    'rules'  => [
                        ['field' => 'always_visible', 'operator' => 'equals', 'value' => 'show'],
                    ],
                ],
            ] );

            $fields = $this->form->fields;

            // When condition is not met
            $hidden = $this->service->getHiddenFields( $fields, ['always_visible' => 'hide'] );

            expect( $hidden['always_visible'] )->toBeFalse()
                ->and( $hidden['conditional_field'] )->toBeTrue();

            // When condition is met
            $hidden = $this->service->getHiddenFields( $fields, ['always_visible' => 'show'] );

            expect( $hidden['always_visible'] )->toBeFalse()
                ->and( $hidden['conditional_field'] )->toBeFalse();
        } );
    } );

    describe( 'getAvailableConditionTargets', function (): void {
        it( 'excludes the field itself', function (): void {
            $field1 = FormField::factory()->for( $this->form )->create( ['name' => 'field1'] );
            $field2 = FormField::factory()->for( $this->form )->create( ['name' => 'field2'] );

            $targets = $this->service->getAvailableConditionTargets( $field1, $this->form->fields );

            expect( $targets->pluck( 'name' )->toArray() )->toContain( 'field2' )
                ->and( $targets->pluck( 'name' )->toArray() )->not->toContain( 'field1' );
        } );

        it( 'excludes layout fields', function (): void {
            $field1 = FormField::factory()->for( $this->form )->create( ['name' => 'field1', 'type' => 'text'] );
            FormField::factory()->for( $this->form )->create( ['name' => 'heading', 'type' => 'heading'] );

            $this->form->refresh();
            $targets = $this->service->getAvailableConditionTargets( $field1, $this->form->fields );

            expect( $targets->pluck( 'name' )->toArray() )->not->toContain( 'heading' );
        } );
    } );

    describe( 'cleanupDeletedFieldReferences', function (): void {
        it( 'removes rules referencing non-existent fields', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['name' => 'existing_field'] );

            $logic = [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [
                    ['field' => 'existing_field', 'operator' => 'equals', 'value' => 'test'],
                    ['field' => 'deleted_field', 'operator' => 'equals', 'value' => 'test'],
                ],
            ];

            $cleaned = $this->service->cleanupDeletedFieldReferences( $logic, $this->form->fields);

            expect( $cleaned['rules'])->toHaveCount( 1)
                ->and( $cleaned['rules'][0]['field'])->toBe( 'existing_field');
        });

        it( 'handles empty rules array', function (): void {
            $logic = [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [],
            ];

            $cleaned = $this->service->cleanupDeletedFieldReferences( $logic, $this->form->fields);

            expect( $cleaned['rules'])->toBeEmpty();
        });
    });
});
