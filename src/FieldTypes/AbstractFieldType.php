<?php

/**
 * Base field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Contracts\FieldType;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Support\Autocomplete;
use ArtisanPackUI\Forms\Support\FieldSettings;

/**
 * Default behaviour shared by every field type.
 *
 * A subclass describes its type in defineType() and overrides only the hooks
 * that differ: typeRules() and typeMessages() for validation, presenceRule()
 * for what "required" means, displayScalar() for how a stored value reads.
 *
 * @since 1.6.0
 */
abstract class AbstractFieldType implements FieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = '';

    /**
     * Definition entries that replace the handler's own, from the `ap.forms.fieldTypes` filter.
     *
     * @since 1.6.0
     *
     * @var array<string, mixed>
     */
    protected array $definitionOverrides = [];

    /**
     * The built definition, kept for the life of the handler instance.
     *
     * @since 1.6.0
     *
     * @var array<string, mixed>|null
     */
    protected ?array $resolvedDefinition = null;

    /**
     * Creates a handler.
     *
     * @since 1.6.0
     *
     * @param  string|null  $key  The type name, when registered under a name other than the handler's own.
     */
    public function __construct( ?string $key = null )
    {
        if ( null !== $key && '' !== $key ) {
            $this->key = $key;
        }
    }

    /**
     * Gets the type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @return string The type name.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Gets a copy of the handler with definition entries replaced.
     *
     * Used when an `ap.forms.fieldTypes` filter edits a built-in type's array
     * definition, so the edit is kept while the behaviour stays the handler's.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $overrides  The definition entries to replace.
     *
     * @return static The handler copy.
     */
    public function withDefinition( array $overrides ): static
    {
        $handler                      = clone $this;
        $handler->definitionOverrides = $overrides;
        $handler->resolvedDefinition  = null;

        return $handler;
    }

    /**
     * Gets a copy of the handler registered under another type key.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The type key.
     *
     * @return static The handler.
     */
    public function withKey( string $key ): static
    {
        $handler      = clone $this;
        $handler->key = $key;

        return $handler;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function definition(): array
    {
        if ( null !== $this->resolvedDefinition ) {
            return $this->resolvedDefinition;
        }

        $definition = array_merge(
            ['sub_inputs' => [], 'settings' => []],
            $this->defineType(),
            $this->definitionOverrides,
        );

        $settings = array_merge(
            is_array( $definition['settings'] ) ? $definition['settings'] : [],
            $this->validationOptionSettings( $definition ),
        );

        $definition['settings'] = FieldSettings::normalizeSchema( array_merge(
            $settings,
            $this->commonSettings( $definition, $settings ),
        ) );

        return $this->resolvedDefinition = $definition;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        if ( $this->isLayout() ) {
            return [];
        }

        return [
            '' => array_merge(
                [$this->presenceRule( $field )],
                $this->typeRules( $field ),
                $this->limitRules( $field ),
            ),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        if ( $this->isLayout() ) {
            return [];
        }

        $messages = [];

        if ( $field->is_required ) {
            $messages[ $this->presenceRule( $field ) ] = __( 'The :field field is required.', ['field' => $field->label] );
        }

        return array_merge( $messages, $this->typeMessages( $field ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return $field->getResolvedDefaultValue() ?? '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        if ( ! empty( $value->value_array ) ) {
            return implode( ', ', $value->display_values );
        }

        if ( $value->upload_id && $value->upload ) {
            return $value->upload->original_name;
        }

        return $this->displayScalar( $value );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function exportColumns( FormField $field ): array
    {
        return $this->isLayout() ? [] : [$field->getAdminLabel()];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        return $this->isLayout() ? [] : [$value?->display_value ?? ''];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function mergeTags( FormField $field ): array
    {
        return ['{' . $field->name . '}' => (string) $field->label];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function view(): string
    {
        return 'forms::components.fields.' . $this->key;
    }

    /**
     * Describes the type: its metadata, defaults and settings schema.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The definition.
     */
    abstract protected function defineType(): array;

    /**
     * Checks whether the type is display-only and collects no input.
     *
     * @since 1.6.0
     *
     * @return bool True for a layout type.
     */
    protected function isLayout(): bool
    {
        return (bool) ( $this->definition()['is_layout'] ?? false );
    }

    /**
     * Gets the rule a required or optional field starts with.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string `required` or `nullable`.
     */
    protected function presenceRule( FormField $field ): string
    {
        return $field->is_required ? 'required' : 'nullable';
    }

    /**
     * Gets the rules that check a value has the type's format.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<mixed> The rules.
     */
    protected function typeRules( FormField $field ): array
    {
        return ['string'];
    }

    /**
     * Gets the validation messages for the type's format rules.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array<string, string> The messages, keyed by rule.
     */
    protected function typeMessages( FormField $field ): array
    {
        return [];
    }

    /**
     * Gets the rules for the limits set in the field's `validation_rules`.
     *
     * Limits are checked against null rather than truthiness so a limit of 0
     * is still applied.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<string> The rules.
     */
    protected function limitRules( FormField $field ): array
    {
        $rules = [];

        if ( null !== ( $min = $this->validationLimit( $field, 'min' ) ) ) {
            $rules[] = "min:{$min}";
        }

        if ( null !== ( $max = $this->validationLimit( $field, 'max' ) ) ) {
            $rules[] = "max:{$max}";
        }

        if ( $pattern = $field->getValidationRule( 'pattern' ) ) {
            $rules[] = "regex:{$pattern}";
        }

        foreach ( ['min_date' => 'after_or_equal', 'max_date' => 'before_or_equal', 'min_time' => 'after_or_equal', 'max_time' => 'before_or_equal'] as $key => $rule ) {
            if ( null !== ( $limit = $this->validationLimit( $field, $key ) ) ) {
                $rules[] = "{$rule}:{$limit}";
            }
        }

        return $rules;
    }

    /**
     * Gets a validation limit as a rule parameter, or null when it is not set.
     *
     * `0` and `'0'` count as set; null, blank strings and non-scalars do not.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  string  $key  The validation rule key.
     *
     * @return string|null The limit, or null when unset.
     */
    protected function validationLimit( FormField $field, string $key ): ?string
    {
        $value = $field->getValidationRule( $key );

        if ( ! is_scalar( $value ) || is_bool( $value ) ) {
            return null;
        }

        $value = trim( (string) $value );

        return '' === $value ? null : $value;
    }

    /**
     * Gets the display form of a stored scalar value.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return string The display value.
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        return $value->value ?? '';
    }

    /**
     * Gets the field a stored value was submitted for, if it still exists.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return FormField|null The field, or null when it was deleted.
     */
    protected function sourceField( FormSubmissionValue $value ): ?FormField
    {
        return null === $value->field_id ? null : $value->field;
    }

    /**
     * Gets settings for `validation_options` the settings schema does not cover.
     *
     * Before 1.6.0 the builder drew its validation controls from a type's
     * `validation_options`, so an array type registered through the
     * `ap.forms.fieldTypes` filter with `['min', 'max', 'pattern']` keeps
     * those controls.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $definition  The definition being built.
     *
     * @return list<array<string, mixed>> The settings to add.
     */
    protected function validationOptionSettings( array $definition ): array
    {
        $options = $definition['validation_options'] ?? [];

        if ( ! is_array( $options ) || [] === $options ) {
            return [];
        }

        $covered = [];

        foreach ( is_array( $definition['settings'] ) ? $definition['settings'] : [] as $setting ) {
            if ( is_array( $setting ) && isset( $setting['key'] ) ) {
                $covered[] = $setting['key'];
            }
        }

        [$min, $max] = $this->lengthSettings();
        $available   = [
            'min'           => $min,
            'max'           => $max,
            'pattern'       => $this->patternSetting(),
            'step'          => ['key' => 'step', 'input' => 'number', 'store' => 'validation_rules', 'tab' => 'validation', 'step' => 'any', 'min' => 0, 'label' => __( 'Step Increment' ), 'placeholder' => '1'],
            'max_size'      => ['key' => 'max_size', 'input' => 'integer', 'legacy_store' => 'validation_rules', 'tab' => 'validation', 'min' => 1, 'label' => __( 'Max File Size (KB)' ), 'placeholder' => '5120'],
            'allowed_types' => ['key' => 'allowed_types', 'input' => 'list', 'legacy_store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Allowed File Types' ), 'placeholder' => 'pdf, doc, docx, jpg, png', 'hint' => __( 'Comma-separated file extensions' ), 'monospace' => true],
            'min_date'      => ['key' => 'min_date', 'input' => 'date', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Date' )],
            'max_date'      => ['key' => 'max_date', 'input' => 'date', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Date' )],
        ];

        $settings = [];

        foreach ( $options as $option ) {
            if ( is_string( $option ) && isset( $available[ $option ] ) && ! in_array( $option, $covered, true ) ) {
                $settings[] = $available[ $option ];
                $covered[]  = $option;
            }
        }

        return $settings;
    }

    /**
     * Gets the settings every input type offers, leaving out any the type already declares.
     *
     * Validation tab: a custom error message and a custom required message.
     * Advanced tab: the `autocomplete` token (for types with
     * `supports_autocomplete`), an admin label and dynamic population (unless
     * `supports_dynamic_population` is false). Layout types collect no input
     * and get none of them.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $definition  The definition being built.
     * @param  list<mixed>  $settings  The type's own settings.
     *
     * @return list<array<string, mixed>> The settings to add.
     */
    protected function commonSettings( array $definition, array $settings ): array
    {
        if ( true === ( $definition['is_layout'] ?? false ) ) {
            return [];
        }

        $common = [
            [
                'key'        => 'error_message',
                'tab'        => 'validation',
                'max_length' => 500,
                'label'      => __( 'Custom Error Message' ),
                'hint'       => __( 'Replaces every validation message except the required one. May be a translation key.' ),
            ],
            [
                'key'        => 'required_message',
                'tab'        => 'validation',
                'max_length' => 500,
                'label'      => __( 'Required Message' ),
                'hint'       => __( 'Shown when a required field is left empty. May be a translation key.' ),
            ],
        ];

        if ( true === ( $definition['supports_autocomplete'] ?? false ) ) {
            $default  = $definition['autocomplete'] ?? null;
            $common[] = Autocomplete::setting( Autocomplete::isToken( $default ) ? $default : null );
        }

        $common[] = [
            'key'        => 'admin_label',
            'tab'        => 'advanced',
            'max_length' => 255,
            'label'      => __( 'Admin Label' ),
            'hint'       => __( 'Shown instead of the label in submissions, exports and admin notifications.' ),
        ];

        if ( false !== ( $definition['supports_dynamic_population'] ?? true ) ) {
            $common[] = [
                'key'   => 'dynamic_population',
                'input' => 'toggle',
                'tab'   => 'advanced',
                'label' => __( 'Allow field to be populated dynamically' ),
                'hint'  => __( 'Fill the value from the renderer prefill, the query string or the ap.forms.fieldValue filter. Read-only fields are not populated.' ),
            ];
            $common[] = [
                'key'        => 'dynamic_parameter',
                'tab'        => 'advanced',
                'max_length' => 100,
                'label'      => __( 'Parameter Name' ),
                'hint'       => __( 'The prefill key and query string parameter. Defaults to the field name.' ),
                'monospace'  => true,
            ];
        }

        $declared = [];

        foreach ( $settings as $setting ) {
            if ( is_array( $setting ) && isset( $setting['key'] ) ) {
                $declared[] = $setting['key'];
            }
        }

        return array_values( array_filter(
            $common,
            fn ( array $setting ): bool => ! in_array( $setting['key'], $declared, true ),
        ) );
    }

    /**
     * Gets the `read_only` setting shared by types whose value can be locked.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The setting.
     */
    protected function readOnlySetting(): array
    {
        return [
            'key'   => 'read_only',
            'input' => 'toggle',
            'tab'   => 'advanced',
            'label' => __( 'Read-only' ),
            'hint'  => __( 'Shown but not editable. Submissions keep the default value.' ),
        ];
    }

    /**
     * Gets the minimum and maximum length settings for text-like types.
     *
     * @since 1.6.0
     *
     * @return list<array<string, mixed>> The settings.
     */
    protected function lengthSettings(): array
    {
        return [
            ['key' => 'min', 'input' => 'integer', 'store' => 'validation_rules', 'tab' => 'validation', 'min' => 0, 'label' => __( 'Min Length' )],
            ['key' => 'max', 'input' => 'integer', 'store' => 'validation_rules', 'tab' => 'validation', 'min' => 0, 'label' => __( 'Max Length' )],
        ];
    }

    /**
     * Gets the regex pattern setting.
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The setting.
     */
    protected function patternSetting(): array
    {
        return [
            'key'         => 'pattern',
            'store'       => 'validation_rules',
            'tab'         => 'validation',
            'label'       => __( 'Pattern (Regex)' ),
            'placeholder' => '/^[a-z]+$/i',
            'hint'        => __( 'Regular expression the value must match.' ),
            'monospace'   => true,
        ];
    }
}
