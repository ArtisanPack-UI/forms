<?php

/**
 * Base CAPTCHA driver for providers with a `siteverify` endpoint.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

use ArtisanPackUI\Forms\Contracts\CaptchaDriver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turnstile, hCaptcha and reCAPTCHA all check a token by posting it, the
 * secret key and the visitor's IP address to a `siteverify` URL that
 * answers with `success` and `error-codes`. A subclass names its endpoint,
 * script and config key, and may check more of the answer in evaluate().
 *
 * A provider that cannot be reached, or answers with something other than
 * JSON, fails the check (`request-failed`): the form is not submitted
 * without a verified challenge.
 *
 * @since 1.6.0
 */
abstract class SiteVerifyDriver implements CaptchaDriver
{
    /**
     * Creates a driver.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>|null  $config  The driver's settings; read from
     *                                             `spam_protection.captcha.drivers.{key}` when null.
     */
    public function __construct( protected ?array $config = null )
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function isConfigured(): bool
    {
        return null !== $this->siteKey() && null !== $this->secretKey();
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function sizes(): array
    {
        return ['normal', 'compact'];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function widget( string $size, string $theme ): array
    {
        return [
            'provider' => $this->key(),
            'site_key' => $this->siteKey(),
            'script'   => $this->scriptUrl(),
            'size'     => in_array( $size, $this->sizes(), true ) ? $size : $this->sizes()[0],
            'theme'    => in_array( $theme, ['light', 'dark'], true ) ? $theme : 'auto',
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function verify( string $token, ?string $ipAddress = null ): CaptchaResult
    {
        if ( ! $this->isConfigured() ) {
            return CaptchaResult::fail( ['missing-input-secret'] );
        }

        if ( '' === trim( $token ) ) {
            return CaptchaResult::fail( ['missing-input-response'] );
        }

        try {
            $response = Http::asForm()
                ->timeout( $this->timeout() )
                ->post( $this->verifyUrl(), array_filter( [
                    'secret'   => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ] + $this->extraVerifyParameters() ) );
        } catch ( Throwable $exception ) {
            Log::warning( '[Forms] CAPTCHA verification request failed', [
                'provider' => $this->key(),
                'error'    => $exception->getMessage(),
            ] );

            return CaptchaResult::fail( ['request-failed'] );
        }

        $body = $response->json();

        if ( ! $response->successful() || ! is_array( $body ) ) {
            return CaptchaResult::fail( ['request-failed'] );
        }

        return $this->evaluate( $body, $response );
    }

    /**
     * Gets the URL tokens are checked at.
     *
     * @since 1.6.0
     *
     * @return string The URL.
     */
    abstract protected function verifyUrl(): string;

    /**
     * Gets the URL of the provider's widget script.
     *
     * @since 1.6.0
     *
     * @return string The URL.
     */
    abstract protected function scriptUrl(): string;

    /**
     * Reads the provider's answer.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $body  The decoded answer.
     * @param  Response  $response  The response.
     *
     * @return CaptchaResult The result.
     */
    protected function evaluate( array $body, Response $response ): CaptchaResult
    {
        if ( true === ( $body['success'] ?? false ) ) {
            return CaptchaResult::pass();
        }

        return CaptchaResult::fail( $this->errorCodes( $body ) );
    }

    /**
     * Gets more fields to post with a token.
     *
     * @since 1.6.0
     *
     * @return array<string, string> The fields.
     */
    protected function extraVerifyParameters(): array
    {
        return [];
    }

    /**
     * Gets the error codes from a provider's answer.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $body  The decoded answer.
     *
     * @return list<string> The error codes, or `invalid-input-response` when none were given.
     */
    protected function errorCodes( array $body ): array
    {
        $codes = array_values( array_filter( (array) ( $body['error-codes'] ?? [] ), 'is_string' ) );

        return [] === $codes ? ['invalid-input-response'] : $codes;
    }

    /**
     * Reads one of the driver's settings.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The setting.
     * @param  mixed  $default  The value when it is not set.
     *
     * @return mixed The value.
     */
    protected function setting( string $key, mixed $default = null ): mixed
    {
        $config = $this->config ?? config( 'artisanpack.forms.spam_protection.captcha.drivers.' . $this->key(), [] );

        return is_array( $config ) && array_key_exists( $key, $config ) && null !== $config[ $key ] ? $config[ $key ] : $default;
    }

    /**
     * Gets the public site key.
     *
     * @since 1.6.0
     *
     * @return string|null The key, or null when it is not set.
     */
    protected function siteKey(): ?string
    {
        $key = $this->setting( 'site_key' );

        return is_string( $key ) && '' !== trim( $key ) ? trim( $key ) : null;
    }

    /**
     * Gets the secret key.
     *
     * @since 1.6.0
     *
     * @return string|null The key, or null when it is not set.
     */
    protected function secretKey(): ?string
    {
        $key = $this->setting( 'secret_key' );

        return is_string( $key ) && '' !== trim( $key ) ? trim( $key ) : null;
    }

    /**
     * Gets how long to wait for the provider, in seconds.
     *
     * @since 1.6.0
     *
     * @return int The timeout.
     */
    protected function timeout(): int
    {
        $timeout = config( 'artisanpack.forms.spam_protection.captcha.timeout', 5 );

        return is_numeric( $timeout ) && (int) $timeout > 0 ? (int) $timeout : 5;
    }
}
