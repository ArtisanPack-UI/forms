<?php

/**
 * Time field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Time picker.
 *
 * @since 1.6.0
 */
class TimeFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'time';

    /**
     * Stores `H:i:s` values with a zero seconds part as `H:i`.
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
        return is_string( $value ) ? FormField::normalizeTimeValue( $value ) : $value;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['time'], [
            'label'    => __( 'Time Picker' ),
            'defaults' => [
                'label'        => __( 'Select Time' ),
                'field_config' => [
                    'step' => 60,
                ],
            ],
            'settings' => [
                ['key' => 'min_time', 'input' => 'time', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Min Time' )],
                ['key' => 'max_time', 'input' => 'time', 'store' => 'validation_rules', 'tab' => 'validation', 'label' => __( 'Max Time' )],
                [
                    'key'         => 'step',
                    'input'       => 'integer',
                    'tab'         => 'validation',
                    'min'         => 1,
                    'default'     => 60,
                    'label'       => __( 'Step (seconds)' ),
                    'placeholder' => '60',
                    'hint'        => __( 'e.g., 900 for 15-minute intervals; under 60 lets users pick seconds' ),
                ],
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
        return ['regex:' . FormField::TIME_FORMAT_PATTERN];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return ['regex' => __( 'Please enter a valid time for :field.', ['field' => $field->label] )];
    }
}
