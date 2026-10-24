<?php

/**
 * Address field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\PredefinedChoices;

/**
 * A postal address: street, second street line, city, state, postal code
 * and country.
 *
 * The `address_type` setting picks how the state and postal code inputs
 * work:
 *
 * - `international` (the default): a free-text state, province or region,
 *   any postal code, and a country select of the `ap.forms.countries` list.
 * - `us`: a select of U.S. states and a ZIP code (`12345` or `12345-6789`);
 *   the country is fixed to the United States.
 * - `canada`: a select of provinces and territories and a Canadian postal
 *   code (`A1A 1A1`); the country is fixed to Canada.
 *
 * An international address may also hide the country input and fix it to
 * the default country. A fixed country is not shown but is stored with the
 * value. The value is shown as a multi-line postal address.
 *
 * Address autocomplete providers can hook in through the
 * `ap.forms.addressAutocomplete` filter (HTML added after the Livewire
 * input group) and the `data-forms-part` attribute each input carries.
 *
 * @since 1.6.0
 */
class AddressFieldType extends CompositeFieldType
{
    /**
     * The address types.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const TYPES = ['international', 'us', 'canada'];

    /**
     * The countries the U.S. and Canada address types fix the country to.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public const FIXED_COUNTRIES = ['us' => 'US', 'canada' => 'CA'];

    /**
     * The postal code patterns of the U.S. and Canada address types.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public const POSTAL_PATTERNS = [
        'us'     => '/^\d{5}(-\d{4})?$/',
        'canada' => '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z] ?\d[ABCEGHJ-NPRSTV-Z]\d$/i',
    ];

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'address';

    /**
     * Gets a field's address type.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The address field.
     *
     * @return string `international`, `us` or `canada`.
     */
    public static function addressType( FormField $field ): string
    {
        $type = $field->getConfig( 'address_type' );

        return is_string( $type ) && in_array( $type, self::TYPES, true ) ? $type : 'international';
    }

    /**
     * Gets the country a field stores without showing a country input.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The address field.
     *
     * @return string|null The country code, or null when visitors pick the country.
     */
    public static function fixedCountry( FormField $field ): ?string
    {
        $type = self::addressType( $field );

        if ( isset( self::FIXED_COUNTRIES[ $type ] ) ) {
            return self::FIXED_COUNTRIES[ $type ];
        }

        if ( true !== filter_var( $field->getConfig( 'hide_country', false ), FILTER_VALIDATE_BOOLEAN ) ) {
            return null;
        }

        return self::defaultCountry( $field );
    }

    /**
     * Gets a field's default country, when it is in the country list.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The address field.
     *
     * @return string|null The country code.
     */
    public static function defaultCountry( FormField $field ): ?string
    {
        $country = $field->getConfig( 'default_country' );

        return is_string( $country ) && array_key_exists( $country, PredefinedChoices::countries() ) ? $country : null;
    }

    /**
     * {@inheritDoc}
     *
     * The state input is a select for the U.S. and Canada types, the postal
     * code is checked against their pattern, and the country input is left
     * out when the country is fixed.
     *
     * @since 1.6.0
     */
    public function subInputDefinitions( FormField $field ): array
    {
        $type         = self::addressType( $field );
        $defaultState = $field->getConfig( 'default_state' );
        $defaultState = is_string( $defaultState ) && '' !== trim( $defaultState ) ? trim( $defaultState ) : null;

        $state  = ['key' => 'state', 'label' => __( 'State / Province / Region' ), 'autocomplete' => 'address-level1', 'default' => $defaultState];
        $postal = ['key' => 'postal_code', 'label' => __( 'ZIP / Postal Code' ), 'autocomplete' => 'postal-code', 'required' => true];

        if ( 'us' === $type ) {
            $state  = array_merge( $state, ['label' => __( 'State' ), 'type' => 'select', 'options' => PredefinedChoices::usStates()] );
            $postal = array_merge( $postal, ['label' => __( 'ZIP Code' ), 'rules' => ['regex:' . self::POSTAL_PATTERNS['us']]] );
        } elseif ( 'canada' === $type ) {
            $state  = array_merge( $state, ['label' => __( 'Province' ), 'type' => 'select', 'options' => PredefinedChoices::canadianProvinces()] );
            $postal = array_merge( $postal, ['label' => __( 'Postal Code' ), 'rules' => ['regex:' . self::POSTAL_PATTERNS['canada']]] );
        }

        $definitions = [
            ['key' => 'street', 'label' => __( 'Street Address' ), 'autocomplete' => 'address-line1', 'required' => true, 'wide' => true],
            ['key' => 'street2', 'label' => __( 'Address Line 2' ), 'autocomplete' => 'address-line2', 'wide' => true],
            ['key' => 'city', 'label' => __( 'City' ), 'autocomplete' => 'address-level2', 'required' => true],
            $state,
            $postal,
        ];

        if ( null === self::fixedCountry( $field ) ) {
            $definitions[] = [
                'key'          => 'country',
                'label'        => __( 'Country' ),
                'autocomplete' => 'country-name',
                'type'         => 'select',
                'options'      => PredefinedChoices::countries(),
                'default'      => self::defaultCountry( $field ),
                'required'     => true,
            ];
        }

        return $definitions;
    }

