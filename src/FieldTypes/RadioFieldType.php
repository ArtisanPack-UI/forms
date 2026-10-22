<?php

/**
 * Radio field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Radio buttons, one selectable.
 *
 * @since 1.6.0
 */
class RadioFieldType extends ChoiceFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'radio';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['radio'], [
            'label'    => __( 'Radio Buttons' ),
            'defaults' => [
                'label'        => __( 'Choose One' ),
                'field_config' => [
                    'options' => [],
                ],
            ],
            'settings' => [
                ...$this->otherChoiceSettings(),
                $this->readOnlySetting(),
            ],
        ] );
    }
}
