<?php

/**
 * Number field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\NumberFormat;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * Numeric input.
 *
 * A number typed in the field's `number_format` is converted to a plain
 * decimal before validation, by the Livewire renderer and the submit API
 * (see SubmissionService::normalizeFormattedNumbers()), so the value is
 * stored as validated. The conversion is not repeated on save, since a
 * plain decimal such as `1.5` would be read again as a grouped number.
 *
 * @since 1.6.0
 */
class NumberFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'number';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['number'], [
            'label'    => __( 'Number' ),
            'defaults' => [
                'label'       => __( 'Number' ),
                'placeholder' => '',
            ],
            'settings' => [
                ['key' => 'min', 'input' => 'number', 'store' => 'validation_rules', 'tab' => 'validation', 'step' => 'any', 'label' => __( 'Min Value' )],
                ['key' => 'max', 'input' => 'number', 'store' => 'validation_rules', 'tab' => 'validation', 'step' => 'any', 'label' => __( 'Max Value' )],
                [
                    'key'         => 'step',
                    'input'       => 'number',
                    'store'       => 'validation_rules',
                    'tab'         => 'validation',
                    'step'        => 'any',
                    'min'         => 0,
                    'label'       => __( 'Step Increment' ),
                    'placeholder' => '1',
                    'hint'        => __( 'e.g., 0.01 for decimals, 1 for integers' ),
                ],
                [
                    'key'     => 'number_format',
                    'input'   => 'select',
                    'label'   => __( 'Number Format' ),
                    'hint'    => __( 'How visitors type numbers. Formatted numbers are stored as plain decimals; currency is rounded to two decimal places.' ),
                    'options' => NumberFormat::options(),
                ],
                [
                    'key'   => 'integer_only',
                    'input' => 'toggle',
                    'store' => 'validation_rules',
                    'tab'   => 'validation',
                    'label' => __( 'Whole numbers only' ),
                    'hint'  => __( 'Rejects decimals, whatever the step increment.' ),
                ],
                ['key' => 'prefix', 'max_length' => 20, 'label' => __( 'Prefix' ), 'placeholder' => '$', 'hint' => __( 'Shown before the input, such as a currency sign.' )],
                ['key' => 'suffix', 'max_length' => 20, 'label' => __( 'Suffix' ), 'placeholder' => 'kg', 'hint' => __( 'Shown after the input, such as a unit.' )],
                $this->noDuplicatesSetting(),
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
        $rules = $field->isIntegerOnly() ? ['numeric', 'multiple_of:1'] : ['numeric'];

        if ( null !== ( $step = $this->stepIncrement( $field ) ) ) {
            $rules[] = SubmissionRules::STEP . ':' . $this->stepBase( $field ) . ',' . $step;
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $messages = $field->isIntegerOnly() ? ['multiple_of' => __( 'Please enter a whole number for :field.', ['field' => $field->label] )] : [];

        if ( null !== ( $step = $this->stepIncrement( $field ) ) ) {
            $messages[ SubmissionRules::STEP ] = __( 'The :field must be in steps of :step.', ['field' => $field->label, 'step' => $step] );
        }

        return $messages;
    }

    /**
     * Gets the "Step Increment" a value must be a whole number of steps from "Min Value" by.
     *
     * The browser checks it too (`validateNumericRules()` in
     * `resources/js/shared/validation.ts`).
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string|null The step, or null when unset or not positive.
     */
    protected function stepIncrement( FormField $field ): ?string
    {
        $step = $this->validationLimit( $field, 'step' );

        return null !== $step && is_numeric( $step ) && (float) $step > 0 ? $step : null;
    }

    /**
     * Gets the number steps are counted from: "Min Value", or 0.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string The base.
     */
    protected function stepBase( FormField $field ): string
    {
        $min = $this->validationLimit( $field, 'min' );

        return null !== $min && is_numeric( $min ) ? $min : '0';
    }
}
