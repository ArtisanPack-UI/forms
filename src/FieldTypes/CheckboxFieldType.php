<?php

/**
 * Checkbox field type handler.
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
 * Single checkbox, such as a consent box.
 *
 * @since 1.6.0
 */
class CheckboxFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'checkbox';

    /**
     * Reads the default value as checked or unchecked.
     *
     * The default is free text, and the `boolean` rule rejects words such as
     * "true", "yes" and "on", which would leave a read-only checkbox that can
     * never be submitted.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool Whether the checkbox is checked.
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return filter_var( $field->default_value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['checkbox'], [
            'label'    => __( 'Single Checkbox' ),
            'defaults' => [
                'label' => __( 'I agree to the terms' ),
            ],
            'settings' => [
                ['key' => 'checked_label', 'max_length' => 255, 'label' => __( 'Checked Text' ), 'placeholder' => __( 'Yes' ), 'hint' => __( 'Shown in submissions, notifications and exports.' )],
                ['key' => 'unchecked_label', 'max_length' => 255, 'label' => __( 'Unchecked Text' ), 'placeholder' => __( 'No' )],
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * Gets the presence rule.
     *
     * `required` treats `false` as present, so a consent checkbox that was
     * checked and then unchecked would pass. `accepted` demands it be on.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string `accepted` or `nullable`.
     */
    protected function presenceRule( FormField $field ): string
    {
        return $field->is_required ? 'accepted' : 'nullable';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['boolean'];
    }

    /**
     * Shows the field's checked or unchecked text, or Yes / No.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return string The display value.
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $checked = filter_var( $value->value, FILTER_VALIDATE_BOOLEAN );
        $field   = $this->sourceField( $value );

        if ( null !== $field ) {
            return $field->getCheckedDisplayText( $checked );
        }

        return $checked ? __( 'Yes' ) : __( 'No' );
    }
}
