<?php

/**
 * Google reCAPTCHA v2 CAPTCHA driver.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

/**
 * Google reCAPTCHA v2, as the "I'm not a robot" checkbox or invisible
 * (checked when the form is submitted). Use a site key made for the chosen
 * kind: checkbox and invisible keys are not interchangeable.
 *
 * @since 1.6.0
 */
class RecaptchaV2Driver extends SiteVerifyDriver
{
    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function key(): string
    {
        return 'recaptcha_v2';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function label(): string
    {
        return __( 'Google reCAPTCHA v2' );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function sizes(): array
    {
        return ['normal', 'compact', 'invisible'];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function verifyUrl(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function scriptUrl(): string
    {
        return 'https://www.google.com/recaptcha/api.js?render=explicit';
    }
}
