<?php

/**
 * Phone field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Telephone number input.
 *
 * @since 1.6.0
 */
class PhoneFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'phone';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['phone'], [
            'label'    => __( 'Phone Number' ),
            'defaults' => [
                'label'       => __( 'Phone Number' ),
                'placeholder' => '(123) 456-7890',
            ],
            'settings' => [
                $this->patternSetting(),
                $this->noDuplicatesSetting(),
                $this->readOnlySetting(),
            ],
        ] );
    }
}
