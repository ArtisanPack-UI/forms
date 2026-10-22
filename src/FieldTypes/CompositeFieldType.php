<?php

/**
 * Base handler for composite (multi-input) field types.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

/**
 * A field made of several inputs that are stored together, such as a name or
 * an address.
 *
 * A subclass lists its inputs in the `sub_inputs` entry of defineType(), each
 * an array with:
 *
 * - `key`: the storage key (letters, digits and underscores).
 * - `label`: the default sub-label (translated).
 * - `placeholder`: the default placeholder.
 * - `enabled`: whether the input is shown by default (default true).
 * - `required`: whether a required field requires the input (default false;
 *   when no enabled input is marked, a required field requires them all).
 * - `autocomplete`: the input's `autocomplete` token.
 * - `type`: `text` (default), `email`, `tel`, `url` or `number`.
 * - `rules`: extra validation rules for the input.
 *
 * Admins enable, relabel, and mark inputs required per field in
 * `field_config.sub_inputs` (see FormField::getSubInputs()). The value is
 * stored as a map of input keys in `value_array`; it is shown as the filled
 * inputs joined by the definition's `format_separator` (default `, `),
 * exported as one column per enabled input or one formatted column, and
 * offers a `{field:key}` merge tag per input. Conditional logic can test one
 * input as `{field}.{key}`. Renderers bind each input to `formData.{field}.{key}`.
 *
 * @since 1.6.0
 */
abstract class CompositeFieldType extends AbstractFieldType
{
    /**
     * The longest value one sub-input accepts, unless its rules say otherwise.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const SUB_INPUT_MAX_LENGTH = 255;

    /**
     * The sub-input settings a field may change in `field_config.sub_inputs`.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const SUB_INPUT_SETTINGS = ['enabled', 'required', 'label', 'placeholder'];

    /**
     * Builds the field config that changes one of a field's sub-input settings.
     *
     * `enabled` and `required` are stored as booleans; `label` and
     * `placeholder` are trimmed and capped at 255 characters, and a blank one
     * falls back to the definition. The last enabled sub-input cannot be
     * turned off. `resources/js/shared/composite.ts` mirrors this.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The composite field.
     * @param  string  $key  The sub-input key.
     * @param  string  $setting  `enabled`, `required`, `label` or `placeholder`.
     * @param  mixed  $value  The raw input value.
     *
     * @return array<string, mixed>|null The new field config, or null when the change is not allowed.
     */
    public static function updateSubInputConfig( FormField $field, string $key, string $setting, mixed $value ): ?array
    {
        $subInputs = $field->getSubInputs();

        if ( ! isset( $subInputs[ $key ] ) || ! in_array( $setting, self::SUB_INPUT_SETTINGS, true ) ) {
            return null;
        }

        $config     = $field->field_config ?? [];
        $configured = is_array( $config['sub_inputs'] ?? null ) ? $config['sub_inputs'] : [];
        $entry      = is_array( $configured[ $key ] ?? null ) ? $configured[ $key ] : [];

        if ( in_array( $setting, ['enabled', 'required'], true ) ) {
            $flag = true === filter_var( $value, FILTER_VALIDATE_BOOLEAN );

            if ( 'enabled' === $setting && ! $flag && [$key] === array_keys( $field->getEnabledSubInputs() ) ) {
                return null;
            }

            $entry[ $setting ] = $flag;
        } else {
            $text = is_scalar( $value ) && ! is_bool( $value ) ? mb_substr( trim( (string) $value ), 0, 255 ) : '';

            if ( '' === $text ) {
                unset( $entry[ $setting ] );
            } else {
                $entry[ $setting ] = $text;
            }
        }

        $configured[ $key ]   = $entry;
        $config['sub_inputs'] = $configured;

        return $config;
    }

