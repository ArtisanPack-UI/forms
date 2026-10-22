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

/**
 * Validation rules registered by name, so field settings can add them as
 * strings and the custom error message setting applies to them.
 *
 * - `forms_no_duplicates:{field_id}[,ci][,{ignored_submission_id}]` fails when
 *   the value was already submitted for the field (`ci`: case-insensitively).
 * - `forms_other_choice` fails when an "Other" choice was submitted without its
 *   text, or with text longer than FormField::OTHER_CHOICE_MAX_LENGTH.
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
