<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Database\Eloquent\Collection;

/**
 * ConditionalLogicService
 *
 * Handles server-side evaluation of conditional logic rules
 * to determine field visibility based on form data values.
 *
 * @since 1.0.0
 */
class ConditionalLogicService
{
    /**
     * Field name to UUID mapping for the current form.
     *
     * @var array<string, string>
     */
    protected array $fieldNameToUuid = [];

    /**
     * UUID to field name mapping for the current form.
     *
     * @var array<string, string>
     */
    protected array $uuidToFieldName = [];

    /**
     * Evaluate conditional logic for all fields in a form.
     *
     * @param  Collection<int, FormField>  $fields
     * @param  array<string, mixed>  $formData
     *
     * @return array<string, bool> Map of field names to visibility (true = visible)
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
     * Get hidden fields (inverse of visibility).
     *
     * @param  Collection<int, FormField>  $fields
     * @param  array<string, mixed>  $formData
     *
     * @return array<string, bool> Map of field names to hidden state (true = hidden)
     */
    public function getHiddenFields( Collection $fields, array $formData ): array
    {
        $visibility = $this->evaluateAllFields( $fields, $formData );

        return array_map( fn ( bool $visible ) => ! $visible, $visibility );
    }

    /**
     * Evaluate conditional logic for a single field.
     *
     * @param  array<string, mixed>  $formData
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
     * Evaluate a single condition rule.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $formData
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
     * Compare field value against rule value using the specified operator.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
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
     * Get list of fields that can be used as condition targets for a given field.
     *
     * This filters out:
     * - The field itself (can't reference itself)
     * - Layout fields (they don't have values)
     * - Fields in later steps (for multi-step forms)
     *
     * @param  Collection<int, FormField>  $allFields
     *
     * @return Collection<int, FormField>
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
     * Detect circular dependencies in conditional logic.
     *
     * @param  Collection<int, FormField>  $fields
     *
     * @return array<string, array<string>> Map of field names to their circular dependency chains
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
     * Clean up conditional logic rules that reference deleted fields.
     *
     * @param  array<string, mixed>  $logic
     * @param  Collection<int, FormField>  $availableFields
     *
     * @return array<string, mixed> Cleaned logic structure
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
     * Build field name/UUID mappings.
     *
     * @param  Collection<int, FormField>  $fields
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
     * Resolve a field reference to a field name.
     * The reference could be a UUID or a field name.
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
     * Check if two values are equal.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
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
     * Check if field value contains the rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareContains( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $fieldValue ) || ! is_string( $ruleValue ) ) {
            return false;
        }

        return str_contains( $fieldValue, $ruleValue );
    }

    /**
     * Check if field value starts with the rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareStartsWith( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $fieldValue ) || ! is_string( $ruleValue ) ) {
            return false;
        }

        return str_starts_with( $fieldValue, $ruleValue );
    }

    /**
     * Check if field value ends with the rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareEndsWith( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_string( $fieldValue ) || ! is_string( $ruleValue ) ) {
            return false;
        }

        return str_ends_with( $fieldValue, $ruleValue );
    }

    /**
     * Check if a value is empty.
     *
     * @param  mixed  $value
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
     * Check if field value is greater than rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareGreaterThan( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue > (float) $ruleValue;
    }

    /**
     * Check if field value is less than rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareLessThan( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue < (float) $ruleValue;
    }

    /**
     * Check if field value is greater than or equal to rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareGreaterOrEqual( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue >= (float) $ruleValue;
    }

    /**
     * Check if field value is less than or equal to rule value.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareLessOrEqual( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_numeric( $fieldValue ) || ! is_numeric( $ruleValue ) ) {
            return false;
        }

        return (float) $fieldValue <= (float) $ruleValue;
    }

    /**
     * Check if field value is in a comma-separated list.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
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
     * Check if a checkbox is checked.
     *
     * @param  mixed  $value
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
     * Check if an array value includes a specific item.
     *
     * @param  mixed  $fieldValue
     * @param  mixed  $ruleValue
     */
    protected function compareIncludes( $fieldValue, $ruleValue ): bool
    {
        if ( ! is_array( $fieldValue ) ) {
            return false;
        }

        return in_array( (string) $ruleValue, array_map( 'strval', $fieldValue ), true );
    }

    /**
     * Check if a field has a circular dependency using depth-first search.
     *
     * @param  array<string, array<string>>  $dependencies
     * @param  array<string, bool>  $visited
     * @param  array<string>  $path
     */
    protected function hasCycle( string $fieldName, array $dependencies, array &$visited, array &$path ): bool
    {
        if ( isset( $visited[ $fieldName ])) {
            // Found a cycle
            $path[] = $fieldName;

            return true;
        }

        $visited[ $fieldName ] = true;
        $path[]                = $fieldName;

        foreach ( $dependencies[ $fieldName ] ?? [] as $dependency) {
            if ( $dependency && $this->hasCycle( $dependency, $dependencies, $visited, $path)) {
                return true;
            }
        }

        // Backtrack
        array_pop( $path);
        unset( $visited[ $fieldName ]);

        return false;
    }
}
