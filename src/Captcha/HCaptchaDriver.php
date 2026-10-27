<?php

/**
 * hCaptcha CAPTCHA driver.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

/**
 * hCaptcha, as a checkbox or invisible (checked when the form is submitted).
 *
 * @since 1.6.0
 */
class HCaptchaDriver extends SiteVerifyDriver
{
    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function key(): string
    {
        return 'hcaptcha';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function label(): string
    {
        return __( 'hCaptcha' );
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
        return 'https://api.hcaptcha.com/siteverify';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function scriptUrl(): string
    {
        return 'https://js.hcaptcha.com/1/api.js?render=explicit';
    }

    /**
     * {@inheritDoc}
     *
     * hCaptcha checks the token was issued for the site key.
     *
     * @since 1.6.0
     */
    protected function extraVerifyParameters(): array
    {
        return ['sitekey' => (string) $this->siteKey()];
    }
}
