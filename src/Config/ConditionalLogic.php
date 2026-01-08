<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Config;

/**
 * ConditionalLogic Configuration
 *
 * Defines the operators, actions, and logic types available
 * for conditional field visibility rules.
 *
 * @since 1.0.0
 */
class ConditionalLogic
{
    /**
     * Available condition actions.
     *
     * @var array<string, array{label: string, description: string}>
     */
    public const ACTIONS = [
        'show' => [
            'label'       => 'Show this field',
            'description' => 'Field is visible when conditions are met',
        ],
        'hide' => [
            'label'       => 'Hide this field',
            'description' => 'Field is hidden when conditions are met',
        ],
    ];

    /**
     * Available logic types for combining multiple rules.
     *
     * @var array<string, array{label: string, description: string}>
     */
    public const LOGIC_TYPES = [
        'all' => [
            'label'       => 'All conditions (AND)',
            'description' => 'All conditions must be true',
        ],
        'any' => [
            'label'       => 'Any condition (OR)',
            'description' => 'At least one condition must be true',
        ],
    ];

    /**
     * Available comparison operators with metadata.
     *
     * @var array<string, array{
     *     label: string,
     *     description: string,
     *     needs_value: bool,
     *     value_type: string,
     *     supports_types: array<string>
     * }>
     */
    public const OPERATORS = [
        'equals' => [
            'label'          => 'Equals',
            'description'    => 'Value exactly matches',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden', 'date', 'time'],
        ],
        'not_equals' => [
            'label'          => 'Does not equal',
            'description'    => 'Value does not match',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden', 'date', 'time'],
        ],
        'contains' => [
            'label'          => 'Contains',
            'description'    => 'Value contains the text',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'not_contains' => [
            'label'          => 'Does not contain',
            'description'    => 'Value does not contain the text',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'starts_with' => [
            'label'          => 'Starts with',
            'description'    => 'Value starts with the text',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'ends_with' => [
            'label'          => 'Ends with',
            'description'    => 'Value ends with the text',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'is_empty' => [
            'label'          => 'Is empty',
            'description'    => 'Field has no value',
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'textarea', 'select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple', 'file', 'date', 'time', 'hidden'],
        ],
        'is_not_empty' => [
            'label'          => 'Is not empty',
            'description'    => 'Field has a value',
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'textarea', 'select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple', 'file', 'date', 'time', 'hidden'],
        ],
        'greater_than' => [
            'label'          => 'Greater than',
            'description'    => 'Value is greater than',
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'less_than' => [
            'label'          => 'Less than',
            'description'    => 'Value is less than',
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'greater_or_equal' => [
            'label'          => 'Greater than or equal',
            'description'    => 'Value is greater than or equal to',
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'less_or_equal' => [
            'label'          => 'Less than or equal',
            'description'    => 'Value is less than or equal to',
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'in' => [
            'label'          => 'Is one of',
            'description'    => 'Value is in the list (comma-separated)',
            'needs_value'    => true,
            'value_type'     => 'list',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden'],
        ],
        'not_in' => [
            'label'          => 'Is not one of',
            'description'    => 'Value is not in the list (comma-separated)',
            'needs_value'    => true,
            'value_type'     => 'list',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden'],
        ],
        'checked' => [
            'label'          => 'Is checked',
            'description'    => 'Checkbox is checked',
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['checkbox'],
        ],
        'unchecked' => [
            'label'          => 'Is not checked',
            'description'    => 'Checkbox is not checked',
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['checkbox'],
        ],
        'includes' => [
            'label'          => 'Includes',
            'description'    => 'Selection includes the value',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['checkbox_group', 'select_multiple'],
        ],
        'not_includes' => [
            'label'          => 'Does not include',
            'description'    => 'Selection does not include the value',
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['checkbox_group', 'select_multiple'],
        ],
    ];

    /**
     * Get all available actions.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function getActions(): array
    {
        return self::ACTIONS;
    }

    /**
     * Get all available logic types.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function getLogicTypes(): array
    {
        return self::LOGIC_TYPES;
    }

    /**
     * Get all available operators.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getOperators(): array
    {
        return self::OPERATORS;
    }

    /**
     * Get operators that are compatible with a field type.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getOperatorsForType( string $fieldType ): array
    {
        return array_filter(
            self::OPERATORS,
            fn ( array $operator ) => in_array( $fieldType, $operator['supports_types'], true ),
        );
    }

    /**
     * Get operator configuration by key.
     *
     * @return array<string, mixed>|null
     */
    public static function getOperator( string $key ): ?array
    {
        return self::OPERATORS[ $key ] ?? null;
    }

    /**
     * Check if an operator exists.
     */
    public static function operatorExists( string $key ): bool
    {
        return isset( self::OPERATORS[ $key ] );
    }

    /**
     * Check if an operator requires a value.
     */
    public static function operatorNeedsValue( string $key ): bool
    {
        return self::OPERATORS[ $key ]['needs_value'] ?? true;
    }

    /**
     * Get the default conditional logic structure.
     *
     * @return array{action: string, logic: string, rules: array<int, array{field: string, operator: string, value: string}>}
     */
    public static function getDefaultStructure(): array
    {
        return [
            'action' => 'show',
            'logic'  => 'all',
            'rules'  => [],
        ];
    }

    /**
     * Create a new condition rule structure.
     *
     * @return array{field: string, operator: string, value: string}
     */
    public static function createRule( string $fieldName = '', string $operator = 'equals', string $value = '' ): array
    {
        return [
            'field'    => $fieldName,
            'operator' => $operator,
            'value'    => $value,
        ];
    }

    /**
     * Validate a conditional logic structure.
     *
     * @param  array<string, mixed>  $logic
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validate( array $logic ): array
    {
        $errors = [];

        // Check action
        if ( ! isset( $logic['action'] ) || ! isset( self::ACTIONS[ $logic['action'] ] ) ) {
            $errors[] = 'Invalid action. Must be "show" or "hide".';
        }

        // Check logic type
        if ( ! isset( $logic['logic'] ) || ! isset( self::LOGIC_TYPES[ $logic['logic'] ] ) ) {
            $errors[] = 'Invalid logic type. Must be "all" or "any".';
        }

        // Check rules
        if ( ! isset( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
            $errors[] = 'Rules must be an array.';
        } else {
            foreach ( $logic['rules'] as $index => $rule ) {
                if ( ! isset( $rule['field'] ) || empty( $rule['field'] ) ) {
                    $errors[] = "Rule {$index}: Field is required.";
                }

                if ( ! isset( $rule['operator'] ) || ! self::operatorExists( $rule['operator'] ) ) {
                    $errors[] = "Rule {$index}: Invalid operator.";
                } elseif ( self::operatorNeedsValue( $rule['operator'] ) && ! isset( $rule['value'] ) ) {
                    $errors[] = "Rule {$index}: Value is required for operator '{$rule['operator']}'.";
                }
            }
        }

        return [
            'valid'  => empty( $errors),
            'errors' => $errors,
        ];
    }
}
