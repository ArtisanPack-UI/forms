<?php

/**
 * Select field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Dropdown of options, one selectable.
 *
 * @since 1.6.0
 */
class SelectFieldType extends ChoiceFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'select';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['select'], [
            'label'    => __( 'Dropdown Select' ),
            'defaults' => [
                'label'        => __( 'Select an Option' ),
                'placeholder'  => __( 'Choose...' ),
                'field_config' => [
                    'options' => [],
                ],
            ],
            'settings' => [
                $this->choicesSourceSetting(),
                $this->searchableSetting(),
                $this->readOnlySetting(),
            ],
        ] );
    }
}
