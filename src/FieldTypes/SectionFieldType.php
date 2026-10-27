<?php

/**
 * Section field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;

/**
 * A group of fields with a title and description.
 *
 * Unlike a heading, a section groups the fields that follow it, up to the
 * next section or the end of the step (see Support\Sections): renderers wrap
 * them in a `<fieldset>` whose `<legend>` is the section's label, and
 * conditional logic on the section shows or hides every field in it. It may
 * be collapsible, and start collapsed.
 *
 * @since 1.6.0
 */
class SectionFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'section';

    /**
     * The longest description a section may have.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DESCRIPTION_MAX_LENGTH = 1000;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'section';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['section'], [
            'label'    => __( 'Section' ),
            'defaults' => [
                'label' => __( 'Section' ),
            ],
            'settings' => [
                [
                    'key'        => 'description',
                    'input'      => 'textarea',
                    'max_length' => self::DESCRIPTION_MAX_LENGTH,
                    'label'      => __( 'Description' ),
                    'hint'       => __( 'Shown under the section title. The section groups the fields after it, up to the next section.' ),
                ],
                [
                    'key'   => 'collapsible',
                    'input' => 'toggle',
                    'label' => __( 'Collapsible' ),
                    'hint'  => __( 'Visitors can show and hide the section\'s fields with a button in its title.' ),
                ],
                [
                    'key'   => 'collapsed',
                    'input' => 'toggle',
                    'label' => __( 'Start collapsed' ),
                    'hint'  => __( 'For collapsible sections. A section with an error opens.' ),
                ],
            ],
        ] );
    }
}
