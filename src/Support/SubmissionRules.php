<?php

/**
 * Submission validation rules.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Enums\SubmissionStatus;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

/**
 * Validation rules registered by name, so field settings can add them as
 * strings and the custom error message setting applies to them.
 *
 * - `forms_no_duplicates:{field_id}[,ci][,{ignored_submission_id}]` fails when
 *   the value was already submitted for the field (`ci`: case-insensitively).
 * - `forms_other_choice` fails when an "Other" choice was submitted without its
 *   text, or with text longer than FormField::OTHER_CHOICE_MAX_LENGTH.
 * - `forms_max_words:{max}[,html]` fails when a text has more than `max` words
 *   (`html`: the words of rich text, without its markup).
 * - `forms_min_text:{min}` and `forms_max_text:{max}` fail when rich text has
 *   fewer or more characters of text than the limit (see RichText).
 * - `forms_email_domain:{allow|block},{domain},…` fails when an address's
 *   domain is not on the allow list, or is on the block list (see EmailDomains).
 * - `forms_disabled_dates:[weekends,]{Y-m-d},…` fails on a disabled day.
 * - `forms_min_age:{years}` and `forms_max_age:{years}` fail when a birthdate
 *   is too recent or too long ago (see DateConstraints).
 * - `forms_total_size:{kilobytes}` fails when a list of files is larger in total.
 * - `forms_image_dimensions:{min_width},{max_width},{min_height},{max_height}`
 *   fails when an image is outside the bounds; a blank bound is not checked
 *   and files that are not images pass.
 * - `forms_date_range` fails when a date range has only one of its dates, or
 *   ends before it starts.
 *
 * @since 1.6.0
 */
class SubmissionRules
{
    /**
     * The name of the "No duplicates" rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const NO_DUPLICATES = 'forms_no_duplicates';

    /**
     * The name of the "Other" choice rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const OTHER_CHOICE = 'forms_other_choice';

    /**
     * The name of the word limit rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MAX_WORDS = 'forms_max_words';

    /**
     * The name of the rich text minimum length rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MIN_TEXT = 'forms_min_text';

    /**
     * The name of the rich text maximum length rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MAX_TEXT = 'forms_max_text';

    /**
     * The name of the email domain rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const EMAIL_DOMAIN = 'forms_email_domain';

    /**
     * The name of the disabled dates rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const DISABLED_DATES = 'forms_disabled_dates';

    /**
     * The name of the minimum age rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MIN_AGE = 'forms_min_age';

    /**
     * The name of the maximum age rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const MAX_AGE = 'forms_max_age';

    /**
     * The name of the total upload size rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TOTAL_SIZE = 'forms_total_size';

    /**
     * The name of the image dimensions rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const IMAGE_DIMENSIONS = 'forms_image_dimensions';

    /**
     * The name of the date range rule.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const DATE_RANGE = 'forms_date_range';

    /**
     * Registers the rules with the validator.
     *
     * @since 1.6.0
     *
     * @param  Factory  $validator  The validation factory.
     */
    public static function register( Factory $validator ): void
    {
        $validator->extend( self::NO_DUPLICATES, fn ( string $attribute, mixed $value, array $parameters ): bool => self::isUnique( $value, $parameters ) );
        $validator->extend( self::OTHER_CHOICE, fn ( string $attribute, mixed $value ): bool => self::hasOtherText( $value ) );
        $validator->extend( self::MAX_WORDS, fn ( string $attribute, mixed $value, array $parameters ): bool => self::withinWordLimit( $value, $parameters ) );
        $validator->extend( self::MIN_TEXT, fn ( string $attribute, mixed $value, array $parameters ): bool => self::withinTextLength( $value, $parameters, 'min' ) );
        $validator->extend( self::MAX_TEXT, fn ( string $attribute, mixed $value, array $parameters ): bool => self::withinTextLength( $value, $parameters, 'max' ) );
        $validator->extend( self::EMAIL_DOMAIN, fn ( string $attribute, mixed $value, array $parameters ): bool => EmailDomains::passes( $value, $parameters ) );
        $validator->extend( self::DISABLED_DATES, fn ( string $attribute, mixed $value, array $parameters ): bool => DateConstraints::isAllowedDay( $value, $parameters ) );
        $validator->extend( self::MIN_AGE, fn ( string $attribute, mixed $value, array $parameters ): bool => DateConstraints::meetsMinimumAge( $value, $parameters ) );
        $validator->extend( self::MAX_AGE, fn ( string $attribute, mixed $value, array $parameters ): bool => DateConstraints::meetsMaximumAge( $value, $parameters ) );
        $validator->extend( self::TOTAL_SIZE, fn ( string $attribute, mixed $value, array $parameters ): bool => self::withinTotalSize( $value, $parameters ) );
        $validator->extend( self::IMAGE_DIMENSIONS, fn ( string $attribute, mixed $value, array $parameters ): bool => self::withinImageDimensions( $value, $parameters ) );
        $validator->extend( self::DATE_RANGE, fn ( string $attribute, mixed $value ): bool => self::isDateRange( $value ) );
    }

