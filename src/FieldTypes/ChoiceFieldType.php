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

use ArtisanPackUI\Forms\Models\FormSubmissionValue;

/**
 * A field whose value is one of its options' values.
 *
 * Shows the chosen option's label, falling back to the stored value when the
 * field or the option has since been deleted.
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
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $raw = $value->value ?? '';

        if ( '' === $raw ) {
            return $raw;
        }

        return $this->sourceField( $value )?->getChoiceDisplayLabel( $raw ) ?? $raw;
    }
}
