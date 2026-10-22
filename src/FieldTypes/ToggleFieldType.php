<?php

/**
 * Toggle field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * On/off switch.
 *
 * Validates, stores and displays like a single checkbox: `boolean`, and
 * `accepted` when required. Only the rendering differs.
 *
 * @since 1.6.0
 */
class ToggleFieldType extends CheckboxFieldType
{
    /**
     * Switch sizes the renderers accept.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const SIZES = ['xs', 'sm', 'md', 'lg'];

    /**
     * daisyUI colors the renderers accept.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const COLORS = ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info'];

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'toggle';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['toggle'], [
            'label'    => __( 'Toggle' ),
            'defaults' => [
                'label' => __( 'Toggle' ),
            ],
            'settings' => [
                [
                    'key'     => 'size',
                    'input'   => 'select',
                    'default' => 'md',
                    'label'   => __( 'Size' ),
                    'options' => [
                        ['value' => 'xs', 'label' => __( 'Extra Small' )],
                        ['value' => 'sm', 'label' => __( 'Small' )],
                        ['value' => 'md', 'label' => __( 'Medium' )],
                        ['value' => 'lg', 'label' => __( 'Large' )],
                    ],
                ],
                [
                    'key'     => 'color',
                    'input'   => 'select',
                    'default' => 'primary',
                    'label'   => __( 'Color' ),
                    'options' => [
                        ['value' => 'primary', 'label' => __( 'Primary' )],
                        ['value' => 'secondary', 'label' => __( 'Secondary' )],
                        ['value' => 'accent', 'label' => __( 'Accent' )],
                        ['value' => 'success', 'label' => __( 'Success' )],
                        ['value' => 'warning', 'label' => __( 'Warning' )],
                        ['value' => 'error', 'label' => __( 'Error' )],
                        ['value' => 'info', 'label' => __( 'Info' )],
                    ],
                ],
                ['key' => 'checked_label', 'max_length' => 255, 'label' => __( 'On Text' ), 'placeholder' => __( 'Yes' ), 'hint' => __( 'Shown in submissions, notifications and exports.' )],
                ['key' => 'unchecked_label', 'max_length' => 255, 'label' => __( 'Off Text' ), 'placeholder' => __( 'No' )],
                $this->readOnlySetting(),
            ],
        ] );
    }
}
