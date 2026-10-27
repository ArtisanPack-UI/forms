<?php

/**
 * Name field type handler.
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
 * A person's name, in one of three formats.
 *
 * - `simple`: one `full` input (`autocomplete="name"`), the most inclusive
 *   format, since many names do not split into first and last.
 * - `normal` (the default): prefix, first, middle, last and suffix inputs,
 *   with only first and last shown until an admin turns the others on.
 * - `extended`: the same five inputs, all shown.
 *
 * The prefix is a select of the field's `prefix_choices`; the suffix is a
 * text input, or a select of `suffix_choices` when `suffix_input` is
 * `select`. The value is shown as the filled parts joined with spaces.
 *
 * @since 1.6.0
 */
class NameFieldType extends CompositeFieldType
{
    /**
     * The name formats.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const FORMATS = ['simple', 'normal', 'extended'];

    /**
     * The format a field uses until an admin picks one.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const DEFAULT_FORMAT = 'normal';

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'name';

    /**
     * Gets the format a field uses.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The name field.
     *
     * @return string `simple`, `normal` or `extended`.
     */
    public static function nameFormat( FormField $field ): string
    {
        $format = $field->getConfig( 'name_format' );

        return is_string( $format ) && in_array( $format, self::FORMATS, true ) ? $format : self::DEFAULT_FORMAT;
    }

    /**
     * Gets the prefixes a field offers.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The name field.
     *
     * @return list<string> The field's `prefix_choices`, or the default list.
     */
    public static function prefixChoices( FormField $field ): array
    {
        return self::choices( $field, 'prefix_choices', self::defaultPrefixes() );
    }

    /**
     * Gets the suffixes a field offers when its suffix is a select.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The name field.
     *
     * @return list<string> The field's `suffix_choices`, or the default list.
     */
    public static function suffixChoices( FormField $field ): array
    {
        return self::choices( $field, 'suffix_choices', self::defaultSuffixes() );
    }

    /**
     * Gets the default prefixes.
     *
     * @since 1.6.0
     *
     * @return list<string> The prefixes.
     */
    public static function defaultPrefixes(): array
    {
        return [__( 'Mr.' ), __( 'Mrs.' ), __( 'Ms.' ), __( 'Mx.' ), __( 'Dr.' ), __( 'Prof.' ), __( 'Rev.' )];
    }

    /**
     * Gets the default suffixes.
     *
     * @since 1.6.0
     *
     * @return list<string> The suffixes.
     */
    public static function defaultSuffixes(): array
    {
        return [__( 'Jr.' ), __( 'Sr.' ), 'II', 'III', __( 'PhD' )];
    }

    /**
     * {@inheritDoc}
     *
     * One `full` input for the simple format; otherwise the five parts, with
     * prefix, middle and suffix off by default unless the format is
     * `extended`.
     *
     * @since 1.6.0
     */
    public function subInputDefinitions( FormField $field ): array
    {
        $format = self::nameFormat( $field );

        if ( 'simple' === $format ) {
            return [
                ['key' => 'full', 'label' => __( 'Name' ), 'autocomplete' => 'name', 'required' => true],
            ];
        }

        $extended = 'extended' === $format;
        $suffix   = ['key' => 'suffix', 'label' => __( 'Suffix' ), 'autocomplete' => 'honorific-suffix', 'enabled' => $extended];

        if ( 'select' === $field->getConfig( 'suffix_input' ) ) {
            $suffix['type']    = 'select';
            $suffix['options'] = self::suffixChoices( $field );
        }

        return [
            [
                'key'          => 'prefix',
                'label'        => __( 'Prefix' ),
                'autocomplete' => 'honorific-prefix',
                'type'         => 'select',
                'options'      => self::prefixChoices( $field ),
                'enabled'      => $extended,
            ],
            ['key' => 'first', 'label' => __( 'First' ), 'autocomplete' => 'given-name', 'required' => true],
            ['key' => 'middle', 'label' => __( 'Middle' ), 'autocomplete' => 'additional-name', 'enabled' => $extended],
            ['key' => 'last', 'label' => __( 'Last' ), 'autocomplete' => 'family-name', 'required' => true],
            $suffix,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['name'], [
            'label'            => __( 'Name' ),
            'format_separator' => ' ',
            'defaults'         => [
                'label' => __( 'Name' ),
            ],
            'sub_inputs' => [],
            'settings'   => [
                [
                    'key'     => 'name_format',
                    'input'   => 'select',
                    'default' => self::DEFAULT_FORMAT,
                    'label'   => __( 'Name Format' ),
                    'hint'    => __( 'Simple asks for the whole name in one input, which suits names that do not split into first and last.' ),
                    'options' => [
                        ['value' => 'simple', 'label' => __( 'Simple (one input)' )],
                        ['value' => 'normal', 'label' => __( 'Normal (first and last)' )],
                        ['value' => 'extended', 'label' => __( 'Extended (prefix, first, middle, last, suffix)' )],
                    ],
                ],
                [
                    'key'         => 'prefix_choices',
                    'input'       => 'list',
                    'label'       => __( 'Prefix Choices' ),
                    'placeholder' => implode( ', ', self::defaultPrefixes() ),
                    'hint'        => __( 'Comma-separated. Leave blank for the default list.' ),
                ],
                [
                    'key'     => 'suffix_input',
                    'input'   => 'select',
                    'default' => 'text',
                    'label'   => __( 'Suffix Input' ),
                    'options' => [
                        ['value' => 'text', 'label' => __( 'Free text' )],
                        ['value' => 'select', 'label' => __( 'Select from a list' )],
                    ],
                ],
                [
                    'key'         => 'suffix_choices',
                    'input'       => 'list',
                    'label'       => __( 'Suffix Choices' ),
                    'placeholder' => implode( ', ', self::defaultSuffixes() ),
                    'hint'        => __( 'Comma-separated. Used when the suffix is a select.' ),
                ],
            ],
        ] );
    }

    /**
     * Reads a list setting, falling back to a default list.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The name field.
     * @param  string  $key  The `field_config` key.
     * @param  list<string>  $default  The default list.
     *
     * @return list<string> The choices, trimmed and without blanks or repeats.
     */
    protected static function choices( FormField $field, string $key, array $default ): array
    {
        $configured = $field->getConfig( $key );
        $choices    = [];

        foreach ( is_array( $configured ) ? $configured : [] as $choice ) {
            if ( is_scalar( $choice ) && ! is_bool( $choice ) && '' !== trim( (string) $choice ) ) {
                $choices[] = trim( (string) $choice );
            }
        }

        return [] === $choices ? $default : array_values( array_unique( $choices ) );
    }
}
