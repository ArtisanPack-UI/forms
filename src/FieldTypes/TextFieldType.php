<?php

/**
 * Text field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Single-line text input.
 *
 * @since 1.6.0
 */
class TextFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'text';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['text'], [
            'label'    => __( 'Text Input' ),
            'defaults' => [
                'label'       => __( 'Text Field' ),
                'placeholder' => '',
            ],
            'settings' => [
                ...$this->lengthSettings(),
                $this->patternSetting(),
                $this->noDuplicatesSetting(),
                $this->readOnlySetting(),
            ],
        ] );
    }
}