    /**
     * Checks that a date range has both dates, or neither, and ends on or after its start.
     *
     * Dates that are not `Y-m-d` are left to the sub-inputs' own date rules.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted range, with `start` and `end` keys.
     *
     * @return bool True for a valid or empty range.
     */
    public static function isDateRange( mixed $value ): bool
    {
        if ( ! is_array( $value ) ) {
            return true;
        }

        $start = is_string( $value['start'] ?? null ) ? trim( $value['start'] ) : '';
        $end   = is_string( $value['end'] ?? null ) ? trim( $value['end'] ) : '';

        if ( '' === $start && '' === $end ) {
            return true;
        }

        if ( '' === $start || '' === $end ) {
            return false;
        }

        $pattern = '/^\d{4}-\d{2}-\d{2}$/';

        return 1 !== preg_match( $pattern, $start ) || 1 !== preg_match( $pattern, $end ) || $end >= $start;
    }

    /**
     * Counts the words in a text: runs of characters between whitespace.
     *
     * `resources/js/shared/counter.ts` counts the same way.
     *
     * @since 1.6.0
     *
     * @param  string  $text  The text.
     *
     * @return int The number of words.
     */
    public static function countWords( string $text ): int
    {
        $text = trim( $text );

        return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) ?: [] );
    }

    /**
     * Checks that a text has no more words than the limit.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     * @param  array<int, string>  $parameters  The word limit, then `html` to count the text of rich text.
     *
     * @return bool True when within the limit.
     */
    public static function withinWordLimit( mixed $value, array $parameters ): bool
    {
        $max = filter_var( $parameters[0] ?? null, FILTER_VALIDATE_INT );

        if ( false === $max || ! is_scalar( $value ) || is_bool( $value ) ) {
            return true;
        }

        $text = 'html' === ( $parameters[1] ?? null ) ? RichText::toPlainText( (string) $value ) : (string) $value;

        return self::countWords( $text ) <= $max;
    }

    /**
     * Checks that rich text has at least or at most a number of characters of text.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted HTML.
     * @param  array<int, string>  $parameters  The limit.
     * @param  string  $bound  `min` or `max`.
     *
     * @return bool True when within the limit.
     */
    public static function withinTextLength( mixed $value, array $parameters, string $bound ): bool
    {
        $limit = filter_var( $parameters[0] ?? null, FILTER_VALIDATE_INT );

        if ( false === $limit || ! is_string( $value ) ) {
            return true;
        }

        $length = RichText::length( $value );

        return 'min' === $bound ? $length >= $limit : $length <= $limit;
    }

    /**
     * Checks that a list of uploaded files is no larger in total than the limit.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted files.
     * @param  array<int, string>  $parameters  The limit in kilobytes.
     *
     * @return bool True when within the limit.
     */
    public static function withinTotalSize( mixed $value, array $parameters ): bool
    {
        $max = filter_var( $parameters[0] ?? null, FILTER_VALIDATE_INT );

        if ( false === $max || ! is_array( $value ) ) {
            return true;
        }

        $bytes = 0;

        foreach ( $value as $file ) {
            if ( $file instanceof UploadedFile ) {
                $bytes += (int) $file->getSize();
            }
        }

        return $bytes <= $max * 1024;
    }

    /**
     * Checks that an uploaded image's width and height are within the bounds.
     *
     * Files that are not raster images, or whose size cannot be read, pass:
     * the type rules decide which files are allowed.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted file.
     * @param  array<int, string>  $parameters  The minimum width, maximum width, minimum height and maximum height in pixels.
     *
     * @return bool True when within the bounds.
     */
    public static function withinImageDimensions( mixed $value, array $parameters ): bool
    {
        if ( ! $value instanceof UploadedFile || ! str_starts_with( (string) $value->getMimeType(), 'image/' ) || 'image/svg+xml' === $value->getMimeType() ) {
            return true;
        }

        $size = @getimagesize( $value->getRealPath() ?: '' );

        if ( false === $size ) {
            return true;
        }

        [$width, $height] = $size;
        $bounds           = array_map(
            fn ( ?string $bound ): ?int => false === filter_var( $bound, FILTER_VALIDATE_INT ) ? null : (int) $bound,
            array_pad( array_slice( $parameters, 0, 4 ), 4, null ),
        );

        [$minWidth, $maxWidth, $minHeight, $maxHeight] = $bounds;

        return ! ( ( null !== $minWidth && $width < $minWidth )
            || ( null !== $maxWidth && $width > $maxWidth )
            || ( null !== $minHeight && $height < $minHeight )
            || ( null !== $maxHeight && $height > $maxHeight ) );
    }

    /**
     * Checks that a value was not already submitted for a field.
     *
     * Only saved, non-spam, non-quarantined submissions count. The lookup is
     * narrowed by the `field_id` index before the value is compared, so it
     * stays cheap without an index on the `value` text column.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     * @param  array<int, string>  $parameters  The field ID, then optional `ci` and an ignored submission ID.
     *
     * @return bool True when no earlier submission holds the value.
     */
    public static function isUnique( mixed $value, array $parameters ): bool
    {
        $fieldId = filter_var( $parameters[0] ?? null, FILTER_VALIDATE_INT );

        if ( false === $fieldId || ! is_scalar( $value ) || is_bool( $value ) || '' === trim( (string) $value ) ) {
            return true;
        }

        $options       = array_slice( $parameters, 1 );
        $ignoredId     = null;
        $caseSensitive = true;

        foreach ( $options as $option ) {
            if ( 'ci' === $option ) {
                $caseSensitive = false;
            } elseif ( false !== filter_var( $option, FILTER_VALIDATE_INT ) ) {
                $ignoredId = (int) $option;
            }
        }

        $value = trim( (string) $value );
        $query = FormSubmissionValue::query()
            ->where( 'field_id', $fieldId )
            ->whereHas( 'submission', function ( Builder $submission ) use ( $ignoredId ): void {
                $submission->where( 'is_spam', false )
                    ->where( fn ( Builder $status ) => $status->whereNull( 'status' )->orWhere( 'status', '!=', SubmissionStatus::Quarantined->value ) )
                    ->when( null !== $ignoredId, fn ( Builder $query ) => $query->whereKeyNot( $ignoredId ) );
            } );

        $caseSensitive
            ? $query->where( 'value', $value )
            : $query->whereRaw( 'LOWER(value) = ?', [mb_strtolower( $value )] );

        return ! $query->exists();
    }

    /**
     * Checks that an "Other" choice carries its text.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value, or list of values for a checkbox group.
     *
     * @return bool True when no item is the bare "Other" placeholder or too long.
     */
    public static function hasOtherText( mixed $value ): bool
    {
        foreach ( is_array( $value ) ? $value : [$value] as $item ) {
            if ( ! is_scalar( $item ) ) {
                continue;
            }

            $item = (string) $item;

            if ( FormField::OTHER_CHOICE_VALUE === $item || mb_strlen( $item ) > FormField::OTHER_CHOICE_MAX_LENGTH ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Makes a field's duplicate check skip one submission, for admins editing it.
     *
     * @since 1.6.0
     *
     * @param  array<int, mixed>  $rules  The field's rules.
     * @param  int  $submissionId  The submission being edited.
     *
     * @return array<int, mixed> The rules.
     */
    public static function ignoringSubmission( array $rules, int $submissionId ): array
    {
        return array_map(
            fn ( mixed $rule ): mixed => is_string( $rule ) && str_starts_with( $rule, self::NO_DUPLICATES . ':' ) ? "{$rule},{$submissionId}" : $rule,
            $rules,
        );
    }
}
