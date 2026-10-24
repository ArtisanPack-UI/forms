<?php

/**
 * CAPTCHA driver contract.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Contracts;

use ArtisanPackUI\Forms\Captcha\CaptchaResult;

/**
 * A CAPTCHA provider: what the renderers need to show its challenge, and the
 * server-side check of the token it hands back.
 *
 * Built-in drivers are Cloudflare Turnstile, hCaptcha, and Google reCAPTCHA
 * v2 and v3, configured under `spam_protection.captcha` in
 * `config/forms.php`. Register another through the `ap.forms.captchaDrivers`
 * filter, keyed by name, as a class name or an instance.
 *
 * @since 1.6.0
 */
interface CaptchaDriver
{
    /**
     * Gets the driver's name, as config and field settings refer to it.
     *
     * @since 1.6.0
     *
     * @return string The name, such as `turnstile`.
     */
    public function key(): string;

    /**
     * Gets the provider's name, for the builder.
     *
     * @since 1.6.0
     *
     * @return string The translated name.
     */
    public function label(): string;

    /**
     * Checks whether the driver has the keys it needs.
     *
     * @since 1.6.0
     *
     * @return bool True when the site and secret keys are set.
     */
    public function isConfigured(): bool;

    /**
     * Gets the widget sizes the provider offers.
     *
     * @since 1.6.0
     *
     * @return list<string> Some of `normal`, `compact` and `invisible`; `invisible` runs the check on submit.
     */
    public function sizes(): array;

    /**
     * Gets what a renderer needs to show the challenge.
     *
     * Holds only public values: the `provider` (the driver key), the
     * `site_key`, the `script` URL, the `size` and `theme`, and for a
     * score-based provider the `action`. Never the secret key.
     *
     * @since 1.6.0
     *
     * @param  string  $size  The field's size setting.
     * @param  string  $theme  The field's theme setting: `auto`, `light` or `dark`.
     *
     * @return array<string, string|null> The widget settings.
     */
    public function widget( string $size, string $theme ): array;

    /**
     * Checks a token with the provider.
     *
     * @since 1.6.0
     *
     * @param  string  $token  The token the widget handed back.
     * @param  string|null  $ipAddress  The visitor's IP address, when known.
     *
     * @return CaptchaResult The result.
     */
    public function verify( string $token, ?string $ipAddress = null ): CaptchaResult;
}
