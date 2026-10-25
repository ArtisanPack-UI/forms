<?php

/**
 * Time field type handler.
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
use ArtisanPackUI\Forms\Support\DateTimeFormat;

/**
 * Time input.
 *
 * Times are stored as `H:i` (`H:i:s` with a step under a minute), whichever
 * input the field uses: the native picker or hour and minute dropdowns, with
 * an AM/PM dropdown on a 12-hour clock (`time_input`). `time_format` picks
 * the 24-hour or 12-hour clock for those dropdowns and for stored times in
 * submissions, notifications and exports.
 *
 * @since 1.6.0
 */
class TimeFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'time';

    /**
     * Stores `H:i:s` values with a zero seconds part as `H:i`.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  mixed  $value  The submitted value.
     *
     * @return mixed The value to store.
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_string( $value ) ? FormField::normalizeTimeValue( $value ) : $value;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['time'], [
            'label'    => __( 'Time Picker' ),
            'defaults' => [
                'label'        => __( 'Select Time' ),
                'field_config' => [
                    'step' => 60,
                ],
            ],
            'settings' => [
                [
                    'key'     => 'time_input',
                    'input'   => 'select',
                    'default' => 'picker',
                    'label'   => __( 'Input Type' ),
                    'hint'    => __( 'Dropdowns offer minutes at the step interval, such as every 15 minutes for a 900-second step.' ),
                    'options' => DateTimeFormat::timeInputOptions(),
                ],
                [
                    'key'     => 'time_format',
                    'input'   => 'select',
                    'default' => '24',
                    'label'   => __( 'Time Format' ),
                    'hint'    => __( 'The clock the dropdowns and submissions use. Times are stored as 24-hour HH:MM.' ),
                    'options' => DateTimeFormat::timeFormatOptions(),
                ],
                ['key' => 'min_time', 'input' => 'time', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Time' )],
                ['key' => 'max_time', 'input' => 'time', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Time' )],
                [
                    'key'         => 'step',
                    'input'       => 'integer',
                    'tab'         => 'validation',
                    'min'         => 1,
                    'default'     => 60,
                    'label'       => __( 'Step (seconds)' ),
                    'placeholder' => '60',
                    'hint'        => __( 'e.g., 900 for 15-minute intervals; under 60 lets users pick seconds' ),
                ],
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
        return ['regex:' . FormField::TIME_FORMAT_PATTERN];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return ['regex' => __( 'Please enter a valid time for :field.', ['field' => $field->label] )];
    }

    /**
     * {@inheritDoc}
     *
     * Shows the time on the field's clock.
     *
     * @since 1.6.0
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $stored = $value->value ?? '';
        $field  = $this->sourceField( $value );

        return null === $field ? $stored : DateTimeFormat::formatTime( $stored, $field->getTimeFormat() );
    }
}
