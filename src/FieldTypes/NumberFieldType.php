<?php

/**
 * Number field type handler.
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
 * Numeric input.
 *
 * @since 1.6.0
 */
class NumberFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'number';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['number'], [
            'label'    => __( 'Number' ),
            'defaults' => [
                'label'       => __( 'Number' ),
                'placeholder' => '',
            ],
            'settings' => [
                ['key' => 'min', 'input' => 'number', 'store' => 'validation_rules', 'tab' => 'validation', 'step' => 'any', 'label' => __( 'Min Value' )],
                ['key' => 'max', 'input' => 'number', 'store' => 'validation_rules', 'tab' => 'validation', 'step' => 'any', 'label' => __( 'Max Value' )],
                [
                    'key'         => 'step',
                    'input'       => 'number',
                    'store'       => 'validation_rules',
                    'tab'         => 'validation',
                    'step'        => 'any',
                    'min'         => 0,
                    'label'       => __( 'Step Increment' ),
                    'placeholder' => '1',
                    'hint'        => __( 'e.g., 0.01 for decimals, 1 for integers' ),
                ],
                $this->noDuplicatesSetting(),
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
        return ['numeric'];
    }
}
