<?php

/**
 * Date field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Date picker.
 *
 * @since 1.6.0
 */
class DateFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'date';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['date'], [
            'label'    => __( 'Date Picker' ),
            'defaults' => [
                'label' => __( 'Select Date' ),
            ],
            'settings' => [
                ['key' => 'min_date', 'input' => 'date', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Date' )],
                ['key' => 'max_date', 'input' => 'date', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Date' )],
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['date'];
    }
}
