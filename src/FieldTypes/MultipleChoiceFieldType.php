<?php

/**
 * Base handler for multiple-choice field types.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Models\FormField;

/**
 * A field whose value is a list of its options' values.
 *
 * @since 1.6.0
 */
abstract class MultipleChoiceFieldType extends ChoiceFieldType
{
    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['array'];
    }

    /**
     * Gets the minimum and maximum selection settings.
     *
     * @since 1.6.0
     *
     * @return list<array<string, mixed>> The settings.
     */
    protected function selectionSettings(): array
    {
        return [
            ['key' => 'min', 'input' => 'integer', 'store' => 'validation_rules', 'tab' => 'validation', 'min' => 0, 'label' => __( 'Min Selections' )],
            ['key' => 'max', 'input' => 'integer', 'store' => 'validation_rules', 'tab' => 'validation', 'min' => 0, 'label' => __( 'Max Selections' )],
        ];
    }
}
