<?php

/**
 * Ranking field type handler.
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
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Validation\Rule;

/**
 * Visitors put the field's choices in order of preference.
 *
 * The choices come from the options editor. The list starts in the options'
 * order (shuffled per visitor with **Randomize choice order**), and visitors
 * reorder it by dragging or with each choice's "Move up" / "Move down"
 * buttons. The value is every option value, most preferred first, stored in
 * `value_array`; it must hold each option exactly once. Submissions show the
 * choices numbered in order.
 *
 * @since 1.6.0
 */
class RankingFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'ranking';

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'ranking';

    /**
     * Gets a ranking field's choices in the order they start in.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<array{value: string, label: string}> The choices.
     */
    public static function choices( FormField $field ): array
    {
        $choices = [];

        foreach ( $field->getRenderOptions() as $key => $option ) {
            $value = is_array( $option ) ? ( $option['value'] ?? null ) : ( is_string( $key ) ? $key : $option );
            $label = is_array( $option ) ? ( $option['label'] ?? $value ) : $option;

            if ( is_scalar( $value ) && '' !== (string) $value && ! isset( $choices[ (string) $value ] ) ) {
                $choices[ (string) $value ] = ['value' => (string) $value, 'label' => is_scalar( $label ) ? (string) $label : (string) $value];
            }
        }

        return array_values( $choices );
    }

    /**
     * Gets the value a ranking starts with: every choice, in the order shown.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<string> The option values.
     */
    public static function initialValue( FormField $field ): array
    {
        return array_column( self::choices( $field ), 'value' );
    }

    /**
     * Reads a ranking's current order, fitted to the field's choices.
     *
     * Keeps the values that are choices, in their order and once each, then
     * adds any choice missing from the end, so the list always shows every
     * choice even after the options change.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  mixed  $value  The current order.
     *
     * @return list<string> The option values.
     */
    public static function order( FormField $field, mixed $value ): array
    {
        $choices = self::initialValue( $field );
        $order   = [];

        foreach ( is_array( $value ) ? $value : [] as $item ) {
            if ( is_scalar( $item ) && in_array( (string) $item, $choices, true ) && ! in_array( (string) $item, $order, true ) ) {
                $order[] = (string) $item;
            }
        }

        return array_merge( $order, array_values( array_diff( $choices, $order ) ) );
    }

    /**
     * Moves one choice of a ranking to a new position.
     *
     * @since 1.6.0
     *
     * @param  list<string>  $order  The current order.
     * @param  int  $from  The choice's position, from 0.
     * @param  int  $to  The position to move it to, from 0.
     *
     * @return list<string> The new order; unchanged when either position is out of range.
     */
    public static function move( array $order, int $from, int $to ): array
    {
        $order = array_values( $order );

        if ( $from === $to || ! array_key_exists( $from, $order ) || ! array_key_exists( $to, $order ) ) {
            return $order;
        }

        $moved = array_splice( $order, $from, 1 );

        array_splice( $order, $to, 0, $moved );

        return $order;
    }

    /**
     * {@inheritDoc}
     *
     * The value is a list of every option value, each once: as many items
     * as options, each an option (`in` checks every item of an array) and
     * none repeated.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        $values = array_column( self::choices( $field ), 'value' );

        // `in` checks every item of an array, so errors land on the field rather than one position
        return [
            '' => [$this->presenceRule( $field ), 'array', 'size:' . count( $values ), (string) Rule::in( $values ), SubmissionRules::DISTINCT],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        $message = __( 'Please put every choice of :field in order.', ['field' => $field->label] );

        $messages = [
            'array'                   => $message,
            'size'                    => $message,
            'in'                      => $message,
            SubmissionRules::DISTINCT => $message,
        ];

        if ( $field->is_required ) {
            $messages['required'] = __( 'Please rank the choices of :field.', ['field' => $field->label] );
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     *
     * Stores the option values in the submitted order.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_array( $value ) ? array_values( array_map( fn ( mixed $item ): string => is_scalar( $item ) ? (string) $item : '', $value ) ) : [];
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
     * The choices numbered in order: "1. Price, 2. Quality".
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        return implode( ', ', $this->numberedLabels( $value ) );
    }

    /**
     * {@inheritDoc}
     *
     * An ordered list, every label escaped.
     *
     * @since 1.6.0
     */
    public function displayHtml( FormSubmissionValue $value ): ?string
    {
        $labels = $this->rankedLabels( $value );

        if ( [] === $labels ) {
            return null;
        }

        return '<ol style="margin: 0; padding-left: 1.5em; list-style: decimal;">' . implode( '', array_map(
            fn ( string $label ): string => '<li>' . e( $label ) . '</li>',
            $labels,
        ) ) . '</ol>';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['ranking'], [
            'label'    => __( 'Ranking' ),
            'defaults' => [
                'label'        => __( 'Rank these in order of importance' ),
                'field_config' => [
                    'options' => [
                        ['value' => 'price', 'label' => __( 'Price' )],
                        ['value' => 'quality', 'label' => __( 'Quality' )],
                        ['value' => 'service', 'label' => __( 'Service' )],
                    ],
                ],
            ],
            'settings' => [
                $this->randomizeSetting(),
            ],
        ] );
    }

    /**
     * Gets a stored ranking's choice labels, most preferred first.
     *
     * Choices since removed from the field show their stored value.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return list<string> The labels.
     */
    protected function rankedLabels( FormSubmissionValue $value ): array
    {
        if ( $value->isRedacted() || ! is_array( $value->value_array ) ) {
            return [];
        }

        $field  = $this->sourceField( $value );
        $labels = null === $field ? [] : array_column( self::choices( $field ), 'label', 'value' );

        return array_values( array_map(
            fn ( mixed $item ): string => $labels[ (string) $item ] ?? (string) $item,
            array_filter( $value->value_array, 'is_scalar' ),
        ) );
    }

    /**
     * Gets a stored ranking's labels with their positions: "1. Price".
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return list<string> The numbered labels.
     */
    protected function numberedLabels( FormSubmissionValue $value ): array
    {
        $labels = $this->rankedLabels( $value );

        return array_map(
            fn ( string $label, int $index ): string => __( ':position. :choice', ['position' => $index + 1, 'choice' => $label] ),
            $labels,
            array_keys( $labels ),
        );
    }
}
