<?php

/**
 * Multi-select field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Dropdown of options, any number selectable.
 *
 * @since 1.6.0
 */
class SelectMultipleFieldType extends MultipleChoiceFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'select_multiple';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['select_multiple'], [
            'label'    => __( 'Multi-Select' ),
            'defaults' => [
                'label'        => __( 'Select Multiple Options' ),
                'field_config' => [
                    'options' => [],
                ],
            ],
            'settings' => $this->selectionSettings(),
        ] );
    }
}
