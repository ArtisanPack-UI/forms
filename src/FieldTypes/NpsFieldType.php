<?php

/**
 * Net Promoter Score field type handler.
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
 * A Net Promoter Score question: a 0–10 scale as a radio group, with labels
 * at its ends ("Not likely" / "Extremely likely").
 *
 * The score is stored as a whole number; 0 is a score, not "no answer". With
 * `show_category` on, submissions show the score's category (promoter 9–10,
 * passive 7–8, detractor 0–6) and the CSV export adds a column for it.
 *
 * @since 1.6.0
 */
class NpsFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'nps';

    /**
     * The lowest score.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MIN_SCORE = 0;

    /**
     * The highest score.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_SCORE = 10;

    /**
     * The lowest promoter score.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const PROMOTER_MIN = 9;

    /**
     * The lowest passive score.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const PASSIVE_MIN = 7;

    /**
     * The longest end label.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const LABEL_MAX_LENGTH = 100;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'nps';

    /**
     * Gets the labels shown at the ends of the scale.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array{low: string, high: string} The labels, defaulting to "Not likely" and "Extremely likely".
     */
    public static function endLabels( FormField $field ): array
    {
        $labels = [];

        foreach ( ['low' => __( 'Not likely' ), 'high' => __( 'Extremely likely' )] as $end => $default ) {
            $label          = $field->getConfig( "{$end}_label" );
            $labels[ $end ] = is_string( $label ) && '' !== trim( $label ) ? mb_substr( trim( $label ), 0, self::LABEL_MAX_LENGTH ) : $default;
        }

        return $labels;
    }

    /**
     * Checks whether submissions show each score's category.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True when the `show_category` setting is on.
     */
    public static function showsCategory( FormField $field ): bool
    {
        return true === filter_var( $field->getConfig( 'show_category', false ), FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Gets a score's category.
     *
     * @since 1.6.0
     *
     * @param  mixed  $score  The score.
     *
     * @return string|null `promoter`, `passive` or `detractor`, or null when the score is not 0–10.
     */
    public static function category( mixed $score ): ?string
    {
        if ( ! is_numeric( $score ) || (float) $score !== (float) (int) $score ) {
            return null;
        }

        $score = (int) $score;

        return match ( true ) {
            $score < self::MIN_SCORE || $score > self::MAX_SCORE => null,
            $score >= self::PROMOTER_MIN                         => 'promoter',
            $score >= self::PASSIVE_MIN                          => 'passive',
            default                                              => 'detractor',
        };
    }

    /**
     * Gets a category's translated name.
     *
     * @since 1.6.0
     *
     * @param  string  $category  `promoter`, `passive` or `detractor`.
     *
     * @return string The name.
     */
    public static function categoryLabel( string $category ): string
    {
        return match ( $category ) {
            'promoter' => __( 'Promoter' ),
            'passive'  => __( 'Passive' ),
            default    => __( 'Detractor' ),
        };
    }

    /**
     * {@inheritDoc}
     *
     * Stores the score as a whole number.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_numeric( $value ) ? (string) (int) $value : $value;
    }

    /**
     * {@inheritDoc}
     *
     * Adds a category column when the field shows categories.
     *
     * @since 1.6.0
     */
    public function exportColumns( FormField $field ): array
    {
        $columns = parent::exportColumns( $field );

        if ( self::showsCategory( $field ) ) {
            $columns[] = __( ':field (Category)', ['field' => $field->getAdminLabel()] );
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     *
     * The score, and its category when the field shows categories.
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        $score = null === $value || $value->isRedacted() ? ( $value?->display_value ?? '' ) : ( $value->value ?? '' );
        $cells = [$score];

        if ( self::showsCategory( $field ) ) {
            $category = $value?->isRedacted() ? null : self::category( $score );
            $cells[]  = null === $category ? '' : self::categoryLabel( $category );
        }

        return $cells;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['nps'], [
            'label'    => __( 'Net Promoter Score' ),
            'defaults' => [
                'label' => __( 'How likely are you to recommend us to a friend or colleague?' ),
            ],
            'settings' => [
                ['key' => 'low_label', 'max_length' => self::LABEL_MAX_LENGTH, 'label' => __( 'Low Label' ), 'placeholder' => __( 'Not likely' ), 'hint' => __( 'Shown under 0.' )],
                ['key' => 'high_label', 'max_length' => self::LABEL_MAX_LENGTH, 'label' => __( 'High Label' ), 'placeholder' => __( 'Extremely likely' ), 'hint' => __( 'Shown under 10.' )],
                ['key' => 'show_category', 'input' => 'toggle', 'label' => __( 'Show category' ), 'hint' => __( 'Tags each score as a promoter (9–10), passive (7–8) or detractor (0–6) in submissions and exports.' )],
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
        return ['integer', 'between:' . self::MIN_SCORE . ',' . self::MAX_SCORE];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $message = __( 'Please choose a score from :min to :max for :field.', ['min' => self::MIN_SCORE, 'max' => self::MAX_SCORE, 'field' => $field->label] );

        return ['integer' => $message, 'between' => $message];
    }

    /**
     * Shows the score, with its category when the field shows categories ("9 – Promoter").
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return string The display value.
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $score = $value->value ?? '';
        $field = $this->sourceField( $value );

        if ( '' === $score || null === $field || ! self::showsCategory( $field ) || null === ( $category = self::category( $score ) ) ) {
            return $score;
        }

        return __( ':score – :category', ['score' => $score, 'category' => self::categoryLabel( $category )] );
    }
}
