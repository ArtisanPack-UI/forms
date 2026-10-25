<?php

/**
 * Date field constraint support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves a date field's limits and checks dates against its disabled days and age limits.
 *
 * A min or max date is an absolute `Y-m-d` date or an expression relative to
 * the day the form is submitted: `today`, or a signed number of days, weeks,
 * months or years such as `+30 days` or `-18 years`. Months and years do not
 * overflow, so a month after 31 January is 28 (or 29) February. Days are
 * read in the application's timezone.
 *
 * `resources/js/shared/dateConstraints.ts` mirrors this for React and Vue,
 * reading days in the visitor's timezone.
 *
 * @since 1.6.0
 */
class DateConstraints
{
    /**
     * The `date_range` presets: `no_past` starts the range today, `no_future` ends it today.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const RANGES = ['no_past', 'no_future'];

    /**
     * The pattern a relative expression matches.
     *
     * @since 1.6.0
     *
     * @var string
     */
    protected const RELATIVE_PATTERN = '/^(?:today|([+-])\s*(\d{1,4})\s*(day|week|month|year)s?)$/i';

    /**
     * Gets the options of the `date_range` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function rangeOptions(): array
    {
        return [
            ['value' => '', 'label' => __( 'Any date' )],
            ['value' => 'no_past', 'label' => __( 'No past dates' )],
            ['value' => 'no_future', 'label' => __( 'No future dates' )],
        ];
    }

    /**
     * Parses a min or max date typed in the builder.
     *
     * @since 1.6.0
     *
     * @param  string  $raw  The trimmed input.
     *
     * @return string|null A valid `Y-m-d` date or a normalized expression (`today`, `+30 days`), or null when invalid.
     */
    public static function parseExpression( string $raw ): ?string
    {
        $raw = trim( $raw );

        if ( null !== self::absoluteDate( $raw ) ) {
            return $raw;
        }

        if ( 1 !== preg_match( self::RELATIVE_PATTERN, $raw, $matches ) ) {
            return null;
        }

        if ( ! isset( $matches[1] ) || '' === $matches[1] || 0 === (int) $matches[2] ) {
            return 'today';
        }

        $amount = (int) $matches[2];
        $unit   = strtolower( $matches[3] ) . ( 1 === $amount ? '' : 's' );

        return "{$matches[1]}{$amount} {$unit}";
    }

    /**
     * Resolves a min or max date to the `Y-m-d` date it stands for.
     *
     * @since 1.6.0
     *
     * @param  mixed  $expression  The stored limit.
     * @param  CarbonInterface|null  $today  The day to resolve relative expressions from; today when null.
     *
     * @return string|null The date, or null when the limit is unset or invalid.
     */
    public static function resolve( mixed $expression, ?CarbonInterface $today = null ): ?string
    {
        if ( ! is_string( $expression ) || null === ( $parsed = self::parseExpression( $expression ) ) ) {
            return null;
        }

        if ( null !== self::absoluteDate( $parsed ) ) {
            return $parsed;
        }

        $date = self::today( $today );

        if ( 'today' !== $parsed && 1 === preg_match( self::RELATIVE_PATTERN, $parsed, $matches ) ) {
            $amount = '-' === $matches[1] ? -(int) $matches[2] : (int) $matches[2];

            $date = match ( strtolower( $matches[3] ) ) {
                'day'   => $date->addDays( $amount ),
                'week'  => $date->addWeeks( $amount ),
                'month' => $date->addMonthsNoOverflow( $amount ),
                default => $date->addYearsNoOverflow( $amount ),
            };
        }

        return $date->format( 'Y-m-d' );
    }

    /**
     * Combines limits into the latest minimum and earliest maximum.
     *
     * @since 1.6.0
     *
     * @param  list<string|null>  $minimums  `Y-m-d` minimums; null entries are ignored.
     * @param  list<string|null>  $maximums  `Y-m-d` maximums; null entries are ignored.
     *
     * @return array{min: string|null, max: string|null} The bounds.
     */
    public static function combine( array $minimums, array $maximums ): array
    {
        $minimums = array_filter( $minimums, 'is_string' );
        $maximums = array_filter( $maximums, 'is_string' );

        return [
            'min' => [] === $minimums ? null : max( $minimums ),
            'max' => [] === $maximums ? null : min( $maximums ),
        ];
    }

