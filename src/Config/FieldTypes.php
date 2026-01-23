<?php

/**
 * Field types configuration.
 *
 * Defines all available field types with their metadata, categories,
 * icons, validation options, and default values for the form builder.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Config;

/**
 * Field types configuration class.
 *
 * Provides static methods and constants for retrieving field type
 * definitions, categories, and metadata used throughout the form builder.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FieldTypes
{
    /**
     * Field type category keys for organizing the palette.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string>>
     */
    public const CATEGORY_FIELDS = [
        'basic'    => ['text', 'email', 'phone', 'number', 'url', 'textarea', 'hidden'],
        'choice'   => ['select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple'],
        'advanced' => ['file', 'date', 'time'],
        'layout'   => ['heading', 'paragraph', 'divider', 'html'],
    ];

    /**
     * Gets field type categories for organizing the palette.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, fields: array<string>}> The field categories.
     */
    public static function categories(): array
    {
        return [
            'basic' => [
                'label'  => __( 'Basic Fields' ),
                'fields' => self::CATEGORY_FIELDS['basic'],
            ],
            'choice' => [
                'label'  => __( 'Choice Fields' ),
                'fields' => self::CATEGORY_FIELDS['choice'],
            ],
            'advanced' => [
                'label'  => __( 'Advanced Fields' ),
                'fields' => self::CATEGORY_FIELDS['advanced'],
            ],
            'layout' => [
                'label'  => __( 'Layout Elements' ),
                'fields' => self::CATEGORY_FIELDS['layout'],
            ],
        ];
    }

    /**
     * Field type keys for metadata.
     *
     * @since 1.0.0
     *
     * @var array<string, array{
     *     icon: string,
     *     category: string,
     *     has_options: bool,
     *     supports_placeholder: bool,
     *     supports_default_value: bool,
     *     validation_options: array<string>
     * }>
     */
    public const TYPE_METADATA = [
        'text' => [
            'icon'                   => 'o-document-text',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => ['min', 'max', 'pattern'],
        ],
        'email' => [
            'icon'                   => 'o-envelope',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => ['min', 'max'],
        ],
        'number' => [
            'icon'                   => 'o-hashtag',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => ['min', 'max', 'step'],
        ],
        'url' => [
            'icon'                   => 'o-link',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => ['min', 'max'],
        ],
        'textarea' => [
            'icon'                   => 'o-bars-3-bottom-left',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => ['min', 'max'],
        ],
        'hidden' => [
            'icon'                   => 'o-eye-slash',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'select' => [
            'icon'                   => 'o-chevron-down',
            'category'               => 'choice',
            'has_options'            => true,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'radio' => [
            'icon'                   => 'o-stop-circle',
            'category'               => 'choice',
            'has_options'            => true,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'checkbox' => [
            'icon'                   => 'o-check-circle',
            'category'               => 'choice',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'checkbox_group' => [
            'icon'                   => 'o-list-bullet',
            'category'               => 'choice',
            'has_options'            => true,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'validation_options'     => ['min', 'max'],
        ],
        'select_multiple' => [
            'icon'                   => 'o-queue-list',
            'category'               => 'choice',
            'has_options'            => true,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'validation_options'     => ['min', 'max'],
        ],
        'file' => [
            'icon'                   => 'o-paper-clip',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'validation_options'     => ['max_size', 'allowed_types'],
        ],
        'date' => [
            'icon'                   => 'o-calendar',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => ['min_date', 'max_date'],
        ],
        'phone' => [
            'icon'                   => 'o-phone',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'validation_options'     => ['pattern'],
        ],
        'time' => [
            'icon'                   => 'o-clock',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => ['min_time', 'max_time'],
        ],
        'heading' => [
            'icon'                   => 'o-h1',
            'category'               => 'layout',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'is_layout'              => true,
            'validation_options'     => [],
        ],
        'paragraph' => [
            'icon'                   => 'o-document-text',
            'category'               => 'layout',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'is_layout'              => true,
            'validation_options'     => [],
        ],
        'divider' => [
            'icon'                   => 'o-minus',
            'category'               => 'layout',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'is_layout'              => true,
            'validation_options'     => [],
        ],
        'html' => [
            'icon'                   => 'o-code-bracket',
            'category'               => 'layout',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => false,
            'is_layout'              => true,
            'validation_options'     => [],
        ],
    ];

    /**
     * Gets field type definitions with translated labels.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The field type definitions.
     */
    public static function types(): array
    {
        return [
            'text' => array_merge( self::TYPE_METADATA['text'], [
                'label'    => __( 'Text Input' ),
                'defaults' => [
                    'label'       => __( 'Text Field' ),
                    'placeholder' => '',
                ],
            ] ),
            'email' => array_merge( self::TYPE_METADATA['email'], [
                'label'    => __( 'Email' ),
                'defaults' => [
                    'label'       => __( 'Email Address' ),
                    'placeholder' => __( 'you@example.com' ),
                ],
            ] ),
            'number' => array_merge( self::TYPE_METADATA['number'], [
                'label'    => __( 'Number' ),
                'defaults' => [
                    'label'       => __( 'Number' ),
                    'placeholder' => '',
                ],
            ] ),
            'url' => array_merge( self::TYPE_METADATA['url'], [
                'label'    => __( 'URL' ),
                'defaults' => [
                    'label'       => __( 'Website URL' ),
                    'placeholder' => 'https://example.com',
                ],
            ] ),
            'textarea' => array_merge( self::TYPE_METADATA['textarea'], [
                'label'    => __( 'Text Area' ),
                'defaults' => [
                    'label'        => __( 'Message' ),
                    'placeholder'  => '',
                    'field_config' => ['rows' => 4],
                ],
            ] ),
            'hidden' => array_merge( self::TYPE_METADATA['hidden'], [
                'label'    => __( 'Hidden Field' ),
                'defaults' => [
                    'label' => __( 'Hidden Field' ),
                ],
            ] ),
            'select' => array_merge( self::TYPE_METADATA['select'], [
                'label'    => __( 'Dropdown Select' ),
                'defaults' => [
                    'label'        => __( 'Select an Option' ),
                    'placeholder'  => __( 'Choose...' ),
                    'field_config' => [
                        'options' => [],
                    ],
                ],
            ] ),
            'radio' => array_merge( self::TYPE_METADATA['radio'], [
                'label'    => __( 'Radio Buttons' ),
                'defaults' => [
                    'label'        => __( 'Choose One' ),
                    'field_config' => [
                        'options' => [],
                    ],
                ],
            ] ),
            'checkbox' => array_merge( self::TYPE_METADATA['checkbox'], [
                'label'    => __( 'Single Checkbox' ),
                'defaults' => [
                    'label' => __( 'I agree to the terms' ),
                ],
            ] ),
            'checkbox_group' => array_merge( self::TYPE_METADATA['checkbox_group'], [
                'label'    => __( 'Checkbox Group' ),
                'defaults' => [
                    'label'        => __( 'Select Options' ),
                    'field_config' => [
                        'options' => [],
                    ],
                ],
            ] ),
            'select_multiple' => array_merge( self::TYPE_METADATA['select_multiple'], [
                'label'    => __( 'Multi-Select' ),
                'defaults' => [
                    'label'        => __( 'Select Multiple Options' ),
                    'field_config' => [
                        'options' => [],
                    ],
                ],
            ] ),
            'file' => array_merge( self::TYPE_METADATA['file'], [
                'label'    => __( 'File Upload' ),
                'defaults' => [
                    'label'        => __( 'Upload File' ),
                    'field_config' => [
                        'max_size'      => 5120,
                        'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'png'],
                    ],
                ],
            ] ),
            'date' => array_merge( self::TYPE_METADATA['date'], [
                'label'    => __( 'Date Picker' ),
                'defaults' => [
                    'label' => __( 'Select Date' ),
                ],
            ] ),
            'phone' => array_merge( self::TYPE_METADATA['phone'], [
                'label'    => __( 'Phone Number' ),
                'defaults' => [
                    'label'       => __( 'Phone Number' ),
                    'placeholder' => '(123) 456-7890',
                ],
            ] ),
            'time' => array_merge( self::TYPE_METADATA['time'], [
                'label'    => __( 'Time Picker' ),
                'defaults' => [
                    'label'        => __( 'Select Time' ),
                    'field_config' => [
                        'step' => 60,
                    ],
                ],
            ] ),
            'heading' => array_merge( self::TYPE_METADATA['heading'], [
                'label'    => __( 'Heading' ),
                'defaults' => [
                    'label'        => __( 'Section Heading' ),
                    'field_config' => [
                        'level' => 'h3',
                    ],
                ],
            ] ),
            'paragraph' => array_merge( self::TYPE_METADATA['paragraph'], [
                'label'    => __( 'Paragraph Text' ),
                'defaults' => [
                    'label'     => __( 'Information' ),
                    'help_text' => __( 'Add descriptive text here.' ),
                ],
            ] ),
            'divider' => array_merge( self::TYPE_METADATA['divider'], [
                'label'    => __( 'Divider' ),
                'defaults' => [
                    'label'        => __( 'Divider' ),
                    'field_config' => [
                        'style'   => 'solid',
                        'spacing' => 'normal',
                    ],
                ],
            ] ),
            'html' => array_merge( self::TYPE_METADATA['html'], [
                'label'    => __( 'Custom HTML' ),
                'defaults' => [
                    'label'        => __( 'Custom Content' ),
                    'field_config' => [
                        'content' => '',
                    ],
                ],
            ] ),
        ];
    }

    /**
     * Width option CSS classes.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const WIDTH_CLASSES = [
        'full'       => 'w-full',
        'half'       => 'w-1/2',
        'third'      => 'w-1/3',
        'two-thirds' => 'w-2/3',
    ];

    /**
     * Gets width options for field layout with translated labels.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, class: string}> The width options.
     */
    public static function widths(): array
    {
        return [
            'full' => [
                'label' => __( 'Full Width' ),
                'class' => self::WIDTH_CLASSES['full'],
            ],
            'half' => [
                'label' => __( 'Half Width' ),
                'class' => self::WIDTH_CLASSES['half'],
            ],
            'third' => [
                'label' => __( 'One Third' ),
                'class' => self::WIDTH_CLASSES['third'],
            ],
            'two-thirds' => [
                'label' => __( 'Two Thirds' ),
                'class' => self::WIDTH_CLASSES['two-thirds'],
            ],
        ];
    }

    /**
     * Gets all field categories with their field types.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, fields: array<string>}> The field categories.
     */
    public static function getCategories(): array
    {
        return self::categories();
    }

    /**
     * Gets all field type definitions.
     *
     * Applies the 'forms.field_types' filter hook to allow
     * third-party packages to register custom field types.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The field type definitions.
     */
    public static function getTypes(): array
    {
        $types = self::types();

        // Apply filter hook for extensibility
        if ( function_exists( 'applyFilters' ) ) {
            $types = applyFilters( 'forms.field_types', $types );
        }

        return $types;
    }

    /**
     * Gets all field categories with their field types (filtered).
     *
     * Applies the 'forms.field_categories' filter hook to allow
     * third-party packages to register custom categories.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, fields: array<string>}> The filtered field categories.
     */
    public static function getCategoriesFiltered(): array
    {
        $categories = self::categories();

        // Apply filter hook for extensibility
        if ( function_exists( 'applyFilters' ) ) {
            $categories = applyFilters( 'forms.field_categories', $categories );
        }

        return $categories;
    }

    /**
     * Gets configuration for a specific field type.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return array<string, mixed>|null The field type config or null if not found.
     */
    public static function getTypeConfig( string $type ): ?array
    {
        $types = self::getTypes();

        return $types[ $type ] ?? null;
    }

    /**
     * Gets default values for a specific field type.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return array<string, mixed> The default values for the field type.
     */
    public static function getDefaults( string $type ): array
    {
        $types = self::getTypes();

        return $types[ $type ]['defaults'] ?? [];
    }

    /**
     * Checks if a field type has options (for select, radio, checkbox_group).
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return bool True if the field type has configurable options.
     */
    public static function hasOptions( string $type ): bool
    {
        $types = self::getTypes();

        return $types[ $type ]['has_options'] ?? false;
    }

    /**
     * Gets available validation options for a field type.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return array<string> Array of available validation option names.
     */
    public static function getValidationOptions( string $type ): array
    {
        $types = self::getTypes();

        return $types[ $type ]['validation_options'] ?? [];
    }

    /**
     * Gets all width options.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, class: string}> The width options.
     */
    public static function getWidths(): array
    {
        return self::widths();
    }

    /**
     * Checks if a field type exists.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return bool True if the field type exists.
     */
    public static function typeExists( string $type ): bool
    {
        $types = self::getTypes();

        return isset( $types[ $type ] );
    }

    /**
     * Checks if a field type supports placeholders.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return bool True if the field type supports placeholders.
     */
    public static function supportsPlaceholder( string $type ): bool
    {
        $types = self::getTypes();

        return $types[ $type ]['supports_placeholder'] ?? false;
    }

    /**
     * Checks if a field type supports default values.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return bool True if the field type supports default values.
     */
    public static function supportsDefaultValue( string $type ): bool
    {
        $types = self::getTypes();

        return $types[ $type ]['supports_default_value'] ?? true;
    }

    /**
     * Checks if a field type is a layout element (not a data input).
     *
     * Layout elements (heading, paragraph, divider, html) are display-only
     * and do not collect or store user input.
     *
     * @since 1.0.0
     *
     * @param string $type The field type name.
     *
     * @return bool True if the field type is a layout element.
     */
    public static function isLayoutField( string $type ): bool
    {
        $types = self::getTypes();

        return $types[ $type ]['is_layout'] ?? false;
    }

    /**
     * Gets all field type definitions.
     *
     * Alias for getTypes() for convenience.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The field type definitions.
     */
    public static function all(): array
    {
        return self::getTypes();
    }
}
