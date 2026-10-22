<?php

/**
 * Predefined choice lists and bulk choice parsing.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Lists of choices the builders can insert into a choice field, and the
 * `value|label` bulk syntax the builders' bulk editor uses.
 *
 * The built-in lists are countries (ISO 3166-1 alpha-2 codes), U.S. states,
 * Canadian provinces and territories, months, days of the week, age ranges
 * and satisfaction, agreement and importance scales. Values stay the same
 * in every locale; labels are translated. Apps add their own lists through
 * the `ap.forms.predefinedChoices` filter, which receives the lists keyed by
 * name, each `['label' => …, 'choices' => […]]`, where choices may be
 * `['value' => …, 'label' => …]` entries, a `value => label` map or a list of
 * strings. `resources/js/shared/choices.ts` mirrors the bulk parsing.
 *
 * @since 1.6.0
 */
class PredefinedChoices
{
    /**
     * The most choices one bulk edit may hold.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_CHOICES = 1000;

    /**
     * The separator between a choice's value and label in the bulk syntax.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const SEPARATOR = '|';

    /**
     * Gets every predefined list, after the `ap.forms.predefinedChoices` filter.
     *
     * Lists without a usable label or choices are dropped.
     *
     * @since 1.6.0
     *
     * @return array<string, array{label: string, choices: list<array{value: string, label: string}>}> The lists by key.
     */
    public static function all(): array
    {
        $lists = applyFilters( 'ap.forms.predefinedChoices', self::builtIn() );
        $valid = [];

        foreach ( is_array( $lists ) ? $lists : [] as $key => $list ) {
            if ( ! is_string( $key ) || '' === $key || ! is_array( $list ) ) {
                continue;
            }

            $label   = $list['label'] ?? null;
            $choices = self::normalizeChoices( $list['choices'] ?? [] );

            if ( ! is_scalar( $label ) || '' === trim( (string) $label ) || [] === $choices ) {
                continue;
            }

            $valid[ $key ] = ['label' => (string) $label, 'choices' => $choices];
        }

        return $valid;
    }

    /**
     * Gets one predefined list's choices.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The list key, such as `countries`.
     *
     * @return list<array{value: string, label: string}>|null The choices, or null when there is no such list.
     */
    public static function get( string $key ): ?array
    {
        return self::all()[ $key ]['choices'] ?? null;
    }

    /**
     * Parses bulk-edited choices, one per line.
     *
     * A line is `value|label`, or just a label that doubles as the value.
     * Only the first `|` separates, so labels may contain one. Blank lines
     * are skipped, a value that repeats keeps its first line, and anything
     * past MAX_CHOICES is dropped.
     *
     * @since 1.6.0
     *
     * @param  string  $text  The bulk text.
     *
     * @return list<array{value: string, label: string}> The choices.
     */
    public static function parseBulk( string $text ): array
    {
        $choices = [];
        $seen    = [];

        foreach ( preg_split( '/\r\n|\r|\n/', $text ) ?: [] as $line ) {
            $line = trim( $line );

            if ( '' === $line ) {
                continue;
            }

            if ( str_contains( $line, self::SEPARATOR ) ) {
                [$value, $label] = array_map( 'trim', explode( self::SEPARATOR, $line, 2 ) );
                $value           = '' === $value ? $label : $value;
                $label           = '' === $label ? $value : $label;
            } else {
                $value = $line;
                $label = $line;
            }

            if ( '' === $value || isset( $seen[ $value ] ) ) {
                continue;
            }

            $seen[ $value ] = true;
            $choices[]      = ['value' => $value, 'label' => $label];

            if ( count( $choices ) >= self::MAX_CHOICES ) {
                break;
            }
        }

        return $choices;
    }

