<?php

/**
 * CAPTCHA field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Captcha\CaptchaManager;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Contracts\CaptchaDriver;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

/**
 * A CAPTCHA challenge the visitor completes before submitting, placed where
 * the admin wants it (for example on the last step of a multi-step form).
 *
 * The field uses its `captcha_provider`, or the default driver
 * (`spam_protection.captcha.driver`). Its value is the provider's token,
 * which must be present; the token itself is checked with the provider by
 * CaptchaVerifier when the form is submitted. The token is never stored,
 * exported or offered as a merge tag. A provider without keys renders
 * nothing and checks nothing, so a form keeps working before the keys are
 * set; the honeypot and timing checks still run.
 *
 * @since 1.6.0
 */
class CaptchaFieldType extends AbstractFieldType
{
    /**
     * The type name.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'captcha';

    /**
     * The longest token accepted.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const TOKEN_MAX_LENGTH = 4096;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = self::TYPE;

    /**
     * Gets the driver a field uses.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The CAPTCHA field.
     *
     * @return CaptchaDriver|null The driver, or null when its provider is not registered.
     */
    public static function driver( FormField $field ): ?CaptchaDriver
    {
        $provider = $field->getConfig( 'captcha_provider' );

        return app( CaptchaManager::class )->driver( is_string( $provider ) ? $provider : null );
    }

    /**
     * Gets what a renderer needs to show a field's challenge.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The CAPTCHA field.
     *
     * @return array<string, string|null>|null The widget settings (see CaptchaDriver::widget()), or null when
     *                                         the provider is missing or has no keys.
     */
    public static function widget( FormField $field ): ?array
    {
        $driver = self::driver( $field );

        if ( null === $driver || ! $driver->isConfigured() ) {
            return null;
        }

        $size  = $field->getConfig( 'captcha_size' );
        $theme = $field->getConfig( 'captcha_theme' );

        return $driver->widget( is_string( $size ) ? $size : 'normal', is_string( $theme ) ? $theme : 'auto' );
    }

    /**
     * {@inheritDoc}
     *
     * A token is always required when the provider is set up, whatever the
     * field's required flag.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        if ( null === self::widget( $field ) ) {
            return [];
        }

        return ['' => ['required', 'string', 'max:' . self::TOKEN_MAX_LENGTH]];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        return [
            'required' => __( 'Please complete the verification check.' ),
            'string'   => __( 'The verification check failed. Please try again.' ),
            'max'      => __( 'The verification check failed. Please try again.' ),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function exportColumns( FormField $field ): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function mergeTags( FormField $field ): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['captcha'], [
            'label'    => __( 'CAPTCHA' ),
            'defaults' => [
                'label' => __( 'Verification' ),
            ],
            'settings' => [
                [
                    'key'          => 'captcha_provider',
                    'input'        => 'select',
                    'label'        => __( 'Provider' ),
                    'hint'         => __( 'Keys are set under spam_protection.captcha in config/forms.php. Turnstile and invisible modes are the most accessible.' ),
                    'options_from' => 'captcha_drivers',
                    'options'      => [
                        ['value' => '', 'label' => __( 'Default provider' )],
                    ],
                ],
                [
                    'key'     => 'captcha_size',
                    'input'   => 'select',
                    'default' => 'normal',
                    'label'   => __( 'Widget Size' ),
                    'hint'    => __( 'Invisible checks the visitor when the form is submitted (hCaptcha and reCAPTCHA v2 only). reCAPTCHA v3 is always invisible.' ),
                    'options' => [
                        ['value' => 'normal', 'label' => __( 'Normal' )],
                        ['value' => 'compact', 'label' => __( 'Compact' )],
                        ['value' => 'invisible', 'label' => __( 'Invisible' )],
                    ],
                ],
                [
                    'key'     => 'captcha_theme',
                    'input'   => 'select',
                    'default' => 'auto',
                    'label'   => __( 'Theme' ),
                    'options' => [
                        ['value' => 'auto', 'label' => __( 'Automatic' )],
                        ['value' => 'light', 'label' => __( 'Light' )],
                        ['value' => 'dark', 'label' => __( 'Dark' )],
                    ],
                ],
            ],
        ] );
    }
}
