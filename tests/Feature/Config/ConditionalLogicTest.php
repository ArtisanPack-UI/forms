<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Config\ConditionalLogic;

describe('ConditionalLogic Config', function (): void {
    describe('actions', function (): void {
        it('returns available actions', function (): void {
            $actions = ConditionalLogic::getActions();

            expect($actions)->toHaveKeys(['show', 'hide'])
                ->and($actions['show'])->toHaveKeys(['label', 'description'])
                ->and($actions['hide'])->toHaveKeys(['label', 'description']);
        });
    });

    describe('logic types', function (): void {
        it('returns available logic types', function (): void {
            $types = ConditionalLogic::getLogicTypes();

            expect($types)->toHaveKeys(['all', 'any'])
                ->and($types['all']['label'])->toContain('AND')
                ->and($types['any']['label'])->toContain('OR');
        });
    });

    describe('operators', function (): void {
        it('returns all operators', function (): void {
            $operators = ConditionalLogic::getOperators();

            expect($operators)->toHaveKeys([
                'equals',
                'not_equals',
                'contains',
                'not_contains',
                'starts_with',
                'ends_with',
                'is_empty',
                'is_not_empty',
                'greater_than',
                'less_than',
                'greater_or_equal',
                'less_or_equal',
                'in',
                'not_in',
                'checked',
                'unchecked',
                'includes',
                'not_includes',
            ]);
        });

        it('checks if operator exists', function (): void {
            expect(ConditionalLogic::operatorExists('equals'))->toBeTrue()
                ->and(ConditionalLogic::operatorExists('invalid'))->toBeFalse();
        });

        it('checks if operator needs value', function (): void {
            expect(ConditionalLogic::operatorNeedsValue('equals'))->toBeTrue()
                ->and(ConditionalLogic::operatorNeedsValue('is_empty'))->toBeFalse()
                ->and(ConditionalLogic::operatorNeedsValue('checked'))->toBeFalse();
        });

        it('returns operators for specific field type', function (): void {
            $textOperators = ConditionalLogic::getOperatorsForType('text');
            $numberOperators = ConditionalLogic::getOperatorsForType('number');
            $checkboxOperators = ConditionalLogic::getOperatorsForType('checkbox');

            expect($textOperators)->toHaveKeys(['equals', 'contains', 'is_empty'])
                ->and($numberOperators)->toHaveKeys(['equals', 'greater_than', 'less_than'])
                ->and($checkboxOperators)->toHaveKeys(['checked', 'unchecked', 'is_empty']);
        });

        it('returns single operator by key', function (): void {
            $operator = ConditionalLogic::getOperator('equals');

            expect($operator)->not->toBeNull()
                ->and($operator['label'])->toBe('Equals')
                ->and($operator['needs_value'])->toBeTrue();
        });

        it('returns null for non-existent operator', function (): void {
            expect(ConditionalLogic::getOperator('invalid'))->toBeNull();
        });
    });

    describe('structure helpers', function (): void {
        it('returns default structure', function (): void {
            $structure = ConditionalLogic::getDefaultStructure();

            expect($structure)->toHaveKeys(['action', 'logic', 'rules'])
                ->and($structure['action'])->toBe('show')
                ->and($structure['logic'])->toBe('all')
                ->and($structure['rules'])->toBeArray()
                ->and($structure['rules'])->toBeEmpty();
        });

        it('creates rule structure', function (): void {
            $rule = ConditionalLogic::createRule('email', 'is_not_empty', '');

            expect($rule)->toHaveKeys(['field', 'operator', 'value'])
                ->and($rule['field'])->toBe('email')
                ->and($rule['operator'])->toBe('is_not_empty')
                ->and($rule['value'])->toBe('');
        });
    });

    describe('validation', function (): void {
        it('validates valid logic structure', function (): void {
            $logic = [
                'action' => 'show',
                'logic' => 'all',
                'rules' => [
                    ['field' => 'name', 'operator' => 'is_not_empty', 'value' => ''],
                ],
            ];

            $result = ConditionalLogic::validate($logic);

            expect($result['valid'])->toBeTrue()
                ->and($result['errors'])->toBeEmpty();
        });

        it('fails validation for invalid action', function (): void {
            $logic = [
                'action' => 'invalid',
                'logic' => 'all',
                'rules' => [],
            ];

            $result = ConditionalLogic::validate($logic);

            expect($result['valid'])->toBeFalse()
                ->and($result['errors'])->toContain('Invalid action. Must be "show" or "hide".');
        });

        it('fails validation for invalid logic type', function (): void {
            $logic = [
                'action' => 'show',
                'logic' => 'invalid',
                'rules' => [],
            ];

            $result = ConditionalLogic::validate($logic);

            expect($result['valid'])->toBeFalse()
                ->and($result['errors'])->toContain('Invalid logic type. Must be "all" or "any".');
        });

        it('fails validation for missing field in rule', function (): void {
            $logic = [
                'action' => 'show',
                'logic' => 'all',
                'rules' => [
                    ['field' => '', 'operator' => 'equals', 'value' => 'test'],
                ],
            ];

            $result = ConditionalLogic::validate($logic);

            expect($result['valid'])->toBeFalse()
                ->and($result['errors'][0])->toContain('Field is required');
        });

        it('fails validation for invalid operator', function (): void {
            $logic = [
                'action' => 'show',
                'logic' => 'all',
                'rules' => [
                    ['field' => 'name', 'operator' => 'invalid', 'value' => 'test'],
                ],
            ];

            $result = ConditionalLogic::validate($logic);

            expect($result['valid'])->toBeFalse()
                ->and($result['errors'][0])->toContain('Invalid operator');
        });

        it('fails validation when value required but missing', function (): void {
            $logic = [
                'action' => 'show',
                'logic' => 'all',
                'rules' => [
                    ['field' => 'name', 'operator' => 'equals'],
                ],
            ];

            $result = ConditionalLogic::validate($logic);

            expect($result['valid'])->toBeFalse()
                ->and($result['errors'][0])->toContain('Value is required');
        });
    });
});
