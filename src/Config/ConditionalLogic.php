<?php

/**
 * Conditional logic configuration.
 *
 * Defines the operators, actions, and logic types available
 * for conditional field visibility rules in the form builder.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Config;

/**
 * Conditional logic configuration class.
 *
 * Provides static methods and constants for conditional logic operations
 * including available operators, actions, and validation utilities.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class ConditionalLogic
{
    /**
     * Available condition actions.
     *
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * Gets all available actions.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, description: string}> The available actions.
     */
    public static function getActions(): array
    {
        return self::ACTIONS;
    }

    /**
     * Gets all available logic types.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, description: string}> The available logic types.
     */
    public static function getLogicTypes(): array
    {
        return self::LOGIC_TYPES;
    }

    /**
     * Gets all available operators.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The available operators.
     */
    public static function getOperators(): array
    {
        return self::OPERATORS;
    }

    /**
     * Gets operators that are compatible with a field type.
     *
     * @since 1.0.0
     *
     * @param string $fieldType The field type to get operators for.
     *
     * @return array<string, array<string, mixed>> The compatible operators.
     */
    public static function getOperatorsForType( string $fieldType ): array
    {
        return array_filter(
            self::OPERATORS,
            fn ( array $operator ) => in_array( $fieldType, $operator['supports_types'], true ),
        );
    }

    /**
     * Gets operator configuration by key.
     *
     * @since 1.0.0
     *
     * @param string $key The operator key.
     *
     * @return array<string, mixed>|null The operator configuration or null if not found.
     */
    public static function getOperator( string $key ): ?array
    {
        return self::OPERATORS[ $key ] ?? null;
    }

    /**
     * Checks if an operator exists.
     *
     * @since 1.0.0
     *
     * @param string $key The operator key.
     *
     * @return bool True if the operator exists.
     */
    public static function operatorExists( string $key ): bool
    {
        return isset( self::OPERATORS[ $key ] );
    }

    /**
     * Checks if an operator requires a value.
     *
     * @since 1.0.0
     *
     * @param string $key The operator key.
     *
     * @return bool True if the operator requires a value.
     */
    public static function operatorNeedsValue( string $key ): bool
    {
        return self::OPERATORS[ $key ]['needs_value'] ?? true;
    }

    /**
     * Gets the default conditional logic structure.
     *
     * @since 1.0.0
     *
     * @return array{action: string, logic: string, rules: array<int, array{field: string, operator: string, value: string}>} The default structure.
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
     * Creates a new condition rule structure.
     *
     * @since 1.0.0
     *
     * @param string $fieldName The field name for the rule.
     * @param string $operator  The comparison operator.
     * @param string $value     The value to compare against.
     *
     * @return array{field: string, operator: string, value: string} The rule structure.
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
     * Validates a conditional logic structure.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $logic The conditional logic structure to validate.
     *
     * @return array{valid: bool, errors: array<string>} The validation result.
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
            'valid'  => empty( $errors ),
            'errors' => $errors,
        ];
    }
}
