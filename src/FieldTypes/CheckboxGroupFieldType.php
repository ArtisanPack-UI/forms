<?php

/**
 * Checkbox group field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Checkboxes, any number selectable.
 *
 * @since 1.6.0
 */
class CheckboxGroupFieldType extends MultipleChoiceFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'checkbox_group';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['checkbox_group'], [
            'label'    => __( 'Checkbox Group' ),
            'defaults' => [
                'label'        => __( 'Select Options' ),
                'field_config' => [
                    'options' => [],
                ],
            ],
            'settings' => [
                $this->choicesSourceSetting(),
                $this->choiceLayoutSetting(),
                $this->randomizeSetting(),
                $this->selectAllSetting(),
                ...$this->otherChoiceSettings(),
                ...$this->selectionSettings(),
            ],
        ] );
    }
}