    /**
     * {@inheritDoc}
     *
     * A U.S. or Canadian postal code is stored in upper case, and a fixed
     * country is added.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        $normalized = parent::normalize( $field, $value );

        if ( 'international' !== self::addressType( $field ) && isset( $normalized['postal_code'] ) ) {
            $normalized['postal_code'] = mb_strtoupper( $normalized['postal_code'] );
        }

        $country = self::fixedCountry( $field );

        if ( null !== $country ) {
            $normalized['country'] = $country;
        }

        return $normalized;
    }

    /**
     * {@inheritDoc}
     *
     * Adds a fixed country, so a read-only address is stored like a submitted one.
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return $this->normalize( $field, [] );
    }

    /**
     * {@inheritDoc}
     *
     * One line each for the street lines, then "City, State Postal code"
     * (with a listed state's code, such as NY), then the country name.
     *
     * @since 1.6.0
     */
    public function format( ?FormField $field, array $parts ): string
    {
        $filled = $this->filledParts( $field, $parts );

        if ( null === $field ) {
            return implode( "\n", $filled );
        }

        $country = $filled['country'] ?? null;

        if ( null === $country && is_scalar( $parts['country'] ?? null ) ) {
            $code    = (string) $parts['country'];
            $country = PredefinedChoices::countries()[ $code ] ?? ( '' === trim( $code ) ? null : $code );
        }

        // Postal addresses abbreviate a listed state or province (NY, ON)
        $state  = is_scalar( $parts['state'] ?? null ) && '' !== trim( (string) $parts['state'] ) ? trim( (string) $parts['state'] ) : null;
        $region = trim( implode( ' ', array_filter( [$state, $filled['postal_code'] ?? null] ) ) );
        $lines  = [
            $filled['street'] ?? null,
            $filled['street2'] ?? null,
            implode( ', ', array_filter( [$filled['city'] ?? null, '' === $region ? null : $region] ) ),
            $country,
        ];

        return implode( "\n", array_filter( $lines, fn ( ?string $line ): bool => null !== $line && '' !== $line ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        $messages = parent::messages( $field );
        $type     = self::addressType( $field );

        if ( 'us' === $type ) {
            $messages['postal_code.regex'] = __( ':field: enter a ZIP code such as 12345 or 12345-6789.', ['field' => $field->label] );
        } elseif ( 'canada' === $type ) {
            $messages['postal_code.regex'] = __( ':field: enter a postal code such as K1A 0B1.', ['field' => $field->label] );
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        $countries = [['value' => '', 'label' => __( 'None' )]];

        foreach ( PredefinedChoices::countries() as $code => $name ) {
            $countries[] = ['value' => (string) $code, 'label' => $name];
        }

        return array_merge( FieldTypes::TYPE_METADATA['address'], [
            'label'    => __( 'Address' ),
            'defaults' => [
                'label' => __( 'Address' ),
            ],
            'sub_inputs' => [],
            'settings'   => [
                [
                    'key'     => 'address_type',
                    'input'   => 'select',
                    'default' => 'international',
                    'label'   => __( 'Address Type' ),
                    'hint'    => __( 'United States and Canada addresses use a state or province list, check the postal code and fix the country.' ),
                    'options' => [
                        ['value' => 'international', 'label' => __( 'International' )],
                        ['value' => 'us', 'label' => __( 'United States' )],
                        ['value' => 'canada', 'label' => __( 'Canada' )],
                    ],
                ],
                [
                    'key'     => 'default_country',
                    'input'   => 'select',
                    'default' => '',
                    'label'   => __( 'Default Country' ),
                    'options' => $countries,
                ],
                [
                    'key'   => 'hide_country',
                    'input' => 'toggle',
                    'label' => __( 'Hide country' ),
                    'hint'  => __( 'Stores the default country without asking for it. International addresses only.' ),
                ],
                [
                    'key'        => 'default_state',
                    'max_length' => 255,
                    'label'      => __( 'Default State / Province' ),
                    'hint'       => __( 'For United States and Canada addresses, use the two-letter code, such as NY or ON.' ),
                ],
            ],
        ] );
    }
}
