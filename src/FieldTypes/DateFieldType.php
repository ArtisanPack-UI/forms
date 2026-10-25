<?php

/**
 * Date field type handler.
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
use ArtisanPackUI\Forms\Support\DateConstraints;
use ArtisanPackUI\Forms\Support\DateTimeFormat;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * Date input.
 *
 * Dates are stored as `Y-m-d`, whichever input the field uses: the native
 * picker, or separate month, day and year dropdowns or fields grouped in a
 * fieldset (`date_input`). `date_format` orders those inputs and decides how
 * stored dates are shown in submissions, notifications and exports.
 *
 * The min and max dates may be absolute or relative to the day of the
 * submission (`today`, `+30 days`, `-18 years`), and the "No past dates" and
 * "No future dates" ranges narrow them (see FormField::getDateLimits()).
 * Weekends and specific dates can be disabled, and a minimum or maximum age
 * checks a birthdate.
 *
 * @since 1.6.0
 */
class DateFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'date';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        $limitHint = __( 'A date (YYYY-MM-DD), today, or a number of days, weeks, months or years from today, such as +30 days or -1 year.' );

        return array_merge( FieldTypes::TYPE_METADATA['date'], [
            'label'    => __( 'Date Picker' ),
            'defaults' => [
                'label' => __( 'Select Date' ),
            ],
            'settings' => [
                [
                    'key'     => 'date_input',
                    'input'   => 'select',
                    'default' => 'picker',
                    'label'   => __( 'Input Type' ),
                    'hint'    => __( 'Separate month, day and year inputs are easier to use for birthdates than a calendar.' ),
                    'options' => DateTimeFormat::dateInputOptions(),
                ],
                [
                    'key'     => 'date_format',
                    'input'   => 'select',
                    'default' => 'ymd',
                    'label'   => __( 'Date Format' ),
                    'hint'    => __( 'Orders the month, day and year inputs and formats dates in submissions. Dates are stored as YYYY-MM-DD.' ),
                    'options' => DateTimeFormat::dateFormatOptions(),
                ],
                ['key' => 'min_date', 'input' => 'date_expression', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Date' ), 'placeholder' => 'today', 'hint' => $limitHint, 'monospace' => true],
                ['key' => 'max_date', 'input' => 'date_expression', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Date' ), 'placeholder' => '+1 year', 'hint' => $limitHint, 'monospace' => true],
                [
                    'key'     => 'date_range',
                    'input'   => 'select',
                    'store'   => 'validation_rules',
                    'tab'     => 'validation',
                    'label'   => __( 'Allowed Dates' ),
                    'options' => DateConstraints::rangeOptions(),
                ],
                [
                    'key'   => 'disable_weekends',
                    'input' => 'toggle',
                    'store' => 'validation_rules',
                    'tab'   => 'validation',
                    'label' => __( 'Disable weekends' ),
                    'hint'  => __( 'Rejects Saturdays and Sundays.' ),
                ],
                [
                    'key'         => 'disabled_dates',
                    'input'       => 'list',
                    'store'       => 'validation_rules',
                    'tab'         => 'validation',
                    'label'       => __( 'Disabled Dates' ),
                    'placeholder' => '2026-12-25, 2027-01-01',
                    'hint'        => __( 'Dates that cannot be chosen, as YYYY-MM-DD, comma-separated.' ),
                    'monospace'   => true,
                ],
                [
                    'key'   => 'min_age',
                    'input' => 'integer',
                    'store' => 'validation_rules',
                    'tab'   => 'validation',
                    'min'   => 0,
                    'max'   => 150,
                    'label' => __( 'Minimum Age' ),
                    'hint'  => __( 'For a birthdate: the youngest age, in years, a visitor may be.' ),
                ],
                [
                    'key'   => 'max_age',
                    'input' => 'integer',
                    'store' => 'validation_rules',
                    'tab'   => 'validation',
                    'min'   => 0,
                    'max'   => 150,
                    'label' => __( 'Maximum Age' ),
                    'hint'  => __( 'For a birthdate: the oldest age, in years, a visitor may be.' ),
                ],
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * Adds the disabled days and age rules. Those rules read `Y-m-d` dates
     * only, so with any of them the value must be one (`date_format:Y-m-d`);
     * otherwise a date written another way, such as `June 15 2015`, would
     * pass the `date` rule and skip them.
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        $rules    = [];
        $disabled = $field->getDisabledDates();

        if ( $field->disablesWeekends() ) {
            array_unshift( $disabled, 'weekends' );
        }

        if ( [] !== $disabled ) {
            $rules[] = SubmissionRules::DISABLED_DATES . ':' . implode( ',', $disabled );
        }

        foreach ( ['min_age' => SubmissionRules::MIN_AGE, 'max_age' => SubmissionRules::MAX_AGE] as $key => $rule ) {
            if ( null !== ( $age = $field->getAgeLimit( $key ) ) ) {
                $rules[] = "{$rule}:{$age}";
            }
        }

        return [] === $rules ? ['date'] : ['date', 'date_format:Y-m-d', ...$rules];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $messages = [];

        if ( $field->disablesWeekends() || [] !== $field->getDisabledDates() || null !== $field->getAgeLimit( 'min_age' ) || null !== $field->getAgeLimit( 'max_age' ) ) {
            $messages['date_format'] = __( 'Please enter a valid date for :field.', ['field' => $field->label] );
        }

        if ( $field->disablesWeekends() || [] !== $field->getDisabledDates() ) {
            $messages[ SubmissionRules::DISABLED_DATES ] = __( 'The date chosen for :field is not available. Please choose another date.', ['field' => $field->label] );
        }

        if ( null !== ( $minAge = $field->getAgeLimit( 'min_age' ) ) ) {
            $messages[ SubmissionRules::MIN_AGE ] = trans_choice( 'You must be at least :age year old for :field.|You must be at least :age years old for :field.', $minAge, ['age' => $minAge, 'field' => $field->label] );
        }

        if ( null !== ( $maxAge = $field->getAgeLimit( 'max_age' ) ) ) {
            $messages[ SubmissionRules::MAX_AGE ] = trans_choice( 'You must be no older than :age year for :field.|You must be no older than :age years for :field.', $maxAge, ['age' => $maxAge, 'field' => $field->label] );
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     *
     * The min and max dates are resolved for today, so a relative limit such
     * as `today` becomes the `Y-m-d` date it stands for.
     *
     * @since 1.6.0
     */
    protected function limitRules( FormField $field ): array
    {
        $limits = $field->getDateLimits();
        $rules  = [];

        if ( null !== $limits['min'] ) {
            $rules[] = "after_or_equal:{$limits['min']}";
        }

        if ( null !== $limits['max'] ) {
            $rules[] = "before_or_equal:{$limits['max']}";
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * Shows the date in the field's date format.
     *
     * @since 1.6.0
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $stored = $value->value ?? '';
        $field  = $this->sourceField( $value );

        return null === $field ? $stored : DateTimeFormat::formatDate( $stored, $field->getDateFormat() );
    }
}
