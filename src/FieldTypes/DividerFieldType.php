<?php

/**
 * Divider field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Horizontal rule between sections.
 *
 * @since 1.6.0
 */
class DividerFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'divider';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['divider'], [
            'label'    => __( 'Divider' ),
            'defaults' => [
                'label'        => __( 'Divider' ),
                'field_config' => [
                    'style'   => 'solid',
                    'spacing' => 'normal',
                ],
            ],
            'settings' => [
                [
                    'key'     => 'style',
                    'input'   => 'select',
                    'default' => 'solid',
                    'label'   => __( 'Line Style' ),
                    'options' => [
                        ['value' => 'solid', 'label' => __( 'Solid' )],
                        ['value' => 'dashed', 'label' => __( 'Dashed' )],
                        ['value' => 'dotted', 'label' => __( 'Dotted' )],
                    ],
                ],
                [
                    'key'     => 'spacing',
                    'input'   => 'select',
                    'default' => 'normal',
                    'label'   => __( 'Spacing' ),
                    'options' => [
                        ['value' => 'small', 'label' => __( 'Small' )],
                        ['value' => 'normal', 'label' => __( 'Normal' )],
                        ['value' => 'large', 'label' => __( 'Large' )],
                    ],
                ],
            ],
        ] );
    }
}
