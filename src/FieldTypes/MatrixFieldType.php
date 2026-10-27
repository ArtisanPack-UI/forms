<?php

/**
 * Likert / matrix field type handler.
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A survey grid: rows of statements, each answered with one choice from a
 * scale of columns ("Strongly disagree" … "Strongly agree").
 *
 * Rows are set in the `rows` setting, one statement per line; each row's key
 * is its statement's slug (see parseRows()). Columns are set in `columns`,
 * one per line as `Label` or `Label | value` (see parseColumns()). Visitors
 * submit a map of row keys to column values; it is stored in `value_array`
 * for the rows that were answered, each as `{statement, answer, value}` with
 * the statement and answer label it was submitted with (see answeredRows()).
 * A required matrix needs every row answered.
 *
 * In `single` mode (or when no rows are set) the field is one scale labelled
 * by the field label, and its value is the chosen column's value, stored as
 * text.
 *
 * Submissions show one row per line ("Statement: Agree") and the CSV export
 * has a column per row. The API sends the parsed rows and columns
 * (`matrix`), and `resources/js/shared/matrix.ts` mirrors the rules for
 * React and Vue.
 *
 * @since 1.6.0
 */
class MatrixFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'matrix';

    /**
     * The layouts a matrix may use.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const MODES = ['matrix', 'single'];

    /**
     * The most rows a matrix may have.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_ROWS = 30;

    /**
     * The most columns a matrix may have.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_COLUMNS = 11;

    /**
     * The longest statement or column label.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const LABEL_MAX_LENGTH = 255;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'matrix';

    /**
     * Gets the default columns: a five-point agreement scale.
     *
     * @since 1.6.0
     *
     * @return string The `columns` setting, one per line.
     */
    public static function defaultColumns(): string
    {
        return implode( "\n", [
            __( 'Strongly disagree' ),
            __( 'Disagree' ),
            __( 'Neutral' ),
            __( 'Agree' ),
            __( 'Strongly agree' ),
        ] );
    }

    /**
     * Gets a matrix field's rows.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<array{key: string, label: string}> The rows; empty in single mode.
     */
    public static function rows( FormField $field ): array
    {
        return 'single' === self::configuredMode( $field ) ? [] : self::parseRows( $field->getConfig( 'rows' ) );
    }

    /**
     * Gets a matrix field's columns, falling back to the default scale.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<array{value: string, label: string}> The columns.
     */
    public static function columns( FormField $field ): array
    {
        $columns = self::parseColumns( $field->getConfig( 'columns' ) );

        return [] === $columns ? self::parseColumns( self::defaultColumns() ) : $columns;
    }

    /**
     * Checks whether a matrix field is a single scale rather than a grid.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True in single mode, or when no rows are set.
     */
    public static function isSingle( FormField $field ): bool
    {
        return [] === self::rows( $field );
    }

    /**
     * Parses the `rows` setting, one statement per line.
     *
     * Blank lines are skipped and at most MAX_ROWS are kept. Keys are the
     * statements' slugs with underscores, starting with `row_` when the slug
     * is empty or numeric (so the stored map never reads as a list), with
     * `_2`, `_3`… added to repeats. Renaming a statement changes its key.
     *
     * @since 1.6.0
     *
     * @param  mixed  $raw  The setting.
     *
     * @return list<array{key: string, label: string}> The rows.
     */
    public static function parseRows( mixed $raw ): array
    {
        $rows = [];
        $keys = [];

        foreach ( self::lines( $raw ) as $label ) {
            $key  = Str::slug( $label, '_' );
            $key  = '' === $key || ctype_digit( $key ) ? 'row_' . ( '' === $key ? count( $rows ) + 1 : $key ) : $key;
            $base = $key;

            for ( $suffix = 2; isset( $keys[ $key ] ); $suffix++ ) {
                $key = "{$base}_{$suffix}";
            }

            $keys[ $key ] = true;
            $rows[]       = ['key' => $key, 'label' => $label];

            if ( count( $rows ) >= self::MAX_ROWS ) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Parses the `columns` setting, one per line as `Label` or `Label | value`.
     *
     * A column without a value uses its label. Blank lines and repeated
     * values are skipped, and at most MAX_COLUMNS are kept.
     *
     * @since 1.6.0
     *
     * @param  mixed  $raw  The setting.
     *
     * @return list<array{value: string, label: string}> The columns.
     */
    public static function parseColumns( mixed $raw ): array
    {
        $columns = [];

        foreach ( self::lines( $raw ) as $line ) {
            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            $label = $parts[0];
            $value = mb_substr( '' === ( $parts[1] ?? '' ) ? $label : $parts[1], 0, self::LABEL_MAX_LENGTH );

            if ( '' === $label || isset( $columns[ $value ] ) ) {
                continue;
            }

            $columns[ $value ] = ['value' => $value, 'label' => $label];

            if ( count( $columns ) >= self::MAX_COLUMNS ) {
                break;
            }
        }

        return array_values( $columns );
    }

    /**
     * Gets the value a matrix starts with: every row unanswered, or an empty scale.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array<string, string>|string The value.
     */
    public static function initialValue( FormField $field ): array|string
    {
        return self::isSingle( $field ) ? '' : array_fill_keys( array_column( self::rows( $field ), 'key' ), '' );
    }

    /**
     * Keeps a matrix's answered rows, in row order, with the text they were answered with.
     *
     * Each row keeps its statement and the chosen column's label as they
     * read when the form was submitted, beside the column value, so a stored
     * answer still reads correctly after the rows or columns change.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  mixed  $value  The submitted column values keyed by row.
     *
     * @return array<string, array{statement: string, answer: string, value: string}> The answers keyed by row.
     */
    public static function answeredRows( FormField $field, mixed $value ): array
    {
        if ( ! is_array( $value ) ) {
            return [];
        }

        $labels  = array_column( self::columns( $field ), 'label', 'value' );
        $answers = [];

        foreach ( self::rows( $field ) as $row ) {
            $answer = $value[ $row['key'] ] ?? null;

            if ( is_scalar( $answer ) && ! is_bool( $answer ) && '' !== trim( (string) $answer ) ) {
                $answer                 = trim( (string) $answer );
                $answers[ $row['key'] ] = [
                    'statement' => $row['label'],
                    'answer'    => $labels[ $answer ] ?? $answer,
                    'value'     => $answer,
                ];
            }
        }

        return $answers;
    }

    /**
     * {@inheritDoc}
     *
     * A grid is a map of rows, each one of the column values and required
     * when the field is; a single scale is one of the column values.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        $in = (string) Rule::in( array_column( self::columns( $field ), 'value' ) );

        if ( self::isSingle( $field ) ) {
            return ['' => array_merge( [$this->presenceRule( $field ), 'string', $in], $this->settingRules( $field ) )];
        }

        $keys  = array_column( self::rows( $field ), 'key' );
        $rules = ['' => [$this->presenceRule( $field ), 'array:' . implode( ',', $keys )]];

        foreach ( $keys as $key ) {
            $rules[ $key ] = [$field->is_required ? 'required' : 'nullable', 'string', $in];
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
        $invalid = __( 'Please choose one of the available answers for :field.', ['field' => $field->label] );

        if ( self::isSingle( $field ) ) {
            return array_merge( parent::messages( $field ), ['string' => $invalid, 'in' => $invalid] );
        }

        $messages = [
            'array' => __( 'The :field field is invalid.', ['field' => $field->label] ),
        ];

        if ( $field->is_required ) {
            $messages['required'] = __( 'Please answer every row of :field.', ['field' => $field->label] );
        }

        foreach ( self::rows( $field ) as $row ) {
            $replace = ['field' => $field->label, 'row' => $row['label']];

            $messages[ "{$row['key']}.required" ] = __( ':field: please answer ":row".', $replace );
            $messages[ "{$row['key']}.string" ]   = __( ':field: please choose a valid answer for ":row".', $replace );
            $messages[ "{$row['key']}.in" ]       = __( ':field: please choose a valid answer for ":row".', $replace );
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     *
     * Keeps the answered rows of a grid, or the trimmed choice of a single scale.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        if ( self::isSingle( $field ) ) {
            return is_string( $value ) ? trim( $value ) : $value;
        }

        return self::answeredRows( $field, $value );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return self::initialValue( $field );
    }

    /**
     * {@inheritDoc}
     *
     * A grid shows one answered row per line ("Statement: Agree"); a single
     * scale shows the chosen column's label.
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        $lines = [];

        foreach ( $this->displayRows( $value ) as $row ) {
            $lines[] = __( ':row: :answer', ['row' => $row['label'], 'answer' => $row['answer']] );
        }

        return [] !== $lines ? implode( "\n", $lines ) : $this->columnLabel( $value, $value->value ?? '' );
    }

    /**
     * {@inheritDoc}
     *
     * One column per row of a grid, headed "Label: Statement".
     *
     * @since 1.6.0
     */
    public function exportColumns( FormField $field ): array
    {
        if ( self::isSingle( $field ) ) {
            return parent::exportColumns( $field );
        }

        return array_map(
            fn ( array $row ): string => __( ':field: :row', ['field' => $field->getAdminLabel(), 'row' => $row['label']] ),
            self::rows( $field ),
        );
    }

    /**
     * {@inheritDoc}
     *
     * Each row's answer, by the column label it was submitted with.
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        if ( self::isSingle( $field ) ) {
            return parent::exportValues( $field, $value );
        }

        $rows    = self::rows( $field );
        $answers = null === $value || $value->isRedacted() || ! is_array( $value->value_array ) ? [] : $value->value_array;

        return array_map( function ( array $row ) use ( $answers, $value ): string {
            if ( null !== $value && $value->isRedacted() ) {
                return $value->display_value;
            }

            return null === $value ? '' : $this->answerText( $value, $answers[ $row['key'] ] ?? '' );
        }, $rows );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['matrix'], [
            'label'    => __( 'Likert / Matrix' ),
            'defaults' => [
                'label'        => __( 'How much do you agree with each statement?' ),
                'field_config' => [
                    'rows'    => __( 'The service was fast' ) . "\n" . __( 'The staff were helpful' ),
                    'columns' => self::defaultColumns(),
                ],
            ],
            'settings' => [
                [
                    'key'     => 'mode',
                    'input'   => 'select',
                    'default' => 'matrix',
                    'label'   => __( 'Layout' ),
                    'options' => [
                        ['value' => 'matrix', 'label' => __( 'Matrix (a row per statement)' )],
                        ['value' => 'single', 'label' => __( 'Single scale' )],
                    ],
                ],
                [
                    'key'         => 'rows',
                    'input'       => 'textarea',
                    'max_length'  => 5000,
                    'label'       => __( 'Rows' ),
                    'placeholder' => __( 'The service was fast' ) . "\n" . __( 'The staff were helpful' ),
                    'hint'        => __( 'One statement per line, up to :max. Not used by a single scale.', ['max' => self::MAX_ROWS] ),
                ],
                [
                    'key'         => 'columns',
                    'input'       => 'textarea',
                    'max_length'  => 2000,
                    'label'       => __( 'Columns' ),
                    'placeholder' => self::defaultColumns(),
                    'hint'        => __( 'One choice per line, as Label or Label | value, up to :max. Defaults to a five-point agreement scale.', ['max' => self::MAX_COLUMNS] ),
                ],
            ],
        ] );
    }

    /**
     * Gets a stored grid's answered rows with their statements and answer labels.
     *
     * Uses the statement and label stored with each row. A row stored as a
     * bare column value takes the field's current statement (or its key when
     * the row or field is gone) and column label.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return list<array{label: string, answer: string}> The rows.
     */
    protected function displayRows( FormSubmissionValue $value ): array
    {
        if ( ! is_array( $value->value_array ) || [] === $value->value_array ) {
            return [];
        }

        $field  = $this->sourceField( $value );
        $labels = null === $field ? [] : array_column( self::parseRows( $field->getConfig( 'rows' ) ), 'label', 'key' );
        $rows   = [];

        foreach ( $value->value_array as $key => $answer ) {
            $statement = is_array( $answer ) && is_string( $answer['statement'] ?? null ) ? $answer['statement'] : null;

            if ( is_scalar( $answer ) || is_array( $answer ) ) {
                $rows[] = ['label' => $statement ?? $labels[ $key ] ?? (string) $key, 'answer' => $this->answerText( $value, $answer )];
            }
        }

        return $rows;
    }

    /**
     * Gets the text of one stored row's answer.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     * @param  mixed  $answer  The row's stored answer: `{statement, answer, value}`, or a bare column value.
     *
     * @return string The answer's label.
     */
    protected function answerText( FormSubmissionValue $value, mixed $answer ): string
    {
        if ( is_array( $answer ) ) {
            return is_scalar( $answer['answer'] ?? null ) ? (string) $answer['answer'] : $this->columnLabel( $value, is_scalar( $answer['value'] ?? null ) ? (string) $answer['value'] : '' );
        }

        return is_scalar( $answer ) ? $this->columnLabel( $value, (string) $answer ) : '';
    }

    /**
     * Gets a column's label by its value, falling back to the value.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     * @param  string  $answer  The column value.
     *
     * @return string The label.
     */
    protected function columnLabel( FormSubmissionValue $value, string $answer ): string
    {
        $field = $this->sourceField( $value );

        if ( '' === $answer || null === $field ) {
            return $answer;
        }

        return array_column( self::columns( $field ), 'label', 'value' )[ $answer ] ?? $answer;
    }

    /**
     * Gets a matrix field's layout setting.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string `matrix` or `single`.
     */
    protected static function configuredMode( FormField $field ): string
    {
        $mode = $field->getConfig( 'mode' );

        return is_string( $mode ) && in_array( $mode, self::MODES, true ) ? $mode : 'matrix';
    }

    /**
     * Splits a multi-line setting into its trimmed, non-blank lines.
     *
     * @since 1.6.0
     *
     * @param  mixed  $raw  The setting.
     *
     * @return list<string> The lines, each cut to LABEL_MAX_LENGTH.
     */
    protected static function lines( mixed $raw ): array
    {
        $lines = [];

        foreach ( is_string( $raw ) ? preg_split( '/\R/', $raw ) ?: [] : [] as $line ) {
            $line = mb_substr( trim( $line ), 0, self::LABEL_MAX_LENGTH );

            if ( '' !== $line ) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
