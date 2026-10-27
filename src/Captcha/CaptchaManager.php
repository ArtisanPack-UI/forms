<?php

/**
 * CAPTCHA driver registry.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

use ArtisanPackUI\Forms\Contracts\CaptchaDriver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves CAPTCHA drivers by name.
 *
 * The built-in drivers are passed through the `ap.forms.captchaDrivers`
 * filter, keyed by name, as class names; a filter may add or replace one
 * with a class name or a CaptchaDriver instance. The default driver is
 * `spam_protection.captcha.driver`.
 *
 * @since 1.6.0
 */
class CaptchaManager
{
    /**
     * The built-in drivers.
     *
     * @since 1.6.0
     *
     * @var array<string, class-string<CaptchaDriver>>
     */
    public const DRIVERS = [
        'turnstile'    => TurnstileDriver::class,
        'hcaptcha'     => HCaptchaDriver::class,
        'recaptcha_v2' => RecaptchaV2Driver::class,
        'recaptcha_v3' => RecaptchaV3Driver::class,
    ];

    /**
     * Gets every registered driver.
     *
     * Entries that are not a CaptchaDriver class or instance are skipped and logged.
     *
     * @since 1.6.0
     *
     * @return array<string, CaptchaDriver> The drivers by name.
     */
    public function drivers(): array
    {
        $registered = applyFilters( 'ap.forms.captchaDrivers', self::DRIVERS );
        $drivers    = [];

        foreach ( is_array( $registered ) ? $registered : self::DRIVERS as $key => $entry ) {
            $driver = $this->resolve( $entry );

            if ( null === $driver ) {
                Log::warning( 'ArtisanPackUI Forms: invalid CAPTCHA driver', ['driver' => $key] );

                continue;
            }

            $drivers[ (string) $key ] = $driver;
        }

        return $drivers;
    }

    /**
     * Gets a driver by name, or the default driver.
     *
     * @since 1.6.0
     *
     * @param  string|null  $key  The driver name; null or blank for the default.
     *
     * @return CaptchaDriver|null The driver, or null when none is registered under the name.
     */
    public function driver( ?string $key = null ): ?CaptchaDriver
    {
        $key = null === $key || '' === trim( $key ) ? $this->defaultDriver() : trim( $key );

        return $this->drivers()[ $key ] ?? null;
    }

    /**
     * Gets the default driver's name.
     *
     * @since 1.6.0
     *
     * @return string The name.
     */
    public function defaultDriver(): string
    {
        $driver = config( 'artisanpack.forms.spam_protection.captcha.driver', 'turnstile' );

        return is_string( $driver ) && '' !== trim( $driver ) ? trim( $driver ) : 'turnstile';
    }

    /**
     * Gets the drivers as builder select options.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public function options(): array
    {
        $options = [];

        foreach ( $this->drivers() as $key => $driver ) {
            $options[] = ['value' => $key, 'label' => $driver->label()];
        }

        return $options;
    }

    /**
     * Resolves a registry entry to a driver.
     *
     * @since 1.6.0
     *
     * @param  mixed  $entry  A driver class name or instance.
     *
     * @return CaptchaDriver|null The driver.
     */
    protected function resolve( mixed $entry ): ?CaptchaDriver
    {
        if ( $entry instanceof CaptchaDriver ) {
            return $entry;
        }

        if ( ! is_string( $entry ) || ! is_a( $entry, CaptchaDriver::class, true ) ) {
            return null;
        }

        try {
            return app( $entry );
        } catch ( Throwable ) {
            return null;
        }
    }
}
