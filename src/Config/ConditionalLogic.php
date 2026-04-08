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
     * Action keys.
     *
     * @since 1.0.0
     *
     * @var array<string>
     */
    public const ACTION_KEYS = ['show', 'hide'];

    /**
     * Logic type keys.
     *
     * @since 1.0.0
     *
     * @var array<string>
     */
    public const LOGIC_TYPE_KEYS = ['all', 'any'];

    /**
     * Operator metadata without translatable strings.
     *
     * @since 1.0.0
     *
     * @var array<string, array{
     *     needs_value: bool,
     *     value_type: string,
     *     supports_types: array<string>
     * }>
     */
    public const OPERATOR_METADATA = [
        'equals' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden', 'date', 'time'],
        ],
        'not_equals' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden', 'date', 'time'],
        ],
        'contains' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'not_contains' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'starts_with' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'ends_with' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['text', 'email', 'url', 'phone', 'textarea'],
        ],
        'is_empty' => [
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'textarea', 'select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple', 'file', 'date', 'time', 'hidden'],
        ],
        'is_not_empty' => [
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'textarea', 'select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple', 'file', 'date', 'time', 'hidden'],
        ],
        'greater_than' => [
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'less_than' => [
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'greater_or_equal' => [
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'less_or_equal' => [
            'needs_value'    => true,
            'value_type'     => 'number',
            'supports_types' => ['number'],
        ],
        'in' => [
            'needs_value'    => true,
            'value_type'     => 'list',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden'],
        ],
        'not_in' => [
            'needs_value'    => true,
            'value_type'     => 'list',
            'supports_types' => ['text', 'email', 'number', 'url', 'phone', 'select', 'radio', 'hidden'],
        ],
        'checked' => [
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['checkbox'],
        ],
        'unchecked' => [
            'needs_value'    => false,
            'value_type'     => 'none',
            'supports_types' => ['checkbox'],
        ],
        'includes' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['checkbox_group', 'select_multiple'],
        ],
        'not_includes' => [
            'needs_value'    => true,
            'value_type'     => 'text',
            'supports_types' => ['checkbox_group', 'select_multiple'],
        ],
    ];

    /**
     * Gets available condition actions with translated labels.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, description: string}> The available actions.
     */
    public static function actions(): array
    {
        return [
            'show' => [
                'label'       => __( 'Show this field' ),
                'description' => __( 'Field is visible when conditions are met' ),
            ],
            'hide' => [
                'label'       => __( 'Hide this field' ),
                'description' => __( 'Field is hidden when conditions are met' ),
            ],
        ];
    }

    /**
     * Gets available logic types with translated labels.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, description: string}> The available logic types.
     */
    public static function logicTypes(): array
    {
        return [
            'all' => [
                'label'       => __( 'All conditions (AND)' ),
                'description' => __( 'All conditions must be true' ),
            ],
            'any' => [
                'label'       => __( 'Any condition (OR)' ),
                'description' => __( 'At least one condition must be true' ),
            ],
        ];
    }

    /**
     * Gets available comparison operators with translated labels.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The available operators.
     */
    public static function operators(): array
    {
        return [
            'equals' => array_merge( self::OPERATOR_METADATA['equals'], [
                'label'       => __( 'Equals' ),
                'description' => __( 'Value exactly matches' ),
            ] ),
            'not_equals' => array_merge( self::OPERATOR_METADATA['not_equals'], [
                'label'       => __( 'Does not equal' ),
                'description' => __( 'Value does not match' ),
            ] ),
            'contains' => array_merge( self::OPERATOR_METADATA['contains'], [
                'label'       => __( 'Contains' ),
                'description' => __( 'Value contains the text' ),
            ] ),
            'not_contains' => array_merge( self::OPERATOR_METADATA['not_contains'], [
                'label'       => __( 'Does not contain' ),
                'description' => __( 'Value does not contain the text' ),
            ] ),
            'starts_with' => array_merge( self::OPERATOR_METADATA['starts_with'], [
                'label'       => __( 'Starts with' ),
                'description' => __( 'Value starts with the text' ),
            ] ),
            'ends_with' => array_merge( self::OPERATOR_METADATA['ends_with'], [
                'label'       => __( 'Ends with' ),
                'description' => __( 'Value ends with the text' ),
            ] ),
            'is_empty' => array_merge( self::OPERATOR_METADATA['is_empty'], [
                'label'       => __( 'Is empty' ),
                'description' => __( 'Field has no value' ),
            ] ),
            'is_not_empty' => array_merge( self::OPERATOR_METADATA['is_not_empty'], [
                'label'       => __( 'Is not empty' ),
                'description' => __( 'Field has a value' ),
            ] ),
            'greater_than' => array_merge( self::OPERATOR_METADATA['greater_than'], [
                'label'       => __( 'Greater than' ),
                'description' => __( 'Value is greater than' ),
            ] ),
            'less_than' => array_merge( self::OPERATOR_METADATA['less_than'], [
                'label'       => __( 'Less than' ),
                'description' => __( 'Value is less than' ),
            ] ),
            'greater_or_equal' => array_merge( self::OPERATOR_METADATA['greater_or_equal'], [
                'label'       => __( 'Greater than or equal' ),
                'description' => __( 'Value is greater than or equal to' ),
            ] ),
            'less_or_equal' => array_merge( self::OPERATOR_METADATA['less_or_equal'], [
                'label'       => __( 'Less than or equal' ),
                'description' => __( 'Value is less than or equal to' ),
            ] ),
            'in' => array_merge( self::OPERATOR_METADATA['in'], [
                'label'       => __( 'Is one of' ),
                'description' => __( 'Value is in the list (comma-separated)' ),
            ] ),
            'not_in' => array_merge( self::OPERATOR_METADATA['not_in'], [
                'label'       => __( 'Is not one of' ),
                'description' => __( 'Value is not in the list (comma-separated)' ),
            ] ),
            'checked' => array_merge( self::OPERATOR_METADATA['checked'], [
                'label'       => __( 'Is checked' ),
                'description' => __( 'Checkbox is checked' ),
            ] ),
            'unchecked' => array_merge( self::OPERATOR_METADATA['unchecked'], [
                'label'       => __( 'Is not checked' ),
                'description' => __( 'Checkbox is not checked' ),
            ] ),
            'includes' => array_merge( self::OPERATOR_METADATA['includes'], [
                'label'       => __( 'Includes' ),
                'description' => __( 'Selection includes the value' ),
            ] ),
            'not_includes' => array_merge( self::OPERATOR_METADATA['not_includes'], [
                'label'       => __( 'Does not include' ),
                'description' => __( 'Selection does not include the value' ),
            ] ),
        ];
    }

    /**
     * Gets all available actions.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, description: string}> The available actions.
     */
    public static function getActions(): array
    {
        return self::actions();
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
        return self::logicTypes();
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
        return self::operators();
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
            self::operators(),
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
        $operators = self::operators();

        return $operators[ $key ] ?? null;
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
        return isset( self::OPERATOR_METADATA[ $key ] );
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
        return self::OPERATOR_METADATA[ $key ]['needs_value'] ?? true;
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
        if ( ! isset( $logic['action'] ) || ! in_array( $logic['action'], self::ACTION_KEYS, true ) ) {
            $errors[] = __( 'Invalid action. Must be "show" or "hide".' );
        }

        // Check logic type
        if ( ! isset( $logic['logic'] ) || ! in_array( $logic['logic'], self::LOGIC_TYPE_KEYS, true ) ) {
            $errors[] = __( 'Invalid logic type. Must be "all" or "any".' );
        }

        // Check rules
        if ( ! isset( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
            $errors[] = __( 'Rules must be an array.' );
        } else {
            foreach ( $logic['rules'] as $index => $rule ) {
                if ( ! isset( $rule['field'] ) || empty( $rule['field'] ) ) {
                    $errors[] = __( 'Rule :index: Field is required.', ['index' => $index] );
                }

                if ( ! isset( $rule['operator'] ) || ! self::operatorExists( $rule['operator'] ) ) {
                    $errors[] = __( 'Rule :index: Invalid operator.', ['index' => $index] );
                } elseif ( self::operatorNeedsValue( $rule['operator'] ) && ! isset( $rule['value'] ) ) {
                    $errors[] = __( 'Rule :index: Value is required for operator \':operator\'.', ['index' => $index, 'operator' => $rule['operator']] );
                }
            }
        }

        return [
            'valid'  => empty( $errors ),
            'errors' => $errors,
        ];
    }
}
