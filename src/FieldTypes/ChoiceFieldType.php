<?php

/**
 * Base handler for single-choice field types.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Validation\Rule;

/**
 * A field whose value is one of its options' values.
 *
 * Shows the chosen option's label, falling back to the stored value when the
 * field or the option has since been deleted. A field that loads its choices
 * from a source (see ChoiceSources) only accepts one of the resolved values,
 * unless it offers an "Other" choice.
 *
 * @since 1.6.0
 */
abstract class ChoiceFieldType extends AbstractFieldType
{
    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        $rules = parent::rules( $field );

        if ( ! $this->limitsToSourceChoices( $field ) ) {
            return $rules;
        }

        $in = (string) Rule::in( $this->choiceValues( $field ) );

        if ( $field->isMultipleChoice() ) {
            $rules['*'] = [$in];
        } else {
            $rules[''][] = $in;
        }

        return $rules;
    }

    /**
     * Gets the options marked as defaults, or else the default value.
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        $defaults = $field->getDefaultOptionValues();

        if ( [] === $defaults ) {
            return parent::readOnlyValue( $field );
        }

        return $field->isMultipleChoice() ? $defaults : $defaults[0];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $raw = $value->value ?? '';

        if ( '' === $raw ) {
            return $raw;
        }

        return $this->sourceField( $value )?->getChoiceDisplayLabel( $raw ) ?? $raw;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $messages = parent::typeMessages( $field );

        if ( $this->limitsToSourceChoices( $field ) ) {
            $messages[ $field->isMultipleChoice() ? '*.in' : 'in' ] = __( 'Please choose one of the available options for :field.', ['field' => $field->label] );
        }

        return $messages;
    }

    /**
     * Checks whether submitted values must be one of the choices a source resolved.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True when the field loads its choices from a source and has no "Other" choice.
     */
    protected function limitsToSourceChoices( FormField $field ): bool
    {
        return null !== $field->getChoicesSource() && ! $field->allowsOtherChoice();
    }

    /**
     * Gets the values of a field's options.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<string> The values.
     */
    protected function choiceValues( FormField $field ): array
    {
        $values = [];

        foreach ( $field->options as $key => $option ) {
            $value = is_array( $option ) ? ( $option['value'] ?? null ) : ( is_string( $key ) ? $key : $option );

            if ( is_scalar( $value ) ) {
                $values[] = (string) $value;
            }
        }

        return $values;
    }
}
