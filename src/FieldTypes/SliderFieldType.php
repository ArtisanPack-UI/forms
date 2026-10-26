<?php

/**
 * Slider field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * A number chosen on a range slider (`<input type="range">`), from `min` to
 * `max` in steps of `step`, with the value shown beside it.
 *
 * A slider always has a value: it starts at its default value or, without
 * one, at its minimum (see initialValue()). The value label adds the
 * `prefix` and `suffix` (such as `$` or `%`), which the renderers also give
 * to screen readers as `aria-valuetext`. `resources/js/shared/slider.ts`
 * mirrors the limits for React and Vue.
 *
 * @since 1.6.0
 */
class SliderFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'slider';

    /**
     * The minimum used when the field sets none.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DEFAULT_MIN = 0;

    /**
     * The maximum used when the field sets none.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DEFAULT_MAX = 100;

    /**
     * The step used when the field sets none.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DEFAULT_STEP = 1;

    /**
     * The longest prefix or suffix of the value label.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const AFFIX_MAX_LENGTH = 20;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'slider';

    /**
     * Gets a slider's limits and step.
     *
     * A maximum at or below the minimum is moved one step above it, and a
     * step that is not positive falls back to DEFAULT_STEP.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array{min: float|int, max: float|int, step: float|int} The limits.
     */
    public static function limits( FormField $field ): array
    {
        $min  = self::number( $field->getConfig( 'min' ) ) ?? self::DEFAULT_MIN;
        $max  = self::number( $field->getConfig( 'max' ) ) ?? self::DEFAULT_MAX;
        $step = self::number( $field->getConfig( 'step' ) );
        $step = null !== $step && $step > 0 ? $step : self::DEFAULT_STEP;

        if ( $max <= $min ) {
            $max = $min + $step;
        }

        return ['min' => $min, 'max' => $max, 'step' => $step];
    }

    /**
     * Gets the text shown before and after a slider's value.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array{prefix: string, suffix: string} The prefix and suffix, empty when unset.
     */
    public static function affixes( FormField $field ): array
    {
        $affixes = [];

        foreach ( ['prefix', 'suffix'] as $key ) {
            $value           = $field->getConfig( $key );
            $affixes[ $key ] = is_string( $value ) ? mb_substr( trim( $value ), 0, self::AFFIX_MAX_LENGTH ) : '';
        }

        return $affixes;
    }

    /**
     * Checks whether a slider shows its value beside the track.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True unless the `hide_value` setting is on.
     */
    public static function showsValue( FormField $field ): bool
    {
        return true !== filter_var( $field->getConfig( 'hide_value', false ), FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Gets the text placed before and after a value in its label.
     *
     * The prefix and suffix, with a prefix ending, or a suffix starting, with
     * a letter or digit spaced from the value; symbols such as `$` and `%`
     * are not. `formatSliderValue()` in `resources/js/shared/slider.ts`
     * matches.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array{before: string, after: string} The text.
     */
    public static function valueAffixes( FormField $field ): array
    {
        $affixes = self::affixes( $field );

        return [
            'before' => 1 === preg_match( '/[\p{L}\p{N}]$/u', $affixes['prefix'] ) ? $affixes['prefix'] . ' ' : $affixes['prefix'],
            'after'  => 1 === preg_match( '/^[\p{L}\p{N}]/u', $affixes['suffix'] ) ? ' ' . $affixes['suffix'] : $affixes['suffix'],
        ];
    }

    /**
     * Formats a value with the slider's prefix and suffix, such as "$40", "75%" or "3 years".
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  string  $value  The value.
     *
     * @return string The value label.
     */
    public static function valueText( FormField $field, string $value ): string
    {
        $affixes = self::valueAffixes( $field );

        return $affixes['before'] . $value . $affixes['after'];
    }

    /**
     * Gets the value a slider starts with: its default value within its limits, or its minimum.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  mixed  $default  The resolved default value.
     *
     * @return string The value.
     */
    public static function initialValue( FormField $field, mixed $default ): string
    {
        $limits = self::limits( $field );
        $value  = self::number( $default );

        if ( null === $value || $value < $limits['min'] || $value > $limits['max'] ) {
            $value = $limits['min'];
        }

        return self::format( $value );
    }

    /**
     * {@inheritDoc}
     *
     * Stores the number without trailing zeros.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        $number = self::number( $value );

        return null === $number ? $value : self::format( $number );
    }

    /**
     * {@inheritDoc}
     *
     * The default value within the limits, or the minimum.
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return self::initialValue( $field, $field->getResolvedDefaultValue() );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        return [$value?->isRedacted() ? $value->display_value : ( $value?->value ?? '' )];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['slider'], [
            'label'    => __( 'Slider' ),
            'defaults' => [
                'label'        => __( 'Slider' ),
                'field_config' => [
                    'min'  => self::DEFAULT_MIN,
                    'max'  => self::DEFAULT_MAX,
                    'step' => self::DEFAULT_STEP,
                ],
            ],
            'settings' => [
                ['key' => 'min', 'input' => 'number', 'step' => 'any', 'default' => self::DEFAULT_MIN, 'placeholder' => (string) self::DEFAULT_MIN, 'label' => __( 'Minimum' )],
                ['key' => 'max', 'input' => 'number', 'step' => 'any', 'default' => self::DEFAULT_MAX, 'placeholder' => (string) self::DEFAULT_MAX, 'label' => __( 'Maximum' )],
                ['key' => 'step', 'input' => 'number', 'step' => 'any', 'min' => 0, 'default' => self::DEFAULT_STEP, 'placeholder' => (string) self::DEFAULT_STEP, 'label' => __( 'Step' ), 'hint' => __( 'The slider starts at the default value, or at the minimum without one.' )],
                ['key' => 'hide_value', 'input' => 'toggle', 'label' => __( 'Hide value' ), 'hint' => __( 'Leaves out the value shown beside the slider. Screen readers still announce it.' )],
                ['key' => 'prefix', 'max_length' => self::AFFIX_MAX_LENGTH, 'label' => __( 'Value Prefix' ), 'placeholder' => '$'],
                ['key' => 'suffix', 'max_length' => self::AFFIX_MAX_LENGTH, 'label' => __( 'Value Suffix' ), 'placeholder' => '%'],
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
        $limits = self::limits( $field );

        return [
            'numeric',
            'between:' . self::format( $limits['min'] ) . ',' . self::format( $limits['max'] ),
            SubmissionRules::STEP . ':' . self::format( $limits['min'] ) . ',' . self::format( $limits['step'] ),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $limits  = self::limits( $field );
        $replace = [
            'field' => $field->label,
            'min'   => self::valueText( $field, self::format( $limits['min'] ) ),
            'max'   => self::valueText( $field, self::format( $limits['max'] ) ),
            'step'  => self::format( $limits['step'] ),
        ];

        return [
            'numeric'             => __( 'Please choose a value for :field.', $replace ),
            'between'             => __( 'The :field must be between :min and :max.', $replace ),
            SubmissionRules::STEP => __( 'The :field must be in steps of :step.', $replace ),
        ];
    }

    /**
     * Shows the value with the slider's prefix and suffix.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return string The display value.
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $stored = $value->value ?? '';
        $field  = $this->sourceField( $value );

        return '' === $stored || null === $field ? $stored : self::valueText( $field, $stored );
    }

    /**
     * Reads a setting or value as a number.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The value.
     *
     * @return float|int|null The number, or null when it is not numeric.
     */
    protected static function number( mixed $value ): float|int|null
    {
        if ( is_int( $value ) || is_float( $value ) ) {
            return is_finite( (float) $value ) ? $value : null;
        }

        if ( ! is_string( $value ) || ! is_numeric( trim( $value ) ) ) {
            return null;
        }

        return 0 + trim( $value );
    }

    /**
     * Formats a number without trailing zeros or exponent notation.
     *
     * @since 1.6.0
     *
     * @param  float|int  $number  The number.
     *
     * @return string The number.
     */
    protected static function format( float|int $number ): string
    {
        if ( is_int( $number ) || floor( $number ) === $number && abs( $number ) < PHP_INT_MAX ) {
            return (string) (int) $number;
        }

        return rtrim( rtrim( number_format( $number, 10, '.', '' ), '0' ), '.' );
    }
}
