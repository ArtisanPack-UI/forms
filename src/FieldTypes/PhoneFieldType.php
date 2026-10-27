<?php

/**
 * Phone field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Support\PredefinedChoices;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Telephone number input.
 *
 * The `phone_format` setting decides how numbers are checked:
 *
 * - `any` (default): any text, with the optional pattern and input mask.
 * - `us`: a valid US number, checked with `propaganistas/laravel-phone`.
 * - `international`: a valid number in any country. The renderers show a
 *   country selector that starts at `default_country`; a number typed
 *   without a `+` country code is read in the selected country. The
 *   Livewire renderer submits the country as `{name}_country`, which the
 *   `phone` rule reads; React and Vue prefix the number with the country's
 *   calling code instead.
 *
 * With `us` or `international`, numbers are stored in E.164 form
 * (`+15555550123`) so notifications and webhooks get one consistent value,
 * and shown formatted (national for US numbers, international otherwise).
 *
 * @since 1.6.0
 */
class PhoneFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'phone';

    /**
     * Gets the countries the country selector offers, with their calling codes.
     *
     * The `ap.forms.countries` list, leaving out regions libphonenumber has
     * no calling code for.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string, dial_code: string}> The countries.
     */
    public static function countries(): array
    {
        $util      = PhoneNumberUtil::getInstance();
        $countries = [];

        foreach ( PredefinedChoices::countries() as $code => $name ) {
            $dialCode = $util->getCountryCodeForRegion( strtoupper( (string) $code ) );

            if ( $dialCode > 0 ) {
                $countries[] = [
                    'value'     => strtoupper( (string) $code ),
                    'label'     => "{$name} (+{$dialCode})",
                    'dial_code' => (string) $dialCode,
                ];
            }
        }

        return $countries;
    }

    /**
     * Converts a submitted number to E.164 form, when the field checks numbers.
     *
     * A number without a `+` country code is read in the given country, or
     * the field's (US for the `us` format). Values that are blank or do not
     * parse are returned unchanged.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The phone field.
     * @param  mixed  $value  The submitted number.
     * @param  string|null  $country  The country the visitor selected, if any.
     *
     * @return mixed The E.164 number, or the value unchanged.
     */
    public static function toE164( FormField $field, mixed $value, ?string $country = null ): mixed
    {
        $format = $field->getPhoneFormat();

        if ( 'any' === $format || ! is_string( $value ) || '' === trim( $value ) ) {
            return $value;
        }

        $countries = 'us' === $format
            ? ['US']
            : array_values( array_filter( [is_string( $country ) ? strtoupper( $country ) : null, $field->getPhoneDefaultCountry()] ) );

        try {
            $phone = new PhoneNumber( trim( $value ), $countries );

            return $phone->isValid() ? $phone->formatE164() : $value;
        } catch ( Throwable ) {
            return $value;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Converts the number to E.164 form for the `us` and `international` formats.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return self::toE164( $field, $value );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['phone'], [
            'label'    => __( 'Phone Number' ),
            'defaults' => [
                'label'       => __( 'Phone Number' ),
                'placeholder' => '(123) 456-7890',
            ],
            'settings' => [
                [
                    'key'     => 'phone_format',
                    'input'   => 'select',
                    'default' => 'any',
                    'label'   => __( 'Phone Format' ),
                    'hint'    => __( 'US and International numbers are checked and stored in E.164 form (+15555550123).' ),
                    'options' => [
                        ['value' => 'any', 'label' => __( 'Any' )],
                        ['value' => 'us', 'label' => __( 'US — (###) ###-####' )],
                        ['value' => 'international', 'label' => __( 'International, with a country selector' )],
                    ],
                ],
                [
                    'key'          => 'default_country',
                    'input'        => 'select',
                    'default'      => 'US',
                    'label'        => __( 'Default Country' ),
                    'hint'         => __( 'The country an International phone field starts with.' ),
                    'options_from' => 'phone_countries',
                ],
                ...$this->inputMaskSettings(),
                $this->patternSetting(),
                $this->noDuplicatesSetting(),
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * Adds the `phone` rule for the `us` and `international` formats. With
     * `international` the rule also reads the `{name}_country` the Livewire
     * renderer submits.
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return match ( $field->getPhoneFormat() ) {
            'us'            => ['string', 'phone:US'],
            'international' => ['string', 'phone:INTERNATIONAL,' . $field->getPhoneDefaultCountry()],
            default         => ['string'],
        };
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        if ( 'any' === $field->getPhoneFormat() ) {
            return [];
        }

        return ['phone' => __( 'Please enter a valid phone number for :field.', ['field' => $field->label] )];
    }

    /**
     * {@inheritDoc}
     *
     * Shows an E.164 number formatted: in national form for a US field, in
     * international form otherwise.
     *
     * @since 1.6.0
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $stored = $value->value ?? '';
        $field  = $this->sourceField( $value );

        if ( ! str_starts_with( $stored, '+' ) || null === $field || 'any' === $field->getPhoneFormat() ) {
            return $stored;
        }

        try {
            $phone = new PhoneNumber( $stored );

            return 'us' === $field->getPhoneFormat() && $phone->isOfCountry( 'US' ) ? $phone->formatNational() : $phone->formatInternational();
        } catch ( Throwable ) {
            return $stored;
        }
    }
}
