<?php

/**
 * Date and time display support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use Carbon\CarbonImmutable;

/**
 * Formats stored dates and times for display and describes the dropdown input modes.
 *
 * Dates are always stored as `Y-m-d` and times as `H:i` (or `H:i:s`); the
 * format settings only change how they are shown and the order of the
 * separate month, day and year inputs. `resources/js/shared/dateTime.ts`
 * mirrors this for React and Vue.
 *
 * @since 1.6.0
 */
class DateTimeFormat
{
    /**
     * The date display formats and the PHP format each one uses.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public const DATE_FORMATS = [
        'mdy' => 'm/d/Y',
        'dmy' => 'd/m/Y',
        'ymd' => 'Y-m-d',
    ];

    /**
     * The order of the month, day and year inputs for each date format.
     *
     * @since 1.6.0
     *
     * @var array<string, list<string>>
     */
    public const DATE_PART_ORDER = [
        'mdy' => ['month', 'day', 'year'],
        'dmy' => ['day', 'month', 'year'],
        'ymd' => ['year', 'month', 'day'],
    ];

    /**
     * How a date field is filled in: the native picker, three dropdowns or three number inputs.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const DATE_INPUTS = ['picker', 'dropdowns', 'fields'];

    /**
     * How a time field is filled in: the native picker or hour, minute (and AM/PM) dropdowns.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const TIME_INPUTS = ['picker', 'dropdowns'];

    /**
     * The time display formats: a 24-hour or a 12-hour clock.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const TIME_FORMATS = ['24', '12'];

    /**
     * How many years before and after this year a year dropdown offers when the field sets no limits.
     *
     * @since 1.6.0
     *
     * @var array{before: int, after: int}
     */
    public const YEAR_SPAN = ['before' => 100, 'after' => 10];

    /**
     * Gets the options of the `date_format` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function dateFormatOptions(): array
    {
        return [
            ['value' => 'ymd', 'label' => __( 'YYYY-MM-DD' )],
            ['value' => 'mdy', 'label' => __( 'MM/DD/YYYY' )],
            ['value' => 'dmy', 'label' => __( 'DD/MM/YYYY' )],
        ];
    }

    /**
     * Gets the options of the `date_input` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function dateInputOptions(): array
    {
        return [
            ['value' => 'picker', 'label' => __( 'Date picker' )],
            ['value' => 'dropdowns', 'label' => __( 'Month, day and year dropdowns' )],
            ['value' => 'fields', 'label' => __( 'Month, day and year fields' )],
        ];
    }

    /**
     * Gets the options of the `time_format` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function timeFormatOptions(): array
    {
        return [
            ['value' => '24', 'label' => __( '24-hour (14:30)' )],
            ['value' => '12', 'label' => __( '12-hour (2:30 PM)' )],
        ];
    }

    /**
     * Gets the options of the `time_input` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public static function timeInputOptions(): array
    {
        return [
            ['value' => 'picker', 'label' => __( 'Time picker' )],
            ['value' => 'dropdowns', 'label' => __( 'Hour and minute dropdowns' )],
        ];
    }

    /**
     * Formats a stored `Y-m-d` date.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The stored date.
     * @param  string  $format  One of the DATE_FORMATS keys.
     *
     * @return string The formatted date, or the value unchanged when it is not a `Y-m-d` date.
     */
    public static function formatDate( string $value, string $format ): string
    {
        $date = DateConstraints::absoluteDate( trim( $value ) );

        if ( null === $date || ! isset( self::DATE_FORMATS[ $format ] ) ) {
            return $value;
        }

        return CarbonImmutable::createFromFormat( '!Y-m-d', $date )->format( self::DATE_FORMATS[ $format ] );
    }

    /**
     * Formats a stored `H:i` or `H:i:s` time.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The stored time.
     * @param  string  $format  `24` or `12`.
     *
     * @return string The formatted time, such as `2:30 PM`, or the value unchanged when it is not a time.
     */
    public static function formatTime( string $value, string $format ): string
    {
        if ( '12' !== $format || 1 !== preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', trim( $value ), $matches ) ) {
            return $value;
        }

        $hour    = (int) $matches[1];
        $clock   = 0 === $hour % 12 ? 12 : $hour % 12;
        $seconds = isset( $matches[3] ) && '' !== $matches[3] ? ':' . $matches[3] : '';

        return "{$clock}:{$matches[2]}{$seconds} " . ( $hour < 12 ? __( 'AM' ) : __( 'PM' ) );
    }

    /**
     * Gets the translated month names, keyed by month number.
     *
     * @since 1.6.0
     *
     * @return array<int, string> The names, from 1 (January) to 12.
     */
    public static function monthNames(): array
    {
        $names = [];

        foreach ( range( 1, 12 ) as $month ) {
            $names[ $month ] = CarbonImmutable::create( 2000, $month, 1 )->locale( app()->getLocale() )->translatedFormat( 'F' );
        }

        return $names;
    }

    /**
     * Gets the years a year dropdown offers, latest first.
     *
     * The field's earliest and latest allowed dates bound the list; without
     * them it runs from YEAR_SPAN years before this year to YEAR_SPAN after.
     *
     * @since 1.6.0
     *
     * @param  string|null  $min  The earliest allowed `Y-m-d` date.
     * @param  string|null  $max  The latest allowed `Y-m-d` date.
     *
     * @return list<int> The years.
     */
    public static function yearOptions( ?string $min, ?string $max ): array
    {
        $year  = (int) CarbonImmutable::today()->format( 'Y' );
        $first = null === $min ? $year - self::YEAR_SPAN['before'] : (int) substr( $min, 0, 4 );
        $last  = null === $max ? $year + self::YEAR_SPAN['after'] : (int) substr( $max, 0, 4 );

        return $first > $last ? [] : range( $last, $first );
    }

    /**
     * Gets the minutes a minute dropdown offers for a time field's step.
     *
     * A step that is a whole number of minutes (such as 900 for 15 minutes)
     * sets the interval; any other step offers every minute.
     *
     * @since 1.6.0
     *
     * @param  int|null  $step  The step in seconds.
     *
     * @return list<int> The minutes.
     */
    public static function minuteOptions( ?int $step ): array
    {
        $interval = null !== $step && $step >= 60 && 0 === $step % 60 ? intdiv( $step, 60 ) : 1;

        return $interval >= 60 ? [0] : range( 0, 59, $interval );
    }
}
