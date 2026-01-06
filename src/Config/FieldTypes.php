<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Config;

/**
 * FieldTypes Configuration
 *
 * Defines all available field types with their metadata,
 * categories, icons, validation options, and default values.
 *
 * @since 1.0.0
 */
class FieldTypes
{
    /**
     * Field type categories for organizing the palette.
     *
     * @var array<string, array{label: string, fields: array<string>}>
     */
    public const CATEGORIES = [
        'basic' => [
            'label' => 'Basic Fields',
            'fields' => ['text', 'email', 'phone', 'number', 'url', 'textarea', 'hidden'],
        ],
        'choice' => [
            'label' => 'Choice Fields',
            'fields' => ['select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple'],
        ],
        'advanced' => [
            'label' => 'Advanced Fields',
            'fields' => ['file', 'date', 'time'],
        ],
        'layout' => [
            'label' => 'Layout Elements',
            'fields' => ['heading', 'paragraph', 'divider', 'html'],
        ],
    ];

    /**
     * Field type definitions with metadata.
     *
     * @var array<string, array{
     *     label: string,
     *     icon: string,
     *     category: string,
     *     has_options: bool,
     *     supports_placeholder: bool,
     *     supports_default_value: bool,
     *     validation_options: array<string>,
     *     defaults: array<string, mixed>
     * }>
     */
    public const TYPES = [
        'text' => [
            'label' => 'Text Input',
            'icon' => 'o-document-text',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => ['min', 'max', 'pattern'],
            'defaults' => [
                'label' => 'Text Field',
                'placeholder' => '',
            ],
        ],
        'email' => [
            'label' => 'Email',
            'icon' => 'o-envelope',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => ['min', 'max'],
            'defaults' => [
                'label' => 'Email Address',
                'placeholder' => 'you@example.com',
            ],
        ],
        'number' => [
            'label' => 'Number',
            'icon' => 'o-hashtag',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => ['min', 'max', 'step'],
            'defaults' => [
                'label' => 'Number',
                'placeholder' => '',
            ],
        ],
        'url' => [
            'label' => 'URL',
            'icon' => 'o-link',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => ['min', 'max'],
            'defaults' => [
                'label' => 'Website URL',
                'placeholder' => 'https://example.com',
            ],
        ],
        'textarea' => [
            'label' => 'Text Area',
            'icon' => 'o-bars-3-bottom-left',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => ['min', 'max'],
            'defaults' => [
                'label' => 'Message',
                'placeholder' => '',
                'field_config' => ['rows' => 4],
            ],
        ],
        'hidden' => [
            'label' => 'Hidden Field',
            'icon' => 'o-eye-slash',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Hidden Field',
            ],
        ],
        'select' => [
            'label' => 'Dropdown Select',
            'icon' => 'o-chevron-down',
            'category' => 'choice',
            'has_options' => true,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Select an Option',
                'placeholder' => 'Choose...',
                'field_config' => [
                    'options' => [],
                ],
            ],
        ],
        'radio' => [
            'label' => 'Radio Buttons',
            'icon' => 'o-stop-circle',
            'category' => 'choice',
            'has_options' => true,
            'supports_placeholder' => false,
            'supports_default_value' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Choose One',
                'field_config' => [
                    'options' => [],
                ],
            ],
        ],
        'checkbox' => [
            'label' => 'Single Checkbox',
            'icon' => 'o-check-circle',
            'category' => 'choice',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'I agree to the terms',
            ],
        ],
        'checkbox_group' => [
            'label' => 'Checkbox Group',
            'icon' => 'o-list-bullet',
            'category' => 'choice',
            'has_options' => true,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'validation_options' => ['min', 'max'],
            'defaults' => [
                'label' => 'Select Options',
                'field_config' => [
                    'options' => [],
                ],
            ],
        ],
        'select_multiple' => [
            'label' => 'Multi-Select',
            'icon' => 'o-queue-list',
            'category' => 'choice',
            'has_options' => true,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'validation_options' => ['min', 'max'],
            'defaults' => [
                'label' => 'Select Multiple Options',
                'field_config' => [
                    'options' => [],
                ],
            ],
        ],
        'file' => [
            'label' => 'File Upload',
            'icon' => 'o-paper-clip',
            'category' => 'advanced',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'validation_options' => ['max_size', 'allowed_types'],
            'defaults' => [
                'label' => 'Upload File',
                'field_config' => [
                    'max_size' => 5120,
                    'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'png'],
                ],
            ],
        ],
        'date' => [
            'label' => 'Date Picker',
            'icon' => 'o-calendar',
            'category' => 'advanced',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => true,
            'validation_options' => ['min_date', 'max_date'],
            'defaults' => [
                'label' => 'Select Date',
            ],
        ],
        'phone' => [
            'label' => 'Phone Number',
            'icon' => 'o-phone',
            'category' => 'basic',
            'has_options' => false,
            'supports_placeholder' => true,
            'supports_default_value' => true,
            'validation_options' => ['pattern'],
            'defaults' => [
                'label' => 'Phone Number',
                'placeholder' => '(123) 456-7890',
            ],
        ],
        'time' => [
            'label' => 'Time Picker',
            'icon' => 'o-clock',
            'category' => 'advanced',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => true,
            'validation_options' => ['min_time', 'max_time'],
            'defaults' => [
                'label' => 'Select Time',
                'field_config' => [
                    'step' => 60,
                ],
            ],
        ],
        'heading' => [
            'label' => 'Heading',
            'icon' => 'o-h1',
            'category' => 'layout',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'is_layout' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Section Heading',
                'field_config' => [
                    'level' => 'h3',
                ],
            ],
        ],
        'paragraph' => [
            'label' => 'Paragraph Text',
            'icon' => 'o-document-text',
            'category' => 'layout',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'is_layout' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Information',
                'help_text' => 'Add descriptive text here.',
            ],
        ],
        'divider' => [
            'label' => 'Divider',
            'icon' => 'o-minus',
            'category' => 'layout',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'is_layout' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Divider',
                'field_config' => [
                    'style' => 'solid',
                    'spacing' => 'normal',
                ],
            ],
        ],
        'html' => [
            'label' => 'Custom HTML',
            'icon' => 'o-code-bracket',
            'category' => 'layout',
            'has_options' => false,
            'supports_placeholder' => false,
            'supports_default_value' => false,
            'is_layout' => true,
            'validation_options' => [],
            'defaults' => [
                'label' => 'Custom Content',
                'field_config' => [
                    'content' => '',
                ],
            ],
        ],
    ];

    /**
     * Width options for field layout.
     *
     * @var array<string, array{label: string, class: string}>
     */
    public const WIDTHS = [
        'full' => [
            'label' => 'Full Width',
            'class' => 'w-full',
        ],
        'half' => [
            'label' => 'Half Width',
            'class' => 'w-1/2',
        ],
        'third' => [
            'label' => 'One Third',
            'class' => 'w-1/3',
        ],
        'two-thirds' => [
            'label' => 'Two Thirds',
            'class' => 'w-2/3',
        ],
    ];

    /**
     * Get all field categories with their field types.
     *
     * @return array<string, array{label: string, fields: array<string>}>
     */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Get all field type definitions.
     *
     * Applies the 'artisanpack.forms.field_types' filter hook to allow
     * third-party packages to register custom field types.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getTypes(): array
    {
        $types = self::TYPES;

        // Apply filter hook for extensibility
        if (function_exists('applyFilters')) {
            $types = applyFilters('artisanpack.forms.field_types', $types);
        }

        return $types;
    }

    /**
     * Get all field categories with their field types.
     *
     * Applies the 'artisanpack.forms.field_categories' filter hook to allow
     * third-party packages to register custom categories.
     *
     * @return array<string, array{label: string, fields: array<string>}>
     */
    public static function getCategoriesFiltered(): array
    {
        $categories = self::CATEGORIES;

        // Apply filter hook for extensibility
        if (function_exists('applyFilters')) {
            $categories = applyFilters('artisanpack.forms.field_categories', $categories);
        }

        return $categories;
    }

    /**
     * Get configuration for a specific field type.
     *
     * @param  string  $type  The field type name.
     * @return array<string, mixed>|null The field type config or null if not found.
     */
    public static function getTypeConfig(string $type): ?array
    {
        $types = self::getTypes();

        return $types[$type] ?? null;
    }

    /**
     * Get default values for a specific field type.
     *
     * @param  string  $type  The field type name.
     * @return array<string, mixed> The default values for the field type.
     */
    public static function getDefaults(string $type): array
    {
        $types = self::getTypes();

        return $types[$type]['defaults'] ?? [];
    }

    /**
     * Check if a field type has options (for select, radio, checkbox_group).
     *
     * @param  string  $type  The field type name.
     * @return bool True if the field type has configurable options.
     */
    public static function hasOptions(string $type): bool
    {
        $types = self::getTypes();

        return $types[$type]['has_options'] ?? false;
    }

    /**
     * Get available validation options for a field type.
     *
     * @param  string  $type  The field type name.
     * @return array<string> Array of available validation option names.
     */
    public static function getValidationOptions(string $type): array
    {
        $types = self::getTypes();

        return $types[$type]['validation_options'] ?? [];
    }

    /**
     * Get all width options.
     *
     * @return array<string, array{label: string, class: string}>
     */
    public static function getWidths(): array
    {
        return self::WIDTHS;
    }

    /**
     * Check if a field type exists.
     *
     * @param  string  $type  The field type name.
     * @return bool True if the field type exists.
     */
    public static function typeExists(string $type): bool
    {
        $types = self::getTypes();

        return isset($types[$type]);
    }

    /**
     * Check if a field type supports placeholders.
     *
     * @param  string  $type  The field type name.
     * @return bool True if the field type supports placeholders.
     */
    public static function supportsPlaceholder(string $type): bool
    {
        $types = self::getTypes();

        return $types[$type]['supports_placeholder'] ?? false;
    }

    /**
     * Check if a field type supports default values.
     *
     * @param  string  $type  The field type name.
     * @return bool True if the field type supports default values.
     */
    public static function supportsDefaultValue(string $type): bool
    {
        $types = self::getTypes();

        return $types[$type]['supports_default_value'] ?? true;
    }

    /**
     * Check if a field type is a layout element (not a data input).
     *
     * Layout elements (heading, paragraph, divider, html) are display-only
     * and do not collect or store user input.
     *
     * @param  string  $type  The field type name.
     * @return bool True if the field type is a layout element.
     */
    public static function isLayoutField(string $type): bool
    {
        $types = self::getTypes();

        return $types[$type]['is_layout'] ?? false;
    }

    /**
     * Get all field type definitions.
     *
     * Alias for getTypes() for convenience.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::getTypes();
    }
}
