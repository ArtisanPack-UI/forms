<?php

/**
 * Date and time field type handler.
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
 * One date and time, such as an appointment.
 *
 * Renderers use a native `datetime-local` input, which submits
 * `YYYY-MM-DDTHH:MM`; the value is checked and stored as `Y-m-d H:i` (see
 * normalizeValue()). The min and max dates (absolute or relative, see
 * DateConstraints) bound the day, from the start of the min date to the end
 * of the max date. Submissions show the value in the field's date and time
 * formats.
 *
 * @since 1.6.0
 */
class DateTimeFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'datetime';

    /**
     * The format date and time values are checked and stored in.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const STORAGE_FORMAT = 'Y-m-d H:i';

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'datetime';

    /**
     * Converts a `datetime-local` value (`YYYY-MM-DDTHH:MM`, with optional seconds) to `Y-m-d H:i`.
     *
     * Other values are returned as they are, for the rules to reject.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     *
     * @return mixed The value.
     */
    public static function normalizeValue( mixed $value ): mixed
    {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        $value = trim( $value );

        if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2}(\.\d+)?)?$/', $value, $matches ) ) {
            return "{$matches[1]} {$matches[2]}";
        }

        return $value;
    }

    /**
     * Converts a stored `Y-m-d H:i` value to the `YYYY-MM-DDTHH:MM` a `datetime-local` input takes.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The stored value.
     *
     * @return string The input value, or `''` when it is not a date and time.
     */
    public static function toInputValue( mixed $value ): string
    {
        $value = self::normalizeValue( $value );

        return is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value ) ? str_replace( ' ', 'T', $value ) : '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return self::normalizeValue( $value );
    }

    /**
     * {@inheritDoc}
     *
     * In the `YYYY-MM-DDTHH:MM` form the input shows.
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return self::toInputValue( parent::readOnlyValue( $field ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        $limitHint = __( 'A date (YYYY-MM-DD), today, or a number of days, weeks, months or years from today, such as +30 days or -1 year.' );

        return array_merge( FieldTypes::TYPE_METADATA['datetime'], [
            'label'    => __( 'Date & Time' ),
            'defaults' => [
                'label' => __( 'Date and Time' ),
            ],
            'settings' => [
                [
                    'key'     => 'date_format',
                    'input'   => 'select',
                    'default' => 'ymd',
                    'label'   => __( 'Date Format' ),
                    'hint'    => __( 'How the date is shown in submissions. Values are stored as YYYY-MM-DD HH:MM.' ),
                    'options' => DateTimeFormat::dateFormatOptions(),
                ],
                [
                    'key'     => 'time_format',
                    'input'   => 'select',
                    'default' => '24',
                    'label'   => __( 'Time Format' ),
                    'hint'    => __( 'How the time is shown in submissions. The picker follows the visitor\'s browser.' ),
                    'options' => DateTimeFormat::timeFormatOptions(),
                ],
                ['key' => 'min_date', 'input' => 'date_expression', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Date' ), 'placeholder' => 'today', 'hint' => $limitHint, 'monospace' => true],
                ['key' => 'max_date', 'input' => 'date_expression', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Date' ), 'placeholder' => '+1 year', 'hint' => $limitHint, 'monospace' => true],
                [
                    'key'         => 'step',
                    'input'       => 'integer',
                    'tab'         => 'validation',
                    'min'         => 60,
                    'label'       => __( 'Step (seconds)' ),
                    'placeholder' => '60',
                    'hint'        => __( 'e.g., 900 for 15-minute intervals.' ),
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
        return ['date_format:' . self::STORAGE_FORMAT];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $limits = $field->getDateLimits();

        return array_filter( [
            'date_format'     => __( 'Please enter a valid date and time for :field.', ['field' => $field->label] ),
            'after_or_equal'  => null === $limits['min'] ? null : __( 'The :field must be on or after :date.', ['field' => $field->label, 'date' => DateTimeFormat::formatDate( $limits['min'], $field->getDateFormat() )] ),
            'before_or_equal' => null === $limits['max'] ? null : __( 'The :field must be on or before :date.', ['field' => $field->label, 'date' => DateTimeFormat::formatDate( $limits['max'], $field->getDateFormat() )] ),
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * The min date is the start of its day and the max date the end of it.
     *
     * @since 1.6.0
     */
    protected function limitRules( FormField $field ): array
    {
        $limits = $field->getDateLimits();
        $rules  = [];

        if ( null !== $limits['min'] ) {
            $rules[] = "after_or_equal:{$limits['min']} 00:00";
        }

        if ( null !== $limits['max'] ) {
            $rules[] = "before_or_equal:{$limits['max']} 23:59";
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * Shows the date and time in the field's formats.
     *
     * @since 1.6.0
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $stored = $value->value ?? '';
        $field  = $this->sourceField( $value );

        if ( null === $field || 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', $stored, $matches ) ) {
            return $stored;
        }

        return DateTimeFormat::formatDate( $matches[1], $field->getDateFormat() ) . ' ' . DateTimeFormat::formatTime( $matches[2], $field->getTimeFormat() );
    }
}
