<?php

/**
 * Email field type handler.
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
 * Email address input.
 *
 * With `confirm_email` on, the field shows a second "Confirm Email" input
 * bound to `{name}_confirmation` and Laravel's `confirmed` rule requires the
 * two to match. Only the email itself is stored, so display, notifications
 * and exports are unchanged (see FormField::requiresEmailConfirmation()).
 *
 * @since 1.6.0
 */
class EmailFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'email';

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['email'], [
            'label'    => __( 'Email' ),
            'defaults' => [
                'label'       => __( 'Email Address' ),
                'placeholder' => __( 'you@example.com' ),
            ],
            'settings' => [
                [
                    'key'   => 'confirm_email',
                    'input' => 'toggle',
                    'label' => __( 'Enable email confirmation' ),
                    'hint'  => __( 'Asks for the email twice and checks that both match.' ),
                ],
                ['key' => 'email_sub_label', 'max_length' => 255, 'label' => __( 'Email Sub-Label' ), 'placeholder' => __( 'Enter Email' )],
                ['key' => 'confirm_sub_label', 'max_length' => 255, 'label' => __( 'Confirm Sub-Label' ), 'placeholder' => __( 'Confirm Email' )],
                ['key' => 'confirm_placeholder', 'max_length' => 255, 'label' => __( 'Confirm Placeholder' )],
                ...$this->lengthSettings(),
                $this->noDuplicatesSetting(),
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
        return $field->requiresEmailConfirmation() ? ['email', 'confirmed'] : ['email'];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $messages = ['email' => __( 'Please enter a valid email address for :field.', ['field' => $field->label] )];

        if ( $field->requiresEmailConfirmation() ) {
            $messages['confirmed'] = __( 'The email addresses entered for :field do not match.', ['field' => $field->label] );
        }

        return $messages;
    }
}
