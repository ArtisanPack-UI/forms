<?php

/**
 * Date range field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\DateTimeFormat;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * A start and end date, such as a booking's arrival and departure.
 *
 * A composite of two `date` sub-inputs, `start` and `end`, stored as `Y-m-d`
 * in `value_array`. The `forms_date_range` rule requires both dates once
 * either is filled in, and an end on or after the start. The min and max
 * dates (absolute or relative, see DateConstraints) limit both. The range is
 * shown in the field's date format as `start – end`.
 *
 * @since 1.6.0
 */
class DateRangeFieldType extends CompositeFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'date_range';

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'date_range';

    /**
     * {@inheritDoc}
     *
     * Adds the min and max dates to both sub-inputs and, while both are
     * enabled, the range rule to the field.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        $rules  = parent::rules( $field );
        $limits = $field->getDateLimits();

        foreach ( ['start', 'end'] as $key ) {
            if ( ! isset( $rules[ $key ] ) ) {
                continue;
            }

            if ( null !== $limits['min'] ) {
                $rules[ $key ][] = "after_or_equal:{$limits['min']}";
            }

            if ( null !== $limits['max'] ) {
                $rules[ $key ][] = "before_or_equal:{$limits['max']}";
            }
        }

        if ( isset( $rules['start'], $rules['end'] ) ) {
            $rules[''][] = SubmissionRules::DATE_RANGE;
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
        return array_merge( parent::messages( $field ), [
            SubmissionRules::DATE_RANGE => __( 'Please enter both dates for :field, with the end date on or after the start date.', ['field' => $field->label] ),
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * Shows the filled dates in the field's date format.
     *
     * @since 1.6.0
     */
    public function format( ?FormField $field, array $parts ): string
    {
        $format = null === $field ? 'ymd' : $field->getDateFormat();
        $dates  = array_map(
            fn ( string $date ): string => DateTimeFormat::formatDate( $date, $format ),
            $this->filledParts( $field, $parts ),
        );

        return implode( (string) ( $this->definition()['format_separator'] ?? ' – ' ), $dates );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        $limitHint = __( 'A date (YYYY-MM-DD), today, or a number of days, weeks, months or years from today, such as +30 days or -1 year.' );

        return array_merge( FieldTypes::TYPE_METADATA['date_range'], [
            'label'            => __( 'Date Range' ),
            'format_separator' => ' – ',
            'defaults'         => [
                'label' => __( 'Dates' ),
            ],
            'sub_inputs' => [
                ['key' => 'start', 'label' => __( 'Start Date' ), 'type' => 'date', 'required' => true],
                ['key' => 'end', 'label' => __( 'End Date' ), 'type' => 'date', 'required' => true],
            ],
            'settings' => [
                [
                    'key'     => 'date_format',
                    'input'   => 'select',
                    'default' => 'ymd',
                    'label'   => __( 'Date Format' ),
                    'hint'    => __( 'How the dates are shown in submissions. Dates are stored as YYYY-MM-DD.' ),
                    'options' => DateTimeFormat::dateFormatOptions(),
                ],
                ['key' => 'min_date', 'input' => 'date_expression', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Date' ), 'placeholder' => 'today', 'hint' => $limitHint, 'monospace' => true],
                ['key' => 'max_date', 'input' => 'date_expression', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Date' ), 'placeholder' => '+1 year', 'hint' => $limitHint, 'monospace' => true],
            ],
        ] );
    }
}
