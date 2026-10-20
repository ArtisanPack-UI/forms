<?php

/**
 * Paragraph field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Block of descriptive text.
 *
 * @since 1.6.0
 */
class ParagraphFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'paragraph';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['paragraph'], [
            'label'    => __( 'Paragraph Text' ),
            'defaults' => [
                'label'        => __( 'Information' ),
                'field_config' => [
                    'content' => __( 'Add descriptive text here.' ),
                ],
            ],
        ] );
    }
}
