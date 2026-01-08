<?php

/**
 * Conditional logic service.
 *
 * Handles server-side evaluation of conditional logic rules
 * to determine field visibility based on form data values.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Database\Eloquent\Collection;

/**
 * Conditional logic service class.
 *
 * Handles server-side evaluation of conditional logic rules
 * to determine field visibility based on form data values.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class ConditionalLogicService
{
    /**
     * Field name to UUID mapping for the current form.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected array $fieldNameToUuid = [];

    /**
     * UUID to field name mapping for the current form.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected array $uuidToFieldName = [];

    /**
     * Evaluates conditional logic for all fields in a form.
     *
     * @since 1.0.0
     *
     * @param Collection<int, FormField> $fields   The form fields.
     * @param array<string, mixed>        $formData The form data.
     *
     * @return array<string, bool> Map of field names to visibility (true = visible).
     */
    public function evaluateAllFields( Collection $fields, array $formData ): array
    {
        $this->buildFieldMaps( $fields );

        $visibility = [];

        foreach ( $fields as $field ) {
            $visibility[ $field->name ] = $this->evaluateField( $field, $formData );
        }

        return $visibility;
    }

    /**
     * Gets hidden fields (inverse of visibility).
     *
     * @since 1.0.0
     *
     * @param Collection<int, FormField> $fields   The form fields.
     * @param array<string, mixed>        $formData The form data.
     *
     * @return array<string, bool> Map of field names to hidden state (true = hidden).
     */
    public function getHiddenFields( Collection $fields, array $formData ): array
    {
        $visibility = $this->evaluateAllFields( $fields, $formData );

        return array_map( fn ( bool $visible ) => ! $visible, $visibility );
    }

    /**
     * Evaluates conditional logic for a single field.
     *
     * @since 1.0.0
     *
     * @param FormField            $field    The field to evaluate.
     * @param array<string, mixed> $formData The form data.
     *
     * @return bool True if the field should be visible.
     */
    public function evaluateField( FormField $field, array $formData ): bool
    {
        // If no conditional logic, field is always visible
        if ( ! $field->has_conditional_logic ) {
            return true;
        }

        $logic = $field->conditional_logic;

        if ( empty( $logic['rules'] ) ) {
            return true;
        }

        $action    = $logic['action'] ?? 'show';
        $logicType = $logic['logic'] ?? 'all';
        $rules     = $logic['rules'];

        // Evaluate all rules
        $results = [];
        foreach ( $rules as $rule ) {
            $results[] = $this->evaluateRule( $rule, $formData );
        }

        // Combine results based on logic type
        $conditionsMet = 'all' === $logicType
            ? ! in_array( false, $results, true )  // All must be true
            : in_array( true, $results, true );     // At least one must be true

        // Determine visibility based on action
        // 'show': visible when conditions are met
        // 'hide': visible when conditions are NOT met
        return 'show' === $action ? $conditionsMet : ! $conditionsMet;
    }

    /**
     * Evaluates a single condition rule.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $rule     The rule to evaluate.
     * @param array<string, mixed> $formData The form data.
     *
     * @return bool True if the rule condition is met.
     */
    public function evaluateRule( array $rule, array $formData ): bool
    {
        $fieldRef  = $rule['field'] ?? null;
        $operator  = $rule['operator'] ?? 'equals';
        $ruleValue = $rule['value'] ?? '';

        if ( ! $fieldRef ) {
            return true; // No field reference, condition is met
        }

        // Resolve field name (could be UUID or field name)
        $fieldName  = $this->resolveFieldName( $fieldRef );
        $fieldValue = $formData[ $fieldName ] ?? null;

        return $this->compareValues( $fieldValue, $operator, $ruleValue );
    }

    /**
     * Compares field value against rule value using the specified operator.
     *
     * @since 1.0.0
     *
     * @param mixed  $fieldValue The field value.
     * @param string $operator   The comparison operator.
     * @param mixed  $ruleValue  The rule value to compare against.
     *
     * @return bool True if the comparison passes.
     */
    public function compareValues( $fieldValue, string $operator, $ruleValue ): bool
    {
        return match ( $operator ) {
            'equals'           => $this->compareEquals( $fieldValue, $ruleValue ),
            'not_equals'       => ! $this->compareEquals( $fieldValue, $ruleValue ),
            'contains'         => $this->compareContains( $fieldValue, $ruleValue ),
            'not_contains'     => ! $this->compareContains( $fieldValue, $ruleValue ),
            'starts_with'      => $this->compareStartsWith( $fieldValue, $ruleValue ),
            'ends_with'        => $this->compareEndsWith( $fieldValue, $ruleValue ),
            'is_empty'         => $this->isEmpty( $fieldValue ),
            'is_not_empty'     => ! $this->isEmpty( $fieldValue ),
            'greater_than'     => $this->compareGreaterThan( $fieldValue, $ruleValue ),
            'less_than'        => $this->compareLessThan( $fieldValue, $ruleValue ),
            'greater_or_equal' => $this->compareGreaterOrEqual( $fieldValue, $ruleValue ),
            'less_or_equal'    => $this->compareLessOrEqual( $fieldValue, $ruleValue ),
            'in'               => $this->compareIn( $fieldValue, $ruleValue ),
            'not_in'           => ! $this->compareIn( $fieldValue, $ruleValue ),
            'checked'          => $this->isChecked( $fieldValue ),
            'unchecked'        => ! $this->isChecked( $fieldValue ),
            'includes'         => $this->compareIncludes( $fieldValue, $ruleValue ),
            'not_includes'     => ! $this->compareIncludes( $fieldValue, $ruleValue ),
            default            => true,
        };
    }

    /**
     * Gets list of fields that can be used as condition targets for a given field.
     *
     * This filters out:
     * - The field itself (can't reference itself)
     * - Layout fields (they don't have values)
     * - Fields in later steps (for multi-step forms)
     *
     * @since 1.0.0
     *
     * @param FormField                   $field     The field to get targets for.
     * @param Collection<int, FormField>  $allFields All form fields.
     *
     * @return Collection<int, FormField> The available target fields.
     */
    public function getAvailableConditionTargets( FormField $field, Collection $allFields ): Collection
    {
        return $allFields->filter( function ( FormField $targetField ) use ( $field ) {
            // Can't reference itself
            if ( $targetField->id === $field->id ) {
                return false;
            }

            // Can't reference layout fields
            if ( $targetField->isLayoutField() ) {
                return false;
            }

            // For multi-step forms, only allow fields from same or previous steps
            if ( null !== $field->step_id && null !== $targetField->step_id ) {
                // Get step sort orders
                $fieldStepOrder  = $field->step->sort_order ?? 0;
                $targetStepOrder = $targetField->step->sort_order ?? 0;

                // Can only reference fields from same or previous steps
                if ( $targetStepOrder > $fieldStepOrder ) {
                    return false;
                }
            }

            return true;
        } );
    }

    /**
     * Detects circular dependencies in conditional logic.
     *
     * @since 1.0.0
     *
     * @param Collection<int, FormField> $fields The form fields to check.
     *
     * @return array<string, array<string>> Map of field names to their circular dependency chains.
     */
    public function detectCircularDependencies( Collection $fields ): array
    {
        $this->buildFieldMaps( $fields );

        // Build dependency graph
        $dependencies = [];
        foreach ( $fields as $field ) {
            if ( $field->has_conditional_logic ) {
                $rules                        = $field->conditional_logic['rules'] ?? [];
                $dependencies[ $field->name ] = array_map(
                    fn ( $rule ) => $this->resolveFieldName( $rule['field'] ?? '' ),
                    $rules,
                );
            } else {
                $dependencies[ $field->name ] = [];
            }
        }

        // Detect cycles using DFS
        $circular = [];
        foreach ( array_keys( $dependencies ) as $fieldName ) {
            $visited = [];
            $path    = [];

            if ( $this->hasCycle( $fieldName, $dependencies, $visited, $path ) ) {
                $circular[ $fieldName ] = $path;
            }
        }

        return $circular;
    }

    /**
     * Cleans up conditional logic rules that reference deleted fields.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed>       $logic           The logic structure to clean.
     * @param Collection<int, FormField> $availableFields The available fields.
     *
     * @return array<string, mixed> Cleaned logic structure.
     */
    public function cleanupDeletedFieldReferences( array $logic, Collection $availableFields ): array
    {
        if ( empty( $logic['rules'] ) ) {
            return $logic;
        }

        $this->buildFieldMaps( $availableFields );

        $validFieldRefs = array_merge(
            array_keys( $this->fieldNameToUuid ),
            array_keys( $this->uuidToFieldName ),
        );

        $logic['rules'] = array_filter(
            $logic['rules'],
            fn ( $rule ) => in_array( $rule['field'] ?? '', $validFieldRefs, true ),
        );

        // Re-index array
        $logic['rules'] = array_values( $logic['rules'] );

        return $logic;
    }

    /**
     * Builds field name/UUID mappings.
     *
     * @since 1.0.0
     *
     * @param Collection<int, FormField> $fields The form fields.
     *
     * @return void
     */
    protected function buildFieldMaps( Collection $fields ): void
    {
        $this->fieldNameToUuid = [];
        $this->uuidToFieldName = [];

        foreach ( $fields as $field ) {
            $this->fieldNameToUuid[ $field->name ] = $field->uuid;
            $this->uuidToFieldName[ $field->uuid ] = $field->name;
        }
    }

    /**
     * Resolves a field reference to a field name.
     *
     * The reference could be a UUID or a field name.
     *
     * @since 1.0.0
     *
     * @param string $fieldRef The field reference (UUID or name).
     *
     * @return string The resolved field name.
     */
    protected function resolveFieldName( string $fieldRef ): string
    {
        // Check if it's a UUID that we can resolve
        if ( isset( $this->uuidToFieldName[ $fieldRef ] ) ) {
            return $this->uuidToFieldName[ $fieldRef ];
        }

        // Assume it's already a field name
        return $fieldRef;
    }

    /**
     * Checks if two values are equal.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if values are equal.
     */
    protected function compareEquals( $fieldValue, $ruleValue ): bool
    {
        // Handle boolean comparison
        if ( is_bool( $fieldValue ) ) {
            return $fieldValue === filter_var( $ruleValue, FILTER_VALIDATE_BOOLEAN );
        }

        // Handle numeric comparison
        if ( is_numeric( $fieldValue ) && is_numeric( $ruleValue ) ) {
            return (float) $fieldValue === (float) $ruleValue;
        }

        // String comparison (case-sensitive)
        return (string) $fieldValue === (string) $ruleValue;
    }

    /**
     * Checks if field value contains the rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field contains the value.
     */
    protected function compareContains( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $fieldValue ) || ! is_string( $ruleValue ) ) {
            return false;
        }

        return str_contains( $fieldValue, $ruleValue );
    }

    /**
     * Checks if field value starts with the rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field starts with the value.
     */
    protected function compareStartsWith( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $fieldValue ) || ! is_string( $ruleValue ) ) {
            return false;
        }

        return str_starts_with( $fieldValue, $ruleValue );
    }

    /**
     * Checks if field value ends with the rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field ends with the value.
     */
    protected function compareEndsWith( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $fieldValue ) || ! is_string( $ruleValue ) ) {
            return false;
        }

        return str_ends_with( $fieldValue, $ruleValue );
    }

    /**
     * Checks if a value is empty.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to check.
     *
     * @return bool True if the value is empty.
     */
    protected function isEmpty( $value ): bool
    {
        if ( null === $value ) {
            return true;
        }

        if ( is_string( $value ) ) {
            return '' === trim( $value );
        }

        if ( is_array( $value ) ) {
            return empty( $value );
        }

        return empty( $value );
    }

    /**
     * Checks if field value is greater than rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field is greater than value.
     */
    protected function compareGreaterThan( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue > (float) $ruleValue;
    }

    /**
     * Checks if field value is less than rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field is less than value.
     */
    protected function compareLessThan( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue < (float) $ruleValue;
    }

    /**
     * Checks if field value is greater than or equal to rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field is greater than or equal to value.
     */
    protected function compareGreaterOrEqual( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue >= (float) $ruleValue;
    }

    /**
     * Checks if field value is less than or equal to rule value.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The rule value.
     *
     * @return bool True if field is less than or equal to value.
     */
    protected function compareLessOrEqual( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue <= (float) $ruleValue;
    }

    /**
     * Checks if field value is in a comma-separated list.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The field value.
     * @param mixed $ruleValue  The comma-separated list.
     *
     * @return bool True if field value is in the list.
     */
    protected function compareIn( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $ruleValue ) ) {
            return false;
        }

        $list = array_map( 'trim', explode( ',', $ruleValue ) );

        return in_array( (string) $fieldValue, $list, true );
    }

    /**
     * Checks if a checkbox is checked.
     *
     * @since 1.0.0
     *
     * @param mixed $value The checkbox value.
     *
     * @return bool True if the checkbox is checked.
     */
    protected function isChecked( $value ): bool
    {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            return in_array( strtolower( $value ), ['true', '1', 'yes', 'on'], true );
        }

        return (bool) $value;
    }

    /**
     * Checks if an array value includes a specific item.
     *
     * @since 1.0.0
     *
     * @param mixed $fieldValue The array field value.
     * @param mixed $ruleValue  The value to search for.
     *
     * @return bool True if the array includes the value.
     */
    protected function compareIncludes( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_array( $fieldValue ) ) {
            return false;
        }

        return in_array( (string) $ruleValue, array_map( 'strval', $fieldValue ), true );
    }

    /**
     * Checks if a field has a circular dependency using depth-first search.
     *
     * @since 1.0.0
     *
     * @param string                       $fieldName    The field name to check.
     * @param array<string, array<string>> $dependencies The dependency graph.
     * @param array<string, bool>          $visited      Visited nodes tracker.
     * @param array<string>                $path         Current path tracker.
     *
     * @return bool True if a cycle is detected.
     */
    protected function hasCycle( string $fieldName, array $dependencies, array &$visited, array &$path ): bool
    {
        if ( isset( $visited[ $fieldName ] ) ) {
            // Found a cycle
            $path[] = $fieldName;

            return true;
        }

        $visited[ $fieldName ] = true;
        $path[]                = $fieldName;

        foreach ( $dependencies[ $fieldName ] ?? [] as $dependency ) {
            if ( $dependency && $this->hasCycle( $dependency, $dependencies, $visited, $path ) ) {
                return true;
            }
        }

        // Backtrack
        array_pop( $path);
        unset( $visited[ $fieldName ]);

        return false;
    }
}
