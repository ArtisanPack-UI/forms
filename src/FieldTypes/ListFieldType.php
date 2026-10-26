<?php

/**
 * List (repeater) field type handler.
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
 * Rows of one or more columns that visitors add with "Add another", such as
 * attendees, references or line items.
 *
 * Columns are set in the `columns` setting, one per line as
 * `Label | type | options`: `type` is `text` (the default), `email`,
 * `number` or `select`, and a select's options are comma-separated (see
 * columns()). Each column's key is its label's slug. Visitors add
 * rows up to `max_rows` and must fill in at least `min_rows` when they fill
 * in any (or the field is required); rows left blank are dropped before the
 * value is checked. The value is stored in `value_array` as a list of rows,
 * each a map of column keys to text; it is shown as a table in HTML views
 * and as one row per line elsewhere, and exported as one row per line or as
 * JSON. The API sends the parsed columns (`list.columns`), and
 * `resources/js/shared/list.ts` mirrors the rules for React and Vue.
 *
 * @since 1.6.0
 */
class ListFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'list';

    /**
     * The input types a column may use.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const COLUMN_TYPES = ['text', 'email', 'number', 'select'];

    /**
     * The most columns a list may have.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_COLUMNS = 10;

    /**
     * The most rows a list may be set to take.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_ROWS = 100;

    /**
     * The longest value a cell accepts.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const CELL_MAX_LENGTH = 255;

    /**
     * The export formats a list may use.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const EXPORT_FORMATS = ['lines', 'json'];

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'list';

    /**
     * Gets a list field's columns.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return list<array{key: string, label: string, type: string, options: list<array{value: string, label: string}>}> The columns.
     */
    public static function columns( FormField $field ): array
    {
        return self::parseColumns( $field->getConfig( 'columns' ) );
    }

    /**
     * Parses the `columns` setting, one column per line as `Label | type | options`.
     *
     * Blank lines are skipped and at most MAX_COLUMNS are kept. An unknown
     * type is `text`, as is a select without options. Keys are the labels'
     * slugs with underscores (`column_{n}` when a label has no letters or
     * digits), with `_2`, `_3`… added to repeats, so renaming a column
     * changes its key.
     *
     * @since 1.6.0
     *
     * @param  mixed  $raw  The setting.
     *
     * @return list<array{key: string, label: string, type: string, options: list<array{value: string, label: string}>}> The columns.
     */
    public static function parseColumns( mixed $raw ): array
    {
        $lines   = is_string( $raw ) ? preg_split( '/\R/', $raw ) : [];
        $columns = [];
        $keys    = [];

        foreach ( $lines ?: [] as $line ) {
            $parts = array_map( 'trim', explode( '|', $line, 3 ) );
            $label = mb_substr( $parts[0], 0, 255 );

            if ( '' === $label ) {
                continue;
            }

            $type    = strtolower( $parts[1] ?? '' );
            $type    = in_array( $type, self::COLUMN_TYPES, true ) ? $type : 'text';
            $options = [];

            if ( 'select' === $type ) {
                foreach ( explode( ',', $parts[2] ?? '' ) as $option ) {
                    $option = mb_substr( trim( $option ), 0, 255 );

                    if ( '' !== $option && ! isset( $options[ $option ] ) ) {
                        $options[ $option ] = ['value' => $option, 'label' => $option];
                    }
                }

                $type = [] === $options ? 'text' : 'select';
            }

            $key  = Str::slug( $label, '_' );
            $key  = '' === $key ? 'column_' . ( count( $columns ) + 1 ) : $key;
            $base = $key;

            for ( $suffix = 2; isset( $keys[ $key ] ); $suffix++ ) {
                $key = "{$base}_{$suffix}";
            }

            $keys[ $key ] = true;
            $columns[]    = ['key' => $key, 'label' => $label, 'type' => $type, 'options' => array_values( $options )];

            if ( count( $columns ) >= self::MAX_COLUMNS ) {
                break;
            }
        }

        return $columns;
    }

    /**
     * Gets the fewest filled rows a list takes once any row is filled in.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return int|null The count, or null when unset.
     */
    public static function minRows( FormField $field ): ?int
    {
        $min = self::rowLimit( $field, 'min_rows' );
        $max = self::maxRows( $field );

        return null !== $min && $min > $max ? $max : $min;
    }

    /**
     * Gets the most rows a list takes.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return int The count; MAX_ROWS when unset.
     */
    public static function maxRows( FormField $field ): int
    {
        return self::rowLimit( $field, 'max_rows' ) ?? self::MAX_ROWS;
    }

    /**
     * Gets the name of one row, used in the add and remove buttons ("Add Attendee", "Remove Attendee 2").
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return string The row label.
     */
    public static function rowLabel( FormField $field ): string
    {
        $label = $field->getConfig( 'row_label' );

        return is_string( $label ) && '' !== trim( $label ) ? mb_substr( trim( $label ), 0, 100 ) : __( 'Row' );
    }

    /**
     * Gets how a list is exported to CSV.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return string `lines` (one row per line in the cell) or `json`.
     */
    public static function exportFormat( FormField $field ): string
    {
        $format = $field->getConfig( 'export_format' );

        return is_string( $format ) && in_array( $format, self::EXPORT_FORMATS, true ) ? $format : 'lines';
    }

    /**
     * Gets the rows a list starts with: as many empty rows as its minimum, and at least one.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return list<array<string, string>> The rows.
     */
    public static function initialRows( FormField $field ): array
    {
        $row = array_fill_keys( array_column( self::columns( $field ), 'key' ), '' );

        return array_fill( 0, max( 1, self::minRows( $field ) ?? 1 ), $row );
    }

    /**
     * Keeps a list's filled rows, each with its columns as trimmed text.
     *
     * Rows with every cell blank are dropped. Without `$reindex` the other
     * rows keep their keys, so an error on a row names the row the visitor
     * sees.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     * @param  mixed  $value  The submitted rows.
     * @param  bool  $reindex  Whether to number the rows from 0.
     *
     * @return array<int, array<string, string>>|null The rows, or null when none is filled in.
     */
    public static function filledRows( FormField $field, mixed $value, bool $reindex = true ): ?array
    {
        if ( ! is_array( $value ) ) {
            return null;
        }

        $keys = array_column( self::columns( $field ), 'key' );
        $rows = [];

        foreach ( $value as $index => $row ) {
            if ( ! is_array( $row ) || ! is_int( $index ) ) {
                continue;
            }

            $cells = [];

            foreach ( $keys as $key ) {
                $cell          = $row[ $key ] ?? '';
                $cells[ $key ] = is_scalar( $cell ) && ! is_bool( $cell ) ? trim( (string) $cell ) : '';
            }

            if ( '' !== implode( '', $cells ) ) {
                $rows[ $index ] = $cells;
            }
        }

        if ( [] === $rows ) {
            return null;
        }

        return $reindex ? array_values( $rows ) : $rows;
    }

    /**
     * {@inheritDoc}
     *
     * The rows are a list of at most `max_rows` (and at least `min_rows`);
     * each row's cells get their column type's rules.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        $listRules = [$this->presenceRule( $field ), 'array', 'max:' . self::maxRows( $field )];

        if ( null !== ( $min = self::minRows( $field ) ) ) {
            $listRules[] = "min:{$min}";
        }

        $rules = ['' => $listRules, '*' => ['array']];

        foreach ( self::columns( $field ) as $column ) {
            $rules[ "*.{$column['key']}" ] = array_merge( ['nullable'], $this->cellRules( $column ) );
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
        $messages = [
            'array' => __( 'The :field field is invalid.', ['field' => $field->label] ),
            'max'   => trans_choice( ':field may have no more than :count row.|:field may have no more than :count rows.', self::maxRows( $field ), ['field' => $field->label, 'count' => self::maxRows( $field )] ),
        ];

        if ( $field->is_required ) {
            $messages['required'] = __( 'Please fill in at least one row of :field.', ['field' => $field->label] );
        }

        if ( null !== ( $min = self::minRows( $field ) ) ) {
            $messages['min'] = trans_choice( 'Please fill in at least :count row of :field.|Please fill in at least :count rows of :field.', $min, ['count' => $min, 'field' => $field->label] );
        }

        foreach ( self::columns( $field ) as $column ) {
            $replace = ['field' => $field->label, 'column' => $column['label']];
            $prefix  = "*.{$column['key']}";

            $messages[ "{$prefix}.string" ]  = __( ':field: :column must be text.', $replace );
            $messages[ "{$prefix}.max" ]     = __( ':field: :column may not be longer than :max characters.', $replace );
            $messages[ "{$prefix}.email" ]   = __( ':field: :column must be a valid email address.', $replace );
            $messages[ "{$prefix}.numeric" ] = __( ':field: :column must be a number.', $replace );
            $messages[ "{$prefix}.in" ]      = __( ':field: please choose a valid :column.', $replace );
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     *
     * Stores the filled rows, numbered from 0.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return self::filledRows( $field, $value ) ?? [];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * One row per line, its filled cells joined with commas; a select shows its option label.
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        return implode( "\n", array_map(
            fn ( array $cells ): string => implode( ', ', array_filter( $cells, fn ( string $cell ): bool => '' !== $cell ) ),
            $this->displayRows( $value ),
        ) );
    }

    /**
     * {@inheritDoc}
     *
     * A table with a header row of column labels; every cell is escaped.
     *
     * @since 1.6.0
     */
    public function displayHtml( FormSubmissionValue $value ): ?string
    {
        $rows = $this->displayRows( $value );

        if ( [] === $rows ) {
            return '';
        }

        $field   = $this->sourceField( $value );
        $columns = null === $field ? [] : self::columns( $field );
        $labels  = [] === $columns ? array_keys( $rows[0] ) : array_column( $columns, 'label' );
        $html    = '<table class="table table-sm"><thead><tr>';

        foreach ( $labels as $label ) {
            $html .= '<th scope="col">' . e( (string) $label ) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ( $rows as $cells ) {
            $html .= '<tr>';

            foreach ( $cells as $cell ) {
                $html .= '<td>' . e( $cell ) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * {@inheritDoc}
     *
     * One row per line, or the rows as JSON objects keyed by column label.
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        if ( null === $value || 'json' !== self::exportFormat( $field ) || $value->isRedacted() ) {
            return parent::exportValues( $field, $value );
        }

        $labels = array_column( self::columns( $field ), 'label', 'key' );
        $rows   = [];

        foreach ( is_array( $value->value_array ) ? $value->value_array : [] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $labelled = [];

            foreach ( $row as $key => $cell ) {
                $labelled[ $labels[ $key ] ?? (string) $key ] = is_scalar( $cell ) ? (string) $cell : '';
            }

            $rows[] = $labelled;
        }

        return [(string) json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )];
    }

    /**
     * Gets a stored value's rows as display text, with each select cell's option label.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return list<array<string, string>> The rows, each in column order.
     */
    protected function displayRows( FormSubmissionValue $value ): array
    {
        $field   = $this->sourceField( $value );
        $columns = null === $field ? [] : self::columns( $field );
        $rows    = [];

        foreach ( is_array( $value->value_array ) ? $value->value_array : [] as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            if ( [] === $columns ) {
                $rows[] = array_map( fn ( mixed $cell ): string => is_scalar( $cell ) ? (string) $cell : '', $row );

                continue;
            }

            $cells = [];

            foreach ( $columns as $column ) {
                $cell = $row[ $column['key'] ] ?? '';
                $cell = is_scalar( $cell ) ? (string) $cell : '';

                foreach ( $column['options'] as $option ) {
                    if ( $option['value'] === $cell ) {
                        $cell = $option['label'];

                        break;
                    }
                }

                $cells[ $column['key'] ] = $cell;
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['list'], [
            'label'    => __( 'List' ),
            'defaults' => [
                'label'        => __( 'Attendees' ),
                'field_config' => [
                    'columns'   => __( 'Name' ) . "\n" . __( 'Email' ) . ' | email',
                    'row_label' => __( 'Attendee' ),
                ],
            ],
            'settings' => [
                [
                    'key'         => 'columns',
                    'input'       => 'textarea',
                    'max_length'  => 2000,
                    'label'       => __( 'Columns' ),
                    'placeholder' => __( 'Name' ) . "\n" . __( 'Email' ) . " | email\n" . __( 'Role' ) . ' | select | ' . __( 'Speaker' ) . ', ' . __( 'Guest' ),
                    'hint'        => __( 'One column per line: Label | type | options. Types: text, email, number, select (with comma-separated options). Up to :max columns.', ['max' => self::MAX_COLUMNS] ),
                ],
                [
                    'key'         => 'row_label',
                    'max_length'  => 100,
                    'label'       => __( 'Row Label' ),
                    'placeholder' => __( 'Row' ),
                    'hint'        => __( 'Names one row in the add and remove buttons, such as Attendee for "Add Attendee" and "Remove Attendee 2".' ),
                ],
                ['key' => 'min_rows', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'max' => self::MAX_ROWS, 'label' => __( 'Min Rows' ), 'hint' => __( 'Applies once a row is filled in, or always for a required field.' )],
                ['key' => 'max_rows', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'max' => self::MAX_ROWS, 'label' => __( 'Max Rows' ), 'placeholder' => (string) self::MAX_ROWS],
                [
                    'key'     => 'export_format',
                    'input'   => 'select',
                    'tab'     => 'advanced',
                    'default' => 'lines',
                    'label'   => __( 'Export Format' ),
                    'options' => [
                        ['value' => 'lines', 'label' => __( 'One row per line' )],
                        ['value' => 'json', 'label' => __( 'JSON' )],
                    ],
                ],
            ],
        ] );
    }

    /**
     * Gets the rules for a column's cells.
     *
     * @since 1.6.0
     *
     * @param  array{type: string, options: list<array{value: string, label: string}>}  $column  The column.
     *
     * @return list<mixed> The rules.
     */
    protected function cellRules( array $column ): array
    {
        $maxLength = 'max:' . self::CELL_MAX_LENGTH;

        return match ( $column['type'] ) {
            'email'  => ['string', $maxLength, 'email'],
            'number' => ['numeric'],
            'select' => ['string', (string) Rule::in( array_column( $column['options'], 'value' ) )],
            default  => ['string', $maxLength],
        };
    }

    /**
     * Reads a row limit setting.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     * @param  string  $key  `min_rows` or `max_rows`.
     *
     * @return int|null The limit, capped at MAX_ROWS, or null when unset.
     */
    protected static function rowLimit( FormField $field, string $key ): ?int
    {
        $value = $field->getConfig( $key );

        return is_numeric( $value ) && (int) $value > 0 ? min( (int) $value, self::MAX_ROWS ) : null;
    }
}
