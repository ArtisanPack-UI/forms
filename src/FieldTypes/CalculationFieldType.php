<?php

/**
 * Calculation field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\Calculation;

/**
 * A read-only number worked out from other fields with a formula, such as
 * `{quantity} * {price}` (see Calculation).
 *
 * The renderers show the result as the visitor fills the form in, rounded
 * to the field's decimal places. The value a client sends is never trusted:
 * SubmissionService::applyCalculations() works every calculation out again
 * from the submitted values before they are validated and stored.
 * `resources/js/shared/calculation.ts` mirrors the parser for React and Vue.
 *
 * @since 1.6.0
 */
class CalculationFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'calculation';

    /**
     * The decimal places used when the field sets none.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DEFAULT_DECIMALS = 2;

    /**
     * The most decimal places a result may have.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_DECIMALS = 10;

    /**
     * The types a formula cannot read a number from, left out of the builder's field picker.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const UNREADABLE_TYPES = ['file', 'signature', 'captcha', 'section', 'heading', 'paragraph', 'divider', 'html'];

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'calculation';

    /**
     * Gets a calculation's formula.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string The formula, or an empty string when unset.
     */
    public static function formula( FormField $field ): string
    {
        $formula = $field->getConfig( 'formula' );

        return is_string( $formula ) ? trim( $formula ) : '';
    }

    /**
     * Gets the decimal places a calculation's result is rounded to.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return int The decimal places, from 0 to MAX_DECIMALS.
     */
    public static function decimals( FormField $field ): int
    {
        $decimals = $field->getConfig( 'decimals' );

        if ( ! is_numeric( $decimals ) ) {
            return self::DEFAULT_DECIMALS;
        }

        return max( 0, min( self::MAX_DECIMALS, (int) $decimals ) );
    }

    /**
     * Gets the fields a calculation's formula may use, for the builder's field picker.
     *
     * Every field of the form but the calculation itself, administrative
     * fields (the public form never has their values) and the types in
     * UNREADABLE_TYPES. `getFormulaFields()` in
     * `resources/js/shared/calculation.ts` matches.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The calculation.
     * @param  iterable<FormField>  $fields  The form's fields.
     *
     * @return list<array{name: string, label: string}> The fields, with their merge tag name and label.
     */
    public static function formulaFields( FormField $field, iterable $fields ): array
    {
        $available = [];

        foreach ( $fields as $candidate ) {
            if ( $candidate->is( $field ) || $candidate->name === $field->name || in_array( $candidate->type, self::UNREADABLE_TYPES, true ) || $candidate->isAdministrative() ) {
                continue;
            }

            $available[] = ['name' => (string) $candidate->name, 'label' => (string) ( $candidate->getAdminLabel() ?: $candidate->name )];
        }

        return $available;
    }

    /**
     * Works out a calculation's value.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  callable(string): mixed  $resolve  Reads a field's value by name.
     *
     * @return string The rounded result, or an empty string when the formula has none (it is not valid, or divides by zero).
     */
    public static function compute( FormField $field, callable $resolve ): string
    {
        return Calculation::format( Calculation::evaluate( self::formula( $field ), $resolve ), self::decimals( $field ) );
    }

    /**
     * {@inheritDoc}
     *
     * The result is worked out on the server, so the client's value is not
     * checked; it is replaced before validation.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        return ['' => ['nullable']];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['calculation'], [
            'label'    => __( 'Calculation' ),
            'defaults' => [
                'label'        => __( 'Total' ),
                'field_config' => [
                    'formula'  => '',
                    'decimals' => self::DEFAULT_DECIMALS,
                ],
            ],
            'settings' => [
                [
                    'key'         => 'formula',
                    'input'       => 'formula',
                    'max_length'  => Calculation::MAX_LENGTH,
                    'label'       => __( 'Formula' ),
                    'placeholder' => '{quantity} * {price}',
                    'hint'        => __( 'Use numbers, field merge tags such as {price}, + - * / and parentheses. Blank and non-numeric values count as 0.' ),
                    'monospace'   => true,
                ],
                [
                    'key'         => 'decimals',
                    'input'       => 'integer',
                    'min'         => 0,
                    'max'         => self::MAX_DECIMALS,
                    'default'     => self::DEFAULT_DECIMALS,
                    'placeholder' => (string) self::DEFAULT_DECIMALS,
                    'label'       => __( 'Decimal Places' ),
                    'hint'        => __( 'The result is rounded to this many decimal places.' ),
                ],
            ],
        ] );
    }
}
