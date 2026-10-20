<?php

/**
 * Array definition field type adapter.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Models\FormField;

/**
 * Wraps a field type registered as an array through `ap.forms.fieldTypes`.
 *
 * Before 1.6.0 a custom type was an array definition whose `type_validation`
 * entry (a rule or list of rules) replaced the default `string` rule. That
 * format keeps working through this adapter.
 *
 * @since 1.6.0
 */
class ArrayFieldType extends AbstractFieldType
{
    /**
     * Creates the adapter.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The type name.
     * @param  array<string, mixed>  $typeDefinition  The array definition.
     */
    public function __construct( string $key, protected array $typeDefinition = [] )
    {
        parent::__construct( $key );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return $this->typeDefinition;
    }

    /**
     * Gets the definition's `type_validation` rules, or `string`.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<mixed> The rules.
     */
    protected function typeRules( FormField $field ): array
    {
        if ( ! isset( $this->typeDefinition['type_validation'] ) ) {
            return ['string'];
        }

        $rules = $this->typeDefinition['type_validation'];

        return is_array( $rules ) ? array_values( $rules ) : [$rules];
    }
}
