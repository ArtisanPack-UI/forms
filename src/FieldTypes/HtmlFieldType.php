<?php

/**
 * HTML field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Admin-authored HTML, sanitized before it is shown.
 *
 * @since 1.6.0
 */
class HtmlFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'html';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['html'], [
            'label'    => __( 'Custom HTML' ),
            'defaults' => [
                'label'        => __( 'Custom Content' ),
                'field_config' => [
                    'content' => '',
                ],
            ],
        ] );
    }
}
