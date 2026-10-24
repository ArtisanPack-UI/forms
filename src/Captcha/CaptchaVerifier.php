<?php

/**
 * CAPTCHA field verification.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Captcha;

use ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Support\Facades\Log;

/**
 * Checks the tokens of a submission's CAPTCHA fields with their providers.
 *
 * Runs once per submission, after every field has been validated and just
 * before the submission is saved, since providers accept a token only once:
 * checking it while moving between steps would spend it. The Livewire
 * renderer and the submit API both call verify().
 *
 * @since 1.6.0
 */
class CaptchaVerifier
{
    /**
     * Creates a verifier.
     *
     * @since 1.6.0
     *
     * @param  CaptchaManager  $manager  The driver registry.
     */
    public function __construct( protected CaptchaManager $manager )
    {
    }

    /**
     * Checks the token submitted for each CAPTCHA field.
     *
     * Fields hidden by conditional logic, administrative fields and fields
     * whose provider has no keys are skipped.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form fields.
     * @param  array<string, mixed>  $formData  The submitted values keyed by field name.
     * @param  array<string, bool>  $hiddenFields  Fields hidden by conditional logic.
     * @param  string|null  $ipAddress  The visitor's IP address.
     *
     * @return array<string, string> An error message per field that failed, keyed by field name.
     */
    public function verify( iterable $fields, array $formData, array $hiddenFields = [], ?string $ipAddress = null ): array
    {
        $errors = [];

        foreach ( $fields as $field ) {
            if ( CaptchaFieldType::TYPE !== $field->type || ( $hiddenFields[ $field->name ] ?? false ) || $field->isAdministrative() ) {
                continue;
            }

            $driver = CaptchaFieldType::driver( $field );

            if ( null === $driver || ! $driver->isConfigured() ) {
                continue;
            }

            $token  = $formData[ $field->name ] ?? '';
            $result = $driver->verify( is_string( $token ) ? $token : '', $ipAddress );

            if ( ! $result->passed ) {
                if ( config( 'artisanpack.forms.security.logging_enabled', true ) ) {
                    Log::warning( '[Forms Security] captcha_failed', [
                        'event_type'  => 'captcha_failed',
                        'form_id'     => $field->form_id,
                        'field'       => $field->name,
                        'provider'    => $driver->key(),
                        'error_codes' => $result->errorCodes,
                        'score'       => $result->score,
                        'ip_address'  => $ipAddress,
                    ] );
                }

                $errors[ $field->name ] = in_array( 'request-failed', $result->errorCodes, true )
                    ? __( 'The verification check could not be completed. Please try again.' )
                    : __( 'The verification check failed. Please try again.' );
            }
        }

        return $errors;
    }
}
