<?php

/**
 * Email field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Email address input.
 *
 * @since 1.6.0
 */
class EmailFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'email';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['email'], [
            'label'    => __( 'Email' ),
            'defaults' => [
                'label'       => __( 'Email Address' ),
                'placeholder' => __( 'you@example.com' ),
            ],
            'settings' => [
                ...$this->lengthSettings(),
                $this->noDuplicatesSetting(),
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['email'];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return ['email' => __( 'Please enter a valid email address for :field.', ['field' => $field->label] )];
    }
}
