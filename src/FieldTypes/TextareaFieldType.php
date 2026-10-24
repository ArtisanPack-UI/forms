<?php

/**
 * Text area field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Multi-line text input.
 *
 * @since 1.6.0
 */
class TextareaFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'textarea';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['textarea'], [
            'label'    => __( 'Text Area' ),
            'defaults' => [
                'label'        => __( 'Message' ),
                'placeholder'  => '',
                'field_config' => ['rows' => 4],
            ],
            'settings' => [
                ['key' => 'rows', 'input' => 'integer', 'min' => 1, 'max' => 50, 'default' => 4, 'placeholder' => '4', 'label' => __( 'Rows' )],
                $this->counterSetting(),
                ...$this->lengthSettings(),
                [
                    'key'   => 'max_words',
                    'input' => 'integer',
                    'store' => 'validation_rules',
                    'tab'   => 'validation',
                    'min'   => 1,
                    'label' => __( 'Max Words' ),
                    'hint'  => __( 'The counter counts words instead of characters when set.' ),
                ],
                $this->readOnlySetting(),
            ],
        ] );
    }
}
