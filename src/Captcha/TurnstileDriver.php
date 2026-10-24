<?php

/**
 * Cloudflare Turnstile CAPTCHA driver.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

/**
 * Cloudflare Turnstile. Usually shows no puzzle, so it is the most
 * accessible of the built-in providers.
 *
 * @since 1.6.0
 */
class TurnstileDriver extends SiteVerifyDriver
{
    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function key(): string
    {
        return 'turnstile';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function label(): string
    {
        return __( 'Cloudflare Turnstile' );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function verifyUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function scriptUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    }
}
