<?php

/**
 * Field types configuration.
 *
 * Defines all available field types with their metadata, categories,
 * icons, validation options, and default values for the form builder.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Config;

use ArtisanPackUI\Forms\Contracts\FieldType;
use ArtisanPackUI\Forms\FieldTypes\AbstractFieldType;
use ArtisanPackUI\Forms\FieldTypes\AddressFieldType;
use ArtisanPackUI\Forms\FieldTypes\ArrayFieldType;
use ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType;
use ArtisanPackUI\Forms\FieldTypes\CheckboxFieldType;
use ArtisanPackUI\Forms\FieldTypes\CheckboxGroupFieldType;
use ArtisanPackUI\Forms\FieldTypes\ConsentFieldType;
use ArtisanPackUI\Forms\FieldTypes\DateFieldType;
use ArtisanPackUI\Forms\FieldTypes\DateRangeFieldType;
use ArtisanPackUI\Forms\FieldTypes\DateTimeFieldType;
use ArtisanPackUI\Forms\FieldTypes\DividerFieldType;
use ArtisanPackUI\Forms\FieldTypes\EmailFieldType;
use ArtisanPackUI\Forms\FieldTypes\FileFieldType;
use ArtisanPackUI\Forms\FieldTypes\HeadingFieldType;
use ArtisanPackUI\Forms\FieldTypes\HiddenFieldType;
use ArtisanPackUI\Forms\FieldTypes\HtmlFieldType;
use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\FieldTypes\MatrixFieldType;
use ArtisanPackUI\Forms\FieldTypes\NameFieldType;
use ArtisanPackUI\Forms\FieldTypes\NpsFieldType;
use ArtisanPackUI\Forms\FieldTypes\NumberFieldType;
use ArtisanPackUI\Forms\FieldTypes\ParagraphFieldType;
use ArtisanPackUI\Forms\FieldTypes\PhoneFieldType;
use ArtisanPackUI\Forms\FieldTypes\RadioFieldType;
use ArtisanPackUI\Forms\FieldTypes\RankingFieldType;
use ArtisanPackUI\Forms\FieldTypes\RatingFieldType;
use ArtisanPackUI\Forms\FieldTypes\SectionFieldType;
use ArtisanPackUI\Forms\FieldTypes\SelectFieldType;
use ArtisanPackUI\Forms\FieldTypes\SelectMultipleFieldType;
use ArtisanPackUI\Forms\FieldTypes\SignatureFieldType;
use ArtisanPackUI\Forms\FieldTypes\SliderFieldType;
use ArtisanPackUI\Forms\FieldTypes\TextareaFieldType;
use ArtisanPackUI\Forms\FieldTypes\TextFieldType;
use ArtisanPackUI\Forms\FieldTypes\TimeFieldType;
use ArtisanPackUI\Forms\FieldTypes\ToggleFieldType;
use ArtisanPackUI\Forms\FieldTypes\UrlFieldType;
use Illuminate\Support\Facades\Log;
use WeakMap;

/**
 * Field types configuration class.
 *
 * Provides static methods and constants for retrieving field type
 * definitions, categories, and metadata used throughout the form builder.
 *
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
        'choice'   => ['select', 'radio', 'checkbox', 'toggle', 'checkbox_group', 'select_multiple'],
        'advanced' => ['file', 'date', 'time', 'datetime', 'date_range', 'rating', 'slider', 'nps', 'matrix', 'ranking', 'name', 'address', 'list', 'signature', 'consent', 'captcha'],
        'layout'   => ['section', 'heading', 'paragraph', 'divider', 'html'],
    ];

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
     *     supports_autocomplete?: bool,
     *     autocomplete?: string,
     *     supports_dynamic_population?: bool,
     *     supports_label_placement?: bool,
     *     supports_visibility?: bool,
     *     supports_privacy?: bool,
     *     supports_conditional_logic?: bool,
     *     stores_value?: bool,
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
            'supports_autocomplete'  => true,
            'validation_options'     => ['min', 'max', 'pattern'],
        ],
        'email' => [
            'icon'                   => 'o-envelope',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
            'autocomplete'           => 'email',
            'validation_options'     => ['min', 'max'],
        ],
        'number' => [
            'icon'                   => 'o-hashtag',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
            'validation_options'     => ['min', 'max', 'step'],
        ],
        'url' => [
            'icon'                   => 'o-link',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
            'autocomplete'           => 'url',
            'validation_options'     => ['min', 'max'],
        ],
        'textarea' => [
            'icon'                   => 'o-bars-3-bottom-left',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
            'validation_options'     => ['min', 'max'],
        ],
        'hidden' => [
            'icon'                     => 'o-eye-slash',
            'category'                 => 'basic',
            'has_options'              => false,
            'supports_placeholder'     => false,
            'supports_default_value'   => true,
            'supports_label_placement' => false,
            'validation_options'       => [],
        ],
        'select' => [
            'icon'                   => 'o-chevron-down',
            'category'               => 'choice',
            'has_options'            => true,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
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
        'toggle' => [
            'icon'                   => 'o-power',
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
            'icon'                        => 'o-paper-clip',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'supports_visibility'         => false,
            'supports_privacy'            => false,
            'validation_options'          => ['max_size', 'allowed_types'],
        ],
        'date' => [
            'icon'                   => 'o-calendar',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
            'validation_options'     => ['min_date', 'max_date'],
        ],
        'phone' => [
            'icon'                   => 'o-phone',
            'category'               => 'basic',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => true,
            'supports_autocomplete'  => true,
            'autocomplete'           => 'tel',
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
        'datetime' => [
            'icon'                   => 'o-calendar-days',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'date_range' => [
            'icon'                        => 'o-calendar-date-range',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_autocomplete'       => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ],
        'rating' => [
            'icon'                   => 'o-star',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'slider' => [
            'icon'                   => 'o-adjustments-horizontal',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'nps' => [
            'icon'                   => 'o-hand-thumb-up',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => false,
            'supports_default_value' => true,
            'validation_options'     => [],
        ],
        'matrix' => [
            'icon'                        => 'o-squares-2x2',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ],
        'ranking' => [
            'icon'                        => 'o-bars-arrow-down',
            'category'                    => 'advanced',
            'has_options'                 => true,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ],
        'name' => [
            'icon'                        => 'o-user',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_autocomplete'       => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ],
        'address' => [
            'icon'                        => 'o-map-pin',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_autocomplete'       => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ],
        'list' => [
            'icon'                        => 'o-table-cells',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ],
        'signature' => [
            'icon'                        => 'o-pencil-square',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'supports_visibility'         => false,
            'supports_privacy'            => false,
            'validation_options'          => [],
        ],
        'consent' => [
            'icon'                        => 'o-clipboard-document-check',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'supports_visibility'         => false,
            'validation_options'          => [],
        ],
        'captcha' => [
            'icon'                        => 'o-shield-check',
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_dynamic_population' => false,
            'supports_visibility'         => false,
            'supports_privacy'            => false,
            'stores_value'                => false,
            'validation_options'          => [],
        ],
        'section' => [
            'icon'                       => 'o-rectangle-group',
            'category'                   => 'layout',
            'has_options'                => false,
            'supports_placeholder'       => false,
            'supports_default_value'     => false,
            'is_layout'                  => true,
            'supports_conditional_logic' => true,
            'validation_options'         => [],
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
     * The handler class for each built-in field type.
     *
     * @since 1.6.0
     *
     * @var array<string, class-string<AbstractFieldType>>
     */
    public const HANDLERS = [
        'text'            => TextFieldType::class,
        'email'           => EmailFieldType::class,
        'number'          => NumberFieldType::class,
        'url'             => UrlFieldType::class,
        'textarea'        => TextareaFieldType::class,
        'hidden'          => HiddenFieldType::class,
        'select'          => SelectFieldType::class,
        'radio'           => RadioFieldType::class,
        'checkbox'        => CheckboxFieldType::class,
        'toggle'          => ToggleFieldType::class,
        'checkbox_group'  => CheckboxGroupFieldType::class,
        'select_multiple' => SelectMultipleFieldType::class,
        'file'            => FileFieldType::class,
        'date'            => DateFieldType::class,
        'phone'           => PhoneFieldType::class,
        'time'            => TimeFieldType::class,
        'datetime'        => DateTimeFieldType::class,
        'date_range'      => DateRangeFieldType::class,
        'rating'          => RatingFieldType::class,
        'slider'          => SliderFieldType::class,
        'nps'             => NpsFieldType::class,
        'matrix'          => MatrixFieldType::class,
        'ranking'         => RankingFieldType::class,
        'name'            => NameFieldType::class,
        'address'         => AddressFieldType::class,
        'list'            => ListFieldType::class,
        'signature'       => SignatureFieldType::class,
        'consent'         => ConsentFieldType::class,
        'captcha'         => CaptchaFieldType::class,
        'section'         => SectionFieldType::class,
        'heading'         => HeadingFieldType::class,
        'paragraph'       => ParagraphFieldType::class,
        'divider'         => DividerFieldType::class,
        'html'            => HtmlFieldType::class,
    ];

    /**
     * Handlers that give a type built-in behaviour without registering it.
     *
     * Empty since `toggle`, the only entry, became a registered type.
     *
     * @since 1.6.0
     *
     * @var array<string, class-string<AbstractFieldType>>
     */
    public const BEHAVIOUR_HANDLERS = [];

    /**
     * Width option CSS classes.
     *
     * The single source for field widths: FormField::$width_class and the React
     * and Vue renderers (`resources/js/shared/fieldSettings.ts`) use these.
     * Partial widths stack full-width below the `md` breakpoint.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const WIDTH_CLASSES = [
        'full'       => 'w-full',
        'half'       => 'w-full md:w-1/2',
        'third'      => 'w-full md:w-1/3',
        'two-thirds' => 'w-full md:w-2/3',
    ];

    /**
     * Built-in handlers, with their definitions built, by translator instance, then locale.
     *
     * @since 1.6.0
     *
     * @var WeakMap<object, array<string, array<string, FieldType>>>|null
     */
    protected static ?WeakMap $builtInHandlers = null;

    /**
     * Registry entries already warned about, so a bad entry is logged once.
     *
     * @since 1.6.0
     *
     * @var array<string, true>
     */
    protected static array $warnedEntries = [];

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
     * Gets the built-in field type definitions with translated labels.
     *
     * Each definition comes from the type's handler (see HANDLERS). They only
     * depend on the locale, so they are built once per translator and locale
     * (see builtInHandlers()); the `ap.forms.fieldTypes` filter still runs on
     * every getTypes() call.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The field type definitions.
     */
    public static function types(): array
    {
        return array_map(
            fn ( FieldType $handler ): array => $handler->definition(),
            self::builtInHandlers(),
        );
    }

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
     * Applies the 'ap.forms.fieldTypes' filter hook to allow
     * third-party packages to register custom field types. A filter may add an
     * array definition, a FieldType handler class name or a handler instance;
     * handlers are returned here as their definition.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>> The field type definitions.
     */
    public static function getTypes(): array
    {
        $types = [];

        foreach ( self::getRegisteredTypes() as $type => $entry ) {
            if ( is_array( $entry ) ) {
                $types[ $type ] = $entry;

                continue;
            }

            $handler = self::resolveHandler( (string) $type, $entry );

            if ( null !== $handler ) {
                $types[ $type ] = $handler->definition();
            }
        }

        return $types;
    }

    /**
     * Gets the handler for a field type.
     *
     * Built-in types get their own handler, with any edits a filter made to
     * their array definition applied. Types registered as an array get an
     * adapter. An unknown type gets an adapter with an empty definition, so
     * callers can always ask a handler how to validate, store and show a value.
     *
     * @since 1.6.0
     *
     * @param  string  $type  The field type name.
     *
     * @return FieldType The handler.
     */
    public static function handler( string $type ): FieldType
    {
        $registered = self::getRegisteredTypes();

        return self::resolveHandler( $type, $registered[ $type ] ?? null )
            ?? self::resolveHandler( $type, null );
    }

    /**
     * Gets all field categories with their field types (filtered).
     *
     * Applies the 'ap.forms.fieldCategories' filter hook to allow
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
        $categories = applyFilters( 'ap.forms.fieldCategories', $categories );

        return $categories;
    }

    /**
     * Gets configuration for a specific field type.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The field type name.
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
     * @param  string  $type  The field type name.
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
     * @param  string  $type  The field type name.
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
     * Kept for compatibility: since 1.6.0 the builders render a type's
     * controls from the `settings` schema (see FieldSettings) instead.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The field type name.
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
     * @param  string  $type  The field type name.
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
     * @param  string  $type  The field type name.
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
     * @param  string  $type  The field type name.
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
     * Layout elements (section, heading, paragraph, divider, html) are
     * display-only and do not collect or store user input.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The field type name.
     *
     * @return bool True if the field type is a layout element.
     */
    public static function isLayoutField( string $type ): bool
    {
        $types = self::getTypes();

        return $types[ $type ]['is_layout'] ?? false;
    }

    /**
     * Checks if a field type can be shown and hidden by conditional logic.
     *
     * Input types can; layout elements cannot, unless their definition sets
     * `supports_conditional_logic` (such as a section, which hides the fields
     * it groups).
     *
     * @since 1.6.0
     *
     * @param  string  $type  The field type name.
     *
     * @return bool True if the type supports conditional logic.
     */
    public static function supportsConditionalLogic( string $type ): bool
    {
        $definition = self::getTypes()[ $type ] ?? [];

        return ! ( $definition['is_layout'] ?? false ) || true === ( $definition['supports_conditional_logic'] ?? false );
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

    /**
     * Gets the registered field types as the `ap.forms.fieldTypes` filter returns them.
     *
     * Built-ins are passed to the filter as array definitions, so filters
     * written before 1.6.0 that edit or add arrays keep working.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> Array definitions, handler class names or handler instances.
     */
    protected static function getRegisteredTypes(): array
    {
        $types = applyFilters( 'ap.forms.fieldTypes', self::types() );

        return is_array( $types ) ? $types : self::types();
    }

    /**
     * Resolves a registry entry to a handler.
     *
     * @since 1.6.0
     *
     * @param  string  $type  The field type name.
     * @param  mixed  $entry  An array definition, handler class name, handler instance or null.
     *
     * @return FieldType|null The handler, or null when the entry is not usable.
     */
    protected static function resolveHandler( string $type, mixed $entry ): ?FieldType
    {
        if ( $entry instanceof FieldType ) {
            return $entry instanceof AbstractFieldType && '' === $entry->key() ? $entry->withKey( $type ) : $entry;
        }

        if ( is_string( $entry ) ) {
            if ( is_subclass_of( $entry, FieldType::class ) ) {
                return app()->make( $entry, ['key' => $type] );
            }

            if ( ! isset( self::$warnedEntries[ "{$type}:{$entry}" ] ) ) {
                self::$warnedEntries[ "{$type}:{$entry}" ] = true;

                Log::warning( 'ArtisanPackUI Forms: field type handler does not implement FieldType', [
                    'type'    => $type,
                    'handler' => $entry,
                ] );
            }

            return null;
        }

        if ( null !== $entry && ! is_array( $entry ) ) {
            return null;
        }

        $cached = self::builtInHandlers()[ $type ] ?? null;

        if ( null !== $cached && ( null === $entry || $entry === $cached->definition() ) ) {
            return $cached;
        }

        $builtIn = self::HANDLERS[ $type ] ?? self::BEHAVIOUR_HANDLERS[ $type ] ?? null;

        if ( null !== $builtIn ) {
            $handler = new $builtIn( $type );

            return null === $entry ? $handler : $handler->withDefinition( $entry );
        }

        return new ArrayFieldType( $type, $entry ?? [] );
    }

    /**
     * Gets the built-in handlers, with their definitions already built.
     *
     * Definitions only depend on the locale, so the handlers are built once per
     * translator and locale and shared. A filter that leaves a built-in's
     * definition untouched gets the shared handler back from resolveHandler().
     *
     * @since 1.6.0
     *
     * @return array<string, FieldType> The handlers keyed by type.
     */
    protected static function builtInHandlers(): array
    {
        $translator              = app( 'translator' );
        $locale                  = $translator->getLocale();
        self::$builtInHandlers ??= new WeakMap();
        $cached                  = self::$builtInHandlers[ $translator ] ?? [];

        if ( isset( $cached[ $locale ] ) ) {
            return $cached[ $locale ];
        }

        $handlers = [];

        foreach ( self::HANDLERS as $type => $class ) {
            $handlers[ $type ] = new $class( $type );
            $handlers[ $type ]->definition();
        }

        $cached[ $locale ]                     = $handlers;
        self::$builtInHandlers[ $translator ]  = $cached;

        return $handlers;
    }
}
