<?php

/**
 * Rating field type handler.
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

/**
 * Star rating from 1 to `max_rating`, optionally in half stars.
 *
 * A value of 0 means "no rating" and is stored as an empty value.
 *
 * @since 1.6.0
 */
class RatingFieldType extends AbstractFieldType
{
    /**
     * The largest `max_rating` the builders offer.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_STARS = 10;

    /**
     * The `max_rating` used when the field sets none.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DEFAULT_MAX_RATING = 5;

    /**
     * Star sizes the renderers accept.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const SIZES = ['xs', 'sm', 'md', 'lg'];

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'rating';

    /**
     * Gets a rating field's number of stars.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return int The number of stars, from 1 to MAX_STARS.
     */
    public static function maxRating( FormField $field ): int
    {
        $max = $field->getConfig( 'max_rating' );

        if ( ! is_numeric( $max ) || (int) $max < 1 ) {
            return self::DEFAULT_MAX_RATING;
        }

        return min( (int) $max, self::MAX_STARS );
    }

    /**
     * Checks whether a rating field accepts half stars.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True when half stars are allowed.
     */
    public static function allowsHalf( FormField $field ): bool
    {
        return filter_var( $field->getConfig( 'allow_half', false ), FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Stores a rating as a plain number, and "no rating" (0) as empty.
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
        if ( ! is_numeric( $value ) ) {
            return $value;
        }

        $rating = (float) $value;

        if ( 0.0 === $rating ) {
            return '';
        }

        return floor( $rating ) === $rating ? (string) (int) $rating : (string) $rating;
    }

    /**
     * Exports the numeric rating rather than its display form.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  FormSubmissionValue|null  $value  The stored value.
     *
     * @return list<string> The cells.
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        return [$value?->value ?? ''];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['rating'], [
            'label'    => __( 'Rating' ),
            'defaults' => [
                'label'        => __( 'Rating' ),
                'field_config' => [
                    'max_rating' => self::DEFAULT_MAX_RATING,
                ],
            ],
            'settings' => [
                [
                    'key'         => 'max_rating',
                    'input'       => 'integer',
                    'min'         => 1,
                    'max'         => self::MAX_STARS,
                    'default'     => self::DEFAULT_MAX_RATING,
                    'placeholder' => (string) self::DEFAULT_MAX_RATING,
                    'label'       => __( 'Max Rating' ),
                    'hint'        => __( 'Number of stars, from 1 to :max.', ['max' => self::MAX_STARS] ),
                ],
                ['key' => 'allow_half', 'input' => 'toggle', 'label' => __( 'Allow half stars' )],
                [
                    'key'     => 'size',
                    'input'   => 'select',
                    'default' => 'md',
                    'label'   => __( 'Star Size' ),
                    'options' => [
                        ['value' => 'xs', 'label' => __( 'Extra Small' )],
                        ['value' => 'sm', 'label' => __( 'Small' )],
                        ['value' => 'md', 'label' => __( 'Medium' )],
                        ['value' => 'lg', 'label' => __( 'Large' )],
                    ],
                ],
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * Gets the rules for a number of stars within range, in whole or half steps.
     *
     * A required rating must be above 0, since 0 is the "no rating" choice.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<string> The rules.
     */
    protected function typeRules( FormField $field ): array
    {
        $rules = ['numeric', 'between:0,' . self::maxRating( $field )];

        $rules[] = self::allowsHalf( $field ) ? 'multiple_of:0.5' : 'multiple_of:1';

        if ( $field->is_required ) {
            $rules[] = 'gt:0';
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
        $max = self::maxRating( $field );

        return [
            'numeric'     => __( 'Please choose a rating for :field.', ['field' => $field->label] ),
            'between'     => __( 'The :field rating must be between 0 and :max.', ['field' => $field->label, 'max' => $max] ),
            'multiple_of' => self::allowsHalf( $field )
                ? __( 'The :field rating must be a whole or half star.', ['field' => $field->label] )
                : __( 'The :field rating must be a whole star.', ['field' => $field->label] ),
            'gt'          => __( 'Please choose a rating for :field.', ['field' => $field->label] ),
        ];
    }

    /**
     * Shows a rating against its maximum, such as "4 / 5".
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return string The display value, or an empty string when unrated.
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $rating = $value->value ?? '';

        if ( ! is_numeric( $rating ) || 0.0 === (float) $rating ) {
            return '';
        }

        $field = $this->sourceField( $value );

        if ( null === $field ) {
            return $rating;
        }

        return __( ':rating / :max', ['rating' => $rating, 'max' => self::maxRating( $field )] );
    }
}
