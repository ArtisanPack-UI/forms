<?php

/**
 * Google reCAPTCHA v3 CAPTCHA driver.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

use Illuminate\Http\Client\Response;

/**
 * Google reCAPTCHA v3, which shows no challenge and scores each submission
 * from 0.0 (likely a bot) to 1.0 (likely a person). A token passes when its
 * action matches and its score reaches `score_threshold` (default 0.5).
 *
 * @since 1.6.0
 */
class RecaptchaV3Driver extends SiteVerifyDriver
{
    /**
     * The score a token passes with when no threshold is configured.
     *
     * @since 1.6.0
     *
     * @var float
     */
    public const DEFAULT_THRESHOLD = 0.5;

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function key(): string
    {
        return 'recaptcha_v3';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function label(): string
    {
        return __( 'Google reCAPTCHA v3 (score)' );
    }

    /**
     * {@inheritDoc}
     *
     * There is no widget: the token is fetched when the form is submitted.
     *
     * @since 1.6.0
     */
    public function sizes(): array
    {
        return ['invisible'];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function widget( string $size, string $theme ): array
    {
        return array_merge( parent::widget( $size, $theme ), [
            'script' => 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( (string) $this->siteKey() ),
            'action' => $this->action(),
        ] );
    }

    /**
     * Gets the action tokens are requested and checked for.
     *
     * @since 1.6.0
     *
     * @return string The action; letters, digits, `_` and `/` only.
     */
    public function action(): string
    {
        $action = preg_replace( '/[^A-Za-z0-9_\/]/', '', (string) $this->setting( 'action', 'forms_submit' ) );

        return is_string( $action ) && '' !== $action ? $action : 'forms_submit';
    }

    /**
     * Gets the lowest score that passes.
     *
     * @since 1.6.0
     *
     * @return float The threshold, between 0 and 1.
     */
    public function threshold(): float
    {
        $threshold = $this->setting( 'score_threshold', self::DEFAULT_THRESHOLD );

        return is_numeric( $threshold ) ? max( 0.0, min( 1.0, (float) $threshold ) ) : self::DEFAULT_THRESHOLD;
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
        return 'https://www.google.com/recaptcha/api.js';
    }

    /**
     * {@inheritDoc}
     *
     * Also checks the action and the score.
     *
     * @since 1.6.0
     */
    protected function evaluate( array $body, Response $response ): CaptchaResult
    {
        $score = is_numeric( $body['score'] ?? null ) ? (float) $body['score'] : null;

        if ( true !== ( $body['success'] ?? false ) ) {
            return CaptchaResult::fail( $this->errorCodes( $body ), $score );
        }

        if ( ( $body['action'] ?? null ) !== $this->action() ) {
            return CaptchaResult::fail( ['action-mismatch'], $score );
        }

        if ( null === $score || $score < $this->threshold() ) {
            return CaptchaResult::fail( ['score-too-low'], $score );
        }

        return CaptchaResult::pass( $score );
    }
}
