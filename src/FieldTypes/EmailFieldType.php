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
use ArtisanPackUI\Forms\Support\EmailDomains;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * Email address input.
 *
 * With `confirm_email` on, the field shows a second "Confirm Email" input
 * bound to `{name}_confirmation` and Laravel's `confirmed` rule requires the
 * two to match. Only the email itself is stored, so display, notifications
 * and exports are unchanged (see FormField::requiresEmailConfirmation()).
 *
 * `email_validation` picks how strictly addresses are checked (RFC, RFC with
 * a DNS lookup, or strict), and the allowed and blocked domain lists accept
 * or reject addresses by domain, subdomains included (see EmailDomains).
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
                [
                    'key'     => 'email_validation',
                    'input'   => 'select',
                    'store'   => 'validation_rules',
                    'tab'     => 'validation',
                    'default' => 'rfc',
                    'label'   => __( 'Email Validation' ),
                    'hint'    => __( 'DNS also checks that the domain can receive email, which needs a network lookup on each submission.' ),
                    'options' => EmailDomains::validationOptions(),
                ],
                [
                    'key'         => 'allowed_domains',
                    'input'       => 'list',
                    'store'       => 'validation_rules',
                    'tab'         => 'validation',
                    'label'       => __( 'Allowed Domains' ),
                    'placeholder' => 'example.com, example.org',
                    'hint'        => __( 'Only addresses at these domains (or their subdomains) are accepted. Comma-separated.' ),
                    'monospace'   => true,
                ],
                [
                    'key'         => 'blocked_domains',
                    'input'       => 'list',
                    'store'       => 'validation_rules',
                    'tab'         => 'validation',
                    'label'       => __( 'Blocked Domains' ),
                    'placeholder' => 'gmail.com, yahoo.com, hotmail.com',
                    'hint'        => __( 'Addresses at these domains (or their subdomains) are rejected, such as free providers on a business form. Comma-separated.' ),
                    'monospace'   => true,
                ],
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
        $rules = [EmailDomains::VALIDATION_RULES[ $field->getEmailValidation() ]];

        if ( $field->requiresEmailConfirmation() ) {
            $rules[] = 'confirmed';
        }

        foreach ( ['allowed' => 'allow', 'blocked' => 'block'] as $list => $mode ) {
            if ( [] !== ( $domains = $field->getEmailDomains( $list ) ) ) {
                $rules[] = SubmissionRules::EMAIL_DOMAIN . ':' . $mode . ',' . implode( ',', $domains );
            }
        }

        return $rules;
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

        if ( [] !== $field->getEmailDomains( 'allowed' ) || [] !== $field->getEmailDomains( 'blocked' ) ) {
            $messages[ SubmissionRules::EMAIL_DOMAIN ] = __( 'Please use an email address from an accepted domain for :field.', ['field' => $field->label] );
        }

        return $messages;
    }
}
