<?php

/**
 * CAPTCHA verification result.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

/**
 * The outcome of checking a CAPTCHA token with its provider.
 *
 * @since 1.6.0
 */
final class CaptchaResult
{
    /**
     * Creates a result.
     *
     * @since 1.6.0
     *
     * @param  bool  $passed  Whether the token was accepted.
     * @param  list<string>  $errorCodes  The provider's error codes, or `request-failed` when it could not be reached.
     * @param  float|null  $score  A score-based provider's score.
     */
    public function __construct(
        public readonly bool $passed,
        public readonly array $errorCodes = [],
        public readonly ?float $score = null,
    ) {
    }

    /**
     * Creates a passing result.
     *
     * @since 1.6.0
     *
     * @param  float|null  $score  A score-based provider's score.
     *
     * @return self The result.
     */
    public static function pass( ?float $score = null ): self
    {
        return new self( true, [], $score );
    }

    /**
     * Creates a failing result.
     *
     * @since 1.6.0
     *
     * @param  list<string>  $errorCodes  The reasons.
     * @param  float|null  $score  A score-based provider's score.
     *
     * @return self The result.
     */
    public static function fail( array $errorCodes, ?float $score = null ): self
    {
        return new self( false, $errorCodes, $score );
    }
}