    /**
     * Applies bulk-edited choices to a field's existing options.
     *
     * `replace` swaps the options for the new ones, keeping the default mark
     * of any value that stays; `append` adds the new values after the existing
     * options and skips values already there. Both stop at MAX_CHOICES.
     *
     * @since 1.6.0
     *
     * @param  array<int|string, mixed>  $existing  The field's options.
     * @param  list<array{value: string, label: string}>  $choices  The parsed choices (see parseBulk()).
     * @param  string  $mode  `replace` or `append`.
     *
     * @return list<array{value: string, label: string, default?: bool}> The options.
     */
    public static function apply( array $existing, array $choices, string $mode = 'replace' ): array
    {
        $existing = self::normalizeChoices( $existing );

        if ( 'append' === $mode ) {
            $merged = $existing;
            $seen   = array_fill_keys( array_column( $existing, 'value' ), true );

            foreach ( self::normalizeChoices( $choices ) as $choice ) {
                if ( ! isset( $seen[ $choice['value'] ] ) ) {
                    $seen[ $choice['value'] ] = true;
                    $merged[]                 = $choice;
                }
            }

            return array_slice( $merged, 0, self::MAX_CHOICES );
        }

        $defaults = array_fill_keys(
            array_column( array_filter( $existing, fn ( array $option ): bool => $option['default'] ?? false ), 'value' ),
            true,
        );

        return array_slice( array_map(
            fn ( array $choice ): array => isset( $defaults[ $choice['value'] ] ) ? $choice + ['default' => true] : $choice,
            self::normalizeChoices( $choices ),
        ), 0, self::MAX_CHOICES );
    }

    /**
     * Writes choices in the bulk syntax, one per line.
     *
     * A choice whose value equals its label is written as the label alone.
     *
     * @since 1.6.0
     *
     * @param  array<int|string, mixed>  $choices  The choices.
     *
     * @return string The bulk text.
     */
    public static function toBulk( array $choices ): string
    {
        return implode( "\n", array_map(
            fn ( array $choice ): string => $choice['value'] === $choice['label']
                ? $choice['label']
                : $choice['value'] . self::SEPARATOR . $choice['label'],
            self::normalizeChoices( $choices ),
        ) );
    }

