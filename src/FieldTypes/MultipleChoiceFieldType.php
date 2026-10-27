<?php

/**
 * Base handler for multiple-choice field types.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * A field whose value is a list of its options' values.
 *
 * @since 1.6.0
 */
abstract class MultipleChoiceFieldType extends ChoiceFieldType
{
    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['array'];
    }

    /**
     * {@inheritDoc}
     *
     * "Min Selections" is checked only when something is selected, so an
     * optional field can be left empty (`min` fails on `[]`).
     *
     * @since 1.6.0
     */
    protected function limitRules( FormField $field ): array
    {
        $min = $this->validationLimit( $field, 'min' );

        return array_values( array_map(
            fn ( string $rule ): string => null !== $min && "min:{$min}" === $rule ? SubmissionRules::MIN_SELECTIONS . ":{$min}" : $rule,
            parent::limitRules( $field ),
        ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $messages = parent::typeMessages( $field );
        $min      = $this->validationLimit( $field, 'min' );

        if ( null !== $min ) {
            $messages[ SubmissionRules::MIN_SELECTIONS ] = trans_choice(
                'Please choose at least :min option for :field.|Please choose at least :min options for :field.',
                (int) $min,
                ['min' => $min, 'field' => $field->label],
            );
        }

        return $messages;
    }

    /**
     * Gets the minimum and maximum selection settings.
     *
     * @since 1.6.0
     *
     * @return list<array<string, mixed>> The settings.
     */
    protected function selectionSettings(): array
    {
        return [
            ['key' => 'min', 'input' => 'integer', 'store' => 'validation_rules', 'tab' => 'validation', 'min' => 0, 'label' => __( 'Min Selections' )],
            ['key' => 'max', 'input' => 'integer', 'store' => 'validation_rules', 'tab' => 'validation', 'min' => 0, 'label' => __( 'Max Selections' )],
        ];
    }
}
