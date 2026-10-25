<?php

/**
 * Heading field type handler.
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
 * Section heading. Its text is the field label; its level and (since 1.6.0)
 * alignment are settings.
 *
 * @since 1.6.0
 */
class HeadingFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'heading';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['heading'], [
            'label'    => __( 'Heading' ),
            'defaults' => [
                'label'        => __( 'Section Heading' ),
                'field_config' => [
                    'level' => 'h3',
                ],
            ],
            'settings' => [
                [
                    'key'     => 'level',
                    'input'   => 'select',
                    'default' => 'h3',
                    'label'   => __( 'Heading Level' ),
                    'options' => array_map(
                        fn ( string $level ): array => ['value' => $level, 'label' => strtoupper( $level )],
                        FormField::HEADING_LEVELS,
                    ),
                ],
                [
                    'key'     => 'align',
                    'input'   => 'select',
                    'default' => 'left',
                    'label'   => __( 'Alignment' ),
                    'options' => [
                        ['value' => 'left', 'label' => __( 'Left' )],
                        ['value' => 'center', 'label' => __( 'Center' )],
                        ['value' => 'right', 'label' => __( 'Right' )],
                    ],
                ],
            ],
        ] );
    }
}