    /**
     * Normalizes choices to a list of value/label pairs.
     *
     * Accepts `['value' => …, 'label' => …]` entries (keeping a `default`
     * flag), a `value => label` map or a list of strings. Entries without a
     * scalar value are dropped.
     *
     * @since 1.6.0
     *
     * @param  mixed  $choices  The choices.
     *
     * @return list<array{value: string, label: string, default?: bool}> The choices.
     */
    public static function normalizeChoices( mixed $choices ): array
    {
        if ( ! is_array( $choices ) ) {
            return [];
        }

        $isList     = array_is_list( $choices );
        $normalized = [];

        foreach ( $choices as $key => $choice ) {
            if ( is_array( $choice ) ) {
                if ( ! isset( $choice['value'] ) || ! is_scalar( $choice['value'] ) || '' === (string) $choice['value'] ) {
                    continue;
                }

                $label = $choice['label'] ?? null;
                $entry = [
                    'value' => (string) $choice['value'],
                    'label' => is_scalar( $label ) && '' !== trim( (string) $label ) ? (string) $label : (string) $choice['value'],
                ];

                if ( true === ( $choice['default'] ?? false ) ) {
                    $entry['default'] = true;
                }

                $normalized[] = $entry;
            } elseif ( is_scalar( $choice ) && '' !== (string) $choice ) {
                $normalized[] = [
                    'value' => $isList ? (string) $choice : (string) $key,
                    'label' => (string) $choice,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Gets the built-in lists.
     *
     * @since 1.6.0
     *
     * @return array<string, array{label: string, choices: array<string, string>}> The lists by key.
     */
    protected static function builtIn(): array
    {
        return [
            'countries' => [
                'label'   => __( 'Countries' ),
                'choices' => self::countries(),
            ],
            'us_states' => [
                'label'   => __( 'U.S. States' ),
                'choices' => self::usStates(),
            ],
            'canadian_provinces' => [
                'label'   => __( 'Canadian Provinces and Territories' ),
                'choices' => self::canadianProvinces(),
            ],
            'months' => [
                'label'   => __( 'Months' ),
                'choices' => [
                    'January'   => __( 'January' ),
                    'February'  => __( 'February' ),
                    'March'     => __( 'March' ),
                    'April'     => __( 'April' ),
                    'May'       => __( 'May' ),
                    'June'      => __( 'June' ),
                    'July'      => __( 'July' ),
                    'August'    => __( 'August' ),
                    'September' => __( 'September' ),
                    'October'   => __( 'October' ),
                    'November'  => __( 'November' ),
                    'December'  => __( 'December' ),
                ],
            ],
            'days_of_week' => [
                'label'   => __( 'Days of the Week' ),
                'choices' => [
                    'Monday'    => __( 'Monday' ),
                    'Tuesday'   => __( 'Tuesday' ),
                    'Wednesday' => __( 'Wednesday' ),
                    'Thursday'  => __( 'Thursday' ),
                    'Friday'    => __( 'Friday' ),
                    'Saturday'  => __( 'Saturday' ),
                    'Sunday'    => __( 'Sunday' ),
                ],
            ],
            'age_ranges' => [
                'label'   => __( 'Age Ranges' ),
                'choices' => [
                    'under-18' => __( 'Under 18' ),
                    '18-24'    => __( '18 to 24' ),
                    '25-34'    => __( '25 to 34' ),
                    '35-44'    => __( '35 to 44' ),
                    '45-54'    => __( '45 to 54' ),
                    '55-64'    => __( '55 to 64' ),
                    '65-plus'  => __( '65 or older' ),
                ],
            ],
            'satisfaction' => [
                'label'   => __( 'Satisfaction' ),
                'choices' => [
                    'very-satisfied'    => __( 'Very satisfied' ),
                    'satisfied'         => __( 'Satisfied' ),
                    'neutral'           => __( 'Neither satisfied nor dissatisfied' ),
                    'dissatisfied'      => __( 'Dissatisfied' ),
                    'very-dissatisfied' => __( 'Very dissatisfied' ),
                ],
            ],
            'agreement' => [
                'label'   => __( 'Agreement' ),
                'choices' => [
                    'strongly-agree'    => __( 'Strongly agree' ),
                    'agree'             => __( 'Agree' ),
                    'neutral'           => __( 'Neither agree nor disagree' ),
                    'disagree'          => __( 'Disagree' ),
                    'strongly-disagree' => __( 'Strongly disagree' ),
                ],
            ],
            'importance' => [
                'label'   => __( 'Importance' ),
                'choices' => [
                    'very-important'       => __( 'Very important' ),
                    'important'            => __( 'Important' ),
                    'moderately-important' => __( 'Moderately important' ),
                    'slightly-important'   => __( 'Slightly important' ),
                    'not-important'        => __( 'Not important' ),
                ],
            ],
        ];
    }

    /**
     * Gets the countries, keyed by ISO 3166-1 alpha-2 code.
     *
     * @since 1.6.0
     *
     * @return array<string, string> The country names by code.
     */
    protected static function countries(): array
    {
        return [
            'AF' => __( 'Afghanistan' ),
            'AX' => __( 'Åland Islands' ),
            'AL' => __( 'Albania' ),
            'DZ' => __( 'Algeria' ),
            'AS' => __( 'American Samoa' ),
            'AD' => __( 'Andorra' ),
            'AO' => __( 'Angola' ),
            'AI' => __( 'Anguilla' ),
            'AQ' => __( 'Antarctica' ),
            'AG' => __( 'Antigua and Barbuda' ),
            'AR' => __( 'Argentina' ),
            'AM' => __( 'Armenia' ),
            'AW' => __( 'Aruba' ),
            'AU' => __( 'Australia' ),
            'AT' => __( 'Austria' ),
            'AZ' => __( 'Azerbaijan' ),
            'BS' => __( 'Bahamas' ),
            'BH' => __( 'Bahrain' ),
            'BD' => __( 'Bangladesh' ),
            'BB' => __( 'Barbados' ),
            'BY' => __( 'Belarus' ),
            'BE' => __( 'Belgium' ),
            'BZ' => __( 'Belize' ),
            'BJ' => __( 'Benin' ),
            'BM' => __( 'Bermuda' ),
            'BT' => __( 'Bhutan' ),
            'BO' => __( 'Bolivia' ),
            'BQ' => __( 'Bonaire, Sint Eustatius and Saba' ),
            'BA' => __( 'Bosnia and Herzegovina' ),
            'BW' => __( 'Botswana' ),
            'BV' => __( 'Bouvet Island' ),
            'BR' => __( 'Brazil' ),
            'IO' => __( 'British Indian Ocean Territory' ),
            'BN' => __( 'Brunei Darussalam' ),
            'BG' => __( 'Bulgaria' ),
            'BF' => __( 'Burkina Faso' ),
            'BI' => __( 'Burundi' ),
            'CV' => __( 'Cabo Verde' ),
            'KH' => __( 'Cambodia' ),
            'CM' => __( 'Cameroon' ),
            'CA' => __( 'Canada' ),
            'KY' => __( 'Cayman Islands' ),
            'CF' => __( 'Central African Republic' ),
            'TD' => __( 'Chad' ),
            'CL' => __( 'Chile' ),
            'CN' => __( 'China' ),
            'CX' => __( 'Christmas Island' ),
            'CC' => __( 'Cocos (Keeling) Islands' ),
            'CO' => __( 'Colombia' ),
            'KM' => __( 'Comoros' ),
            'CG' => __( 'Congo' ),
            'CD' => __( 'Congo, Democratic Republic of the' ),
            'CK' => __( 'Cook Islands' ),
            'CR' => __( 'Costa Rica' ),
            'CI' => __( "Côte d'Ivoire" ),
            'HR' => __( 'Croatia' ),
            'CU' => __( 'Cuba' ),
            'CW' => __( 'Curaçao' ),
            'CY' => __( 'Cyprus' ),
            'CZ' => __( 'Czechia' ),
            'DK' => __( 'Denmark' ),
            'DJ' => __( 'Djibouti' ),
            'DM' => __( 'Dominica' ),
            'DO' => __( 'Dominican Republic' ),
            'EC' => __( 'Ecuador' ),
            'EG' => __( 'Egypt' ),
            'SV' => __( 'El Salvador' ),
            'GQ' => __( 'Equatorial Guinea' ),
            'ER' => __( 'Eritrea' ),
            'EE' => __( 'Estonia' ),
            'SZ' => __( 'Eswatini' ),
            'ET' => __( 'Ethiopia' ),
            'FK' => __( 'Falkland Islands (Malvinas)' ),
            'FO' => __( 'Faroe Islands' ),
            'FJ' => __( 'Fiji' ),
            'FI' => __( 'Finland' ),
            'FR' => __( 'France' ),
            'GF' => __( 'French Guiana' ),
            'PF' => __( 'French Polynesia' ),
            'TF' => __( 'French Southern Territories' ),
            'GA' => __( 'Gabon' ),
            'GM' => __( 'Gambia' ),
            'GE' => __( 'Georgia' ),
            'DE' => __( 'Germany' ),
            'GH' => __( 'Ghana' ),
            'GI' => __( 'Gibraltar' ),
            'GR' => __( 'Greece' ),
            'GL' => __( 'Greenland' ),
            'GD' => __( 'Grenada' ),
            'GP' => __( 'Guadeloupe' ),
            'GU' => __( 'Guam' ),
            'GT' => __( 'Guatemala' ),
            'GG' => __( 'Guernsey' ),
            'GN' => __( 'Guinea' ),
            'GW' => __( 'Guinea-Bissau' ),
            'GY' => __( 'Guyana' ),
            'HT' => __( 'Haiti' ),
            'HM' => __( 'Heard Island and McDonald Islands' ),
            'VA' => __( 'Holy See' ),
            'HN' => __( 'Honduras' ),
            'HK' => __( 'Hong Kong' ),
            'HU' => __( 'Hungary' ),
            'IS' => __( 'Iceland' ),
            'IN' => __( 'India' ),
            'ID' => __( 'Indonesia' ),
            'IR' => __( 'Iran' ),
            'IQ' => __( 'Iraq' ),
            'IE' => __( 'Ireland' ),
            'IM' => __( 'Isle of Man' ),
            'IL' => __( 'Israel' ),
            'IT' => __( 'Italy' ),
            'JM' => __( 'Jamaica' ),
            'JP' => __( 'Japan' ),
            'JE' => __( 'Jersey' ),
            'JO' => __( 'Jordan' ),
            'KZ' => __( 'Kazakhstan' ),
            'KE' => __( 'Kenya' ),
            'KI' => __( 'Kiribati' ),
            'KP' => __( 'Korea, Democratic People\'s Republic of' ),
            'KR' => __( 'Korea, Republic of' ),
            'KW' => __( 'Kuwait' ),
            'KG' => __( 'Kyrgyzstan' ),
            'LA' => __( 'Lao People\'s Democratic Republic' ),
            'LV' => __( 'Latvia' ),
            'LB' => __( 'Lebanon' ),
            'LS' => __( 'Lesotho' ),
            'LR' => __( 'Liberia' ),
            'LY' => __( 'Libya' ),
            'LI' => __( 'Liechtenstein' ),
            'LT' => __( 'Lithuania' ),
            'LU' => __( 'Luxembourg' ),
            'MO' => __( 'Macao' ),
            'MG' => __( 'Madagascar' ),
            'MW' => __( 'Malawi' ),
            'MY' => __( 'Malaysia' ),
            'MV' => __( 'Maldives' ),
            'ML' => __( 'Mali' ),
            'MT' => __( 'Malta' ),
            'MH' => __( 'Marshall Islands' ),
            'MQ' => __( 'Martinique' ),
            'MR' => __( 'Mauritania' ),
            'MU' => __( 'Mauritius' ),
            'YT' => __( 'Mayotte' ),
            'MX' => __( 'Mexico' ),
            'FM' => __( 'Micronesia' ),
            'MD' => __( 'Moldova' ),
            'MC' => __( 'Monaco' ),
            'MN' => __( 'Mongolia' ),
            'ME' => __( 'Montenegro' ),
            'MS' => __( 'Montserrat' ),
            'MA' => __( 'Morocco' ),
            'MZ' => __( 'Mozambique' ),
            'MM' => __( 'Myanmar' ),
            'NA' => __( 'Namibia' ),
            'NR' => __( 'Nauru' ),
            'NP' => __( 'Nepal' ),
            'NL' => __( 'Netherlands' ),
            'NC' => __( 'New Caledonia' ),
            'NZ' => __( 'New Zealand' ),
            'NI' => __( 'Nicaragua' ),
            'NE' => __( 'Niger' ),
            'NG' => __( 'Nigeria' ),
            'NU' => __( 'Niue' ),
            'NF' => __( 'Norfolk Island' ),
            'MK' => __( 'North Macedonia' ),
            'MP' => __( 'Northern Mariana Islands' ),
            'NO' => __( 'Norway' ),
            'OM' => __( 'Oman' ),
            'PK' => __( 'Pakistan' ),
            'PW' => __( 'Palau' ),
            'PS' => __( 'Palestine, State of' ),
            'PA' => __( 'Panama' ),
            'PG' => __( 'Papua New Guinea' ),
            'PY' => __( 'Paraguay' ),
            'PE' => __( 'Peru' ),
            'PH' => __( 'Philippines' ),
            'PN' => __( 'Pitcairn' ),
            'PL' => __( 'Poland' ),
            'PT' => __( 'Portugal' ),
            'PR' => __( 'Puerto Rico' ),
            'QA' => __( 'Qatar' ),
            'RE' => __( 'Réunion' ),
            'RO' => __( 'Romania' ),
            'RU' => __( 'Russian Federation' ),
            'RW' => __( 'Rwanda' ),
            'BL' => __( 'Saint Barthélemy' ),
            'SH' => __( 'Saint Helena, Ascension and Tristan da Cunha' ),
            'KN' => __( 'Saint Kitts and Nevis' ),
            'LC' => __( 'Saint Lucia' ),
            'MF' => __( 'Saint Martin (French part)' ),
            'PM' => __( 'Saint Pierre and Miquelon' ),
            'VC' => __( 'Saint Vincent and the Grenadines' ),
            'WS' => __( 'Samoa' ),
            'SM' => __( 'San Marino' ),
            'ST' => __( 'Sao Tome and Principe' ),
            'SA' => __( 'Saudi Arabia' ),
            'SN' => __( 'Senegal' ),
            'RS' => __( 'Serbia' ),
            'SC' => __( 'Seychelles' ),
            'SL' => __( 'Sierra Leone' ),
            'SG' => __( 'Singapore' ),
            'SX' => __( 'Sint Maarten (Dutch part)' ),
            'SK' => __( 'Slovakia' ),
            'SI' => __( 'Slovenia' ),
            'SB' => __( 'Solomon Islands' ),
            'SO' => __( 'Somalia' ),
            'ZA' => __( 'South Africa' ),
            'GS' => __( 'South Georgia and the South Sandwich Islands' ),
            'SS' => __( 'South Sudan' ),
            'ES' => __( 'Spain' ),
            'LK' => __( 'Sri Lanka' ),
            'SD' => __( 'Sudan' ),
            'SR' => __( 'Suriname' ),
            'SJ' => __( 'Svalbard and Jan Mayen' ),
            'SE' => __( 'Sweden' ),
            'CH' => __( 'Switzerland' ),
            'SY' => __( 'Syrian Arab Republic' ),
            'TW' => __( 'Taiwan' ),
            'TJ' => __( 'Tajikistan' ),
            'TZ' => __( 'Tanzania' ),
            'TH' => __( 'Thailand' ),
            'TL' => __( 'Timor-Leste' ),
            'TG' => __( 'Togo' ),
            'TK' => __( 'Tokelau' ),
            'TO' => __( 'Tonga' ),
            'TT' => __( 'Trinidad and Tobago' ),
            'TN' => __( 'Tunisia' ),
            'TR' => __( 'Türkiye' ),
            'TM' => __( 'Turkmenistan' ),
            'TC' => __( 'Turks and Caicos Islands' ),
            'TV' => __( 'Tuvalu' ),
            'UG' => __( 'Uganda' ),
            'UA' => __( 'Ukraine' ),
            'AE' => __( 'United Arab Emirates' ),
            'GB' => __( 'United Kingdom' ),
            'US' => __( 'United States' ),
            'UM' => __( 'United States Minor Outlying Islands' ),
            'UY' => __( 'Uruguay' ),
            'UZ' => __( 'Uzbekistan' ),
            'VU' => __( 'Vanuatu' ),
            'VE' => __( 'Venezuela' ),
            'VN' => __( 'Viet Nam' ),
            'VG' => __( 'Virgin Islands (British)' ),
            'VI' => __( 'Virgin Islands (U.S.)' ),
            'WF' => __( 'Wallis and Futuna' ),
            'EH' => __( 'Western Sahara' ),
            'YE' => __( 'Yemen' ),
            'ZM' => __( 'Zambia' ),
            'ZW' => __( 'Zimbabwe' ),
        ];
    }

    /**
     * Gets the U.S. states and the District of Columbia, keyed by postal code.
     *
     * @since 1.6.0
     *
     * @return array<string, string> The state names by code.
     */
    protected static function usStates(): array
    {
        return [
            'AL' => __( 'Alabama' ),
            'AK' => __( 'Alaska' ),
            'AZ' => __( 'Arizona' ),
            'AR' => __( 'Arkansas' ),
            'CA' => __( 'California' ),
            'CO' => __( 'Colorado' ),
            'CT' => __( 'Connecticut' ),
            'DE' => __( 'Delaware' ),
            'DC' => __( 'District of Columbia' ),
            'FL' => __( 'Florida' ),
            'GA' => __( 'Georgia' ),
            'HI' => __( 'Hawaii' ),
            'ID' => __( 'Idaho' ),
            'IL' => __( 'Illinois' ),
            'IN' => __( 'Indiana' ),
            'IA' => __( 'Iowa' ),
            'KS' => __( 'Kansas' ),
            'KY' => __( 'Kentucky' ),
            'LA' => __( 'Louisiana' ),
            'ME' => __( 'Maine' ),
            'MD' => __( 'Maryland' ),
            'MA' => __( 'Massachusetts' ),
            'MI' => __( 'Michigan' ),
            'MN' => __( 'Minnesota' ),
            'MS' => __( 'Mississippi' ),
            'MO' => __( 'Missouri' ),
            'MT' => __( 'Montana' ),
            'NE' => __( 'Nebraska' ),
            'NV' => __( 'Nevada' ),
            'NH' => __( 'New Hampshire' ),
            'NJ' => __( 'New Jersey' ),
            'NM' => __( 'New Mexico' ),
            'NY' => __( 'New York' ),
            'NC' => __( 'North Carolina' ),
            'ND' => __( 'North Dakota' ),
            'OH' => __( 'Ohio' ),
            'OK' => __( 'Oklahoma' ),
            'OR' => __( 'Oregon' ),
            'PA' => __( 'Pennsylvania' ),
            'RI' => __( 'Rhode Island' ),
            'SC' => __( 'South Carolina' ),
            'SD' => __( 'South Dakota' ),
            'TN' => __( 'Tennessee' ),
            'TX' => __( 'Texas' ),
            'UT' => __( 'Utah' ),
            'VT' => __( 'Vermont' ),
            'VA' => __( 'Virginia' ),
            'WA' => __( 'Washington' ),
            'WV' => __( 'West Virginia' ),
            'WI' => __( 'Wisconsin' ),
            'WY' => __( 'Wyoming' ),
        ];
    }

    /**
     * Gets the Canadian provinces and territories, keyed by postal abbreviation.
     *
     * @since 1.6.0
     *
     * @return array<string, string> The names by code.
     */
    protected static function canadianProvinces(): array
    {
        return [
            'AB' => __( 'Alberta' ),
            'BC' => __( 'British Columbia' ),
            'MB' => __( 'Manitoba' ),
            'NB' => __( 'New Brunswick' ),
            'NL' => __( 'Newfoundland and Labrador' ),
            'NT' => __( 'Northwest Territories' ),
            'NS' => __( 'Nova Scotia' ),
            'NU' => __( 'Nunavut' ),
            'ON' => __( 'Ontario' ),
            'PE' => __( 'Prince Edward Island' ),
            'QC' => __( 'Quebec' ),
            'SK' => __( 'Saskatchewan' ),
            'YT' => __( 'Yukon' ),
        ];
    }
}