    /**
     * {@inheritDoc}
     *
     * The field's own value must be a map; each enabled sub-input gets its
     * presence rule, its type's rules (with a length limit for text) and its
     * definition's rules.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        $required = $field->getRequiredSubInputKeys();
        $rules    = ['' => ['nullable', 'array']];

        foreach ( $field->getEnabledSubInputs() as $key => $subInput ) {
            $rules[ $key ] = array_merge(
                [in_array( $key, $required, true ) ? 'required' : 'nullable'],
                $this->subInputTypeRules( $subInput['type'] ),
                $subInput['rules'],
            );
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        $messages = ['array' => __( 'The :field field is invalid.', ['field' => $field->label] )];

        foreach ( $field->getEnabledSubInputs() as $key => $subInput ) {
            $replace = ['field' => $field->label, 'part' => $subInput['label']];

            $messages[ "{$key}.required" ] = __( ':field: :part is required.', $replace );
            $messages[ "{$key}.string" ]   = __( ':field: :part must be text.', $replace );
            $messages[ "{$key}.max" ]      = __( ':field: :part may not be longer than :max characters.', $replace );
            $messages[ "{$key}.email" ]    = __( ':field: :part must be a valid email address.', $replace );
            $messages[ "{$key}.url" ]      = __( ':field: :part must be a valid URL.', $replace );
            $messages[ "{$key}.numeric" ]  = __( ':field: :part must be a number.', $replace );
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     *
     * Keeps the enabled sub-inputs only, as trimmed strings.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        $value      = is_array( $value ) ? $value : [];
        $normalized = [];

        foreach ( array_keys( $field->getEnabledSubInputs() ) as $key ) {
            $part = $value[ $key ] ?? '';

            $normalized[ $key ] = is_scalar( $part ) && ! is_bool( $part ) ? trim( (string) $part ) : '';
        }

        return $normalized;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return array_fill_keys( array_keys( $field->getEnabledSubInputs() ), '' );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        return $this->format( $this->sourceField( $value ), is_array( $value->value_array ) ? $value->value_array : [] );
    }

    /**
     * {@inheritDoc}
     *
     * One column per enabled sub-input, or one formatted column when the
     * field's export format is `single`.
     *
     * @since 1.6.0
     */
    public function exportColumns( FormField $field ): array
    {
        if ( 'single' === $field->getCompositeExportFormat() ) {
            return [$field->getAdminLabel()];
        }

        $columns = [];

        foreach ( $field->getEnabledSubInputs() as $subInput ) {
            $columns[] = __( ':field (:part)', ['field' => $field->getAdminLabel(), 'part' => $subInput['label']] );
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        if ( 'single' === $field->getCompositeExportFormat() ) {
            return [$value?->display_value ?? ''];
        }

        $keys = array_keys( $field->getEnabledSubInputs() );

        if ( true === $value?->isRedacted() ) {
            return array_fill( 0, count( $keys ), __( '[Redacted]' ) );
        }

        $parts = is_array( $value?->value_array ) ? $value->value_array : [];

        return array_map(
            fn ( string $key ): string => is_scalar( $parts[ $key ] ?? null ) ? (string) $parts[ $key ] : '',
            $keys,
        );
    }

    /**
     * {@inheritDoc}
     *
     * `{field}` for the formatted value and `{field:key}` for each enabled sub-input.
     *
     * @since 1.6.0
     */
    public function mergeTags( FormField $field ): array
    {
        $tags = parent::mergeTags( $field );

        foreach ( $field->getEnabledSubInputs() as $key => $subInput ) {
            $tags[ '{' . $field->name . ':' . $key . '}' ] = __( ':field: :part', ['field' => $field->label, 'part' => $subInput['label']] );
        }

        return $tags;
    }

    /**
     * {@inheritDoc}
     *
     * The generic composite partial, unless a subclass ships its own.
     *
     * @since 1.6.0
     */
    public function view(): string
    {
        return 'forms::components.fields.composite';
    }

    /**
     * Formats a composite value for display.
     *
     * Joins the filled sub-inputs, in definition order, with the type's
     * `format_separator`. Without the field, the stored order is kept.
     *
     * @since 1.6.0
     *
     * @param  FormField|null  $field  The field, or null when it was deleted.
     * @param  array<int|string, mixed>  $parts  The stored value.
     *
     * @return string The formatted value.
     */
    public function format( ?FormField $field, array $parts ): string
    {
        $keys   = null === $field ? array_keys( $parts ) : array_keys( $field->getSubInputs() );
        $filled = [];

        foreach ( $keys as $key ) {
            $part = $parts[ $key ] ?? null;

            if ( is_scalar( $part ) && '' !== trim( (string) $part ) ) {
                $filled[] = trim( (string) $part );
            }
        }

        return implode( (string) ( $this->definition()['format_separator'] ?? ', ' ), $filled );
    }

    /**
     * {@inheritDoc}
     *
     * Composite types take no placeholder, default value, field-level
     * autocomplete token or dynamic population; each sub-input has its own
     * label, placeholder and token instead.
     *
     * @since 1.6.0
     */
    protected function baseDefinition(): array
    {
        return array_merge( parent::baseDefinition(), [
            'category'                    => 'advanced',
            'has_options'                 => false,
            'supports_placeholder'        => false,
            'supports_default_value'      => false,
            'supports_autocomplete'       => false,
            'supports_dynamic_population' => false,
            'validation_options'          => [],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * Adds the sub-label placement and export format settings.
     *
     * @since 1.6.0
     */
    protected function commonSettings( array $definition, array $settings ): array
    {
        $declared = [];

        foreach ( $settings as $setting ) {
            if ( is_array( $setting ) && isset( $setting['key'] ) ) {
                $declared[] = $setting['key'];
            }
        }

        $composite = array_filter(
            [$this->subLabelPlacementSetting(), $this->exportFormatSetting()],
            fn ( array $setting ): bool => ! in_array( $setting['key'], $declared, true ),
        );

        return array_merge( array_values( $composite ), parent::commonSettings( $definition, $settings ) );
    }

    /**
     * Gets the setting that places the sub-input labels.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The setting.
     */
    protected function subLabelPlacementSetting(): array
    {
        return [
            'key'     => 'sub_label_placement',
            'input'   => 'select',
            'default' => 'below',
            'label'   => __( 'Sub-Label Placement' ),
            'hint'    => __( 'Hidden sub-labels are still read by screen readers.' ),
            'options' => [
                ['value' => 'below', 'label' => __( 'Below the inputs' )],
                ['value' => 'above', 'label' => __( 'Above the inputs' )],
                ['value' => 'hidden', 'label' => __( 'Hidden' )],
            ],
        ];
    }

    /**
     * Gets the setting that picks how the field is exported to CSV.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The setting.
     */
    protected function exportFormatSetting(): array
    {
        return [
            'key'     => 'export_format',
            'input'   => 'select',
            'tab'     => 'advanced',
            'default' => 'columns',
            'label'   => __( 'Export Format' ),
            'options' => [
                ['value' => 'columns', 'label' => __( 'One column per input' )],
                ['value' => 'single', 'label' => __( 'One formatted column' )],
            ],
        ];
    }

    /**
     * Gets the rules for a sub-input's input type.
     *
     * @since 1.6.0
     *
     * @param  string  $type  The input type (see FormField::SUB_INPUT_TYPES).
     *
     * @return list<string> The rules; text-like types are limited to SUB_INPUT_MAX_LENGTH characters.
     */
    protected function subInputTypeRules( string $type ): array
    {
        $maxLength = 'max:' . self::SUB_INPUT_MAX_LENGTH;

        return match ( $type ) {
            'email'  => ['string', $maxLength, 'email'],
            'url'    => ['string', $maxLength, 'url'],
            'number' => ['numeric'],
            default  => ['string', $maxLength],
        };
    }
}