    /**
     * Gets the latest birthdate of someone at least an age, today.
     *
     * @since 1.6.0
     *
     * @param  int  $age  The minimum age in years.
     * @param  CarbonInterface|null  $today  The day to count from; today when null.
     *
     * @return string The `Y-m-d` date.
     */
    public static function latestBirthdate( int $age, ?CarbonInterface $today = null ): string
    {
        return self::today( $today )->subYearsNoOverflow( $age )->format( 'Y-m-d' );
    }

    /**
     * Gets the earliest birthdate of someone at most an age, today.
     *
     * @since 1.6.0
     *
     * @param  int  $age  The maximum age in years.
     * @param  CarbonInterface|null  $today  The day to count from; today when null.
     *
     * @return string The `Y-m-d` date.
     */
    public static function earliestBirthdate( int $age, ?CarbonInterface $today = null ): string
    {
        return self::today( $today )->subYearsNoOverflow( $age + 1 )->addDay()->format( 'Y-m-d' );
    }

    /**
     * Checks a submitted birthdate against a minimum age, for the `forms_min_age` rule.
     *
     * Values that are not `Y-m-d` dates pass, leaving them to the `date` rule.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted date.
     * @param  array<int, string>  $parameters  The minimum age.
     *
     * @return bool True when old enough.
     */
    public static function meetsMinimumAge( mixed $value, array $parameters ): bool
    {
        $age  = filter_var( $parameters[0] ?? null, FILTER_VALIDATE_INT );
        $date = is_string( $value ) ? self::absoluteDate( trim( $value ) ) : null;

        return false === $age || null === $date || $date <= self::latestBirthdate( $age );
    }

    /**
     * Checks a submitted birthdate against a maximum age, for the `forms_max_age` rule.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted date.
     * @param  array<int, string>  $parameters  The maximum age.
     *
     * @return bool True when not too old.
     */
    public static function meetsMaximumAge( mixed $value, array $parameters ): bool
    {
        $age  = filter_var( $parameters[0] ?? null, FILTER_VALIDATE_INT );
        $date = is_string( $value ) ? self::absoluteDate( trim( $value ) ) : null;

        return false === $age || null === $date || $date >= self::earliestBirthdate( $age );
    }

    /**
     * Checks that a submitted date is not a disabled day, for the `forms_disabled_dates` rule.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted date.
     * @param  array<int, string>  $parameters  `weekends` to disable Saturdays and Sundays, then any `Y-m-d` dates.
     *
     * @return bool True when the date may be chosen.
     */
    public static function isAllowedDay( mixed $value, array $parameters ): bool
    {
        $date = is_string( $value ) ? self::absoluteDate( trim( $value ) ) : null;

        if ( null === $date ) {
            return true;
        }

        if ( in_array( 'weekends', $parameters, true ) && CarbonImmutable::createFromFormat( '!Y-m-d', $date )->isWeekend() ) {
            return false;
        }

        return ! in_array( $date, $parameters, true );
    }

    /**
     * Normalizes a stored list of disabled dates to valid, sorted `Y-m-d` dates.
     *
     * @since 1.6.0
     *
     * @param  mixed  $dates  The stored list, or a comma-separated string.
     *
     * @return list<string> The dates.
     */
    public static function normalizeDates( mixed $dates ): array
    {
        if ( is_string( $dates ) ) {
            $dates = explode( ',', $dates );
        }

        $normalized = [];

        foreach ( is_array( $dates ) ? $dates : [] as $date ) {
            $date = is_string( $date ) ? self::absoluteDate( trim( $date ) ) : null;

            if ( null !== $date ) {
                $normalized[ $date ] = $date;
            }
        }

        sort( $normalized );

        return array_values( $normalized );
    }

    /**
     * Gets a valid `Y-m-d` date, or null.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The value.
     *
     * @return string|null The date, or null when the value is not one.
     */
    public static function absoluteDate( string $value ): ?string
    {
        if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
            return null;
        }

        return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : null;
    }

    /**
     * Gets the day relative expressions are resolved from, at midnight.
     *
     * @since 1.6.0
     *
     * @param  CarbonInterface|null  $today  The day; today in the application's timezone when null.
     *
     * @return CarbonImmutable The day.
     */
    protected static function today( ?CarbonInterface $today ): CarbonImmutable
    {
        return null === $today ? CarbonImmutable::today() : CarbonImmutable::instance( $today )->startOfDay();
    }
}
