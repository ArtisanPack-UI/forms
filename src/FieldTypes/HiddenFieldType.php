<?php

/**
 * Hidden field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * Hidden input that submits its default value.
 *
 * @since 1.6.0
 */
class HiddenFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'hidden';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['hidden'], [
            'label'    => __( 'Hidden Field' ),
            'defaults' => [
                'label' => __( 'Hidden Field' ),
            ],
            'settings' => [
                $this->noDuplicatesSetting(),
            ],
        ] );
    }
}
