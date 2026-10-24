<?php

/**
 * Field settings schema support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Captcha\CaptchaManager;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Reads, parses and stores the type-specific settings a field type declares.
 *
 * Each field type lists its own settings in the `settings` entry of its
 * definition. The Livewire, React and Vue builders render those settings from
 * the schema instead of hard-coding them per type; `resources/js/shared/fieldSettings.ts`
 * mirrors the parsing below so all three store the same values.
 *
 * A setting is an array with these keys (all but `key` and `label` optional):
 *
 * - `key`: the storage key.
 * - `label`, `hint`, `placeholder`: builder copy (translated).
 * - `input`: `text`, `textarea`, `integer`, `number`, `select`, `toggle`,
 *   `date`, `time` or `list` (comma-separated values). Defaults to `text`.
 * - `store`: `field_config` (default) or `validation_rules`.
 * - `legacy_store`: a column an older copy may still live in. It takes
 *   precedence when read and is cleared on write.
 * - `tab`: the builder tab, `general` (default), `validation` or `advanced`.
 * - `default`: the value the renderer falls back to when the setting is unset.
 * - `min`, `max`: bounds for `integer` and `number`; `max_length` for `text`
 *   and `textarea`.
 * - `step`: the `step` attribute of a `number` input.
 * - `options`: a list of `['value' => …, 'label' => …]` for `select`.
 * - `options_from`: `choice_sources` to append the registered choice sources
 *   (see ChoiceSources) to a `select`'s options when the schema is read. The
 *   setting is left out while no source is registered. `captcha_drivers`
 *   appends the registered CAPTCHA drivers (see CaptchaManager).
 * - `monospace`: whether to show the input in a monospace font.
 *
 * @since 1.6.0
 */
class FieldSettings
{
    /**
     * The input types a setting may use.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const INPUTS = ['text', 'textarea', 'integer', 'number', 'select', 'toggle', 'date', 'time', 'list'];

    /**
     * The builder tabs a setting may appear in.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const TABS = ['general', 'validation', 'advanced'];

    /**
     * The columns a setting may be stored in.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const STORES = ['field_config', 'validation_rules'];

    /**
     * Normalizes a settings schema, filling in defaults and dropping invalid entries.
     *
     * @since 1.6.0
     *
     * @param  mixed  $schema  The schema from a type definition.
     *
     * @return list<array<string, mixed>> The normalized settings.
     */
    public static function normalizeSchema( mixed $schema ): array
    {
        if ( ! is_array( $schema ) ) {
            return [];
        }

        $settings = [];

        foreach ( $schema as $setting ) {
            if ( ! is_array( $setting ) || ! is_string( $setting['key'] ?? null ) || '' === $setting['key'] ) {
                continue;
            }

            $setting = array_merge( [
                'label' => $setting['key'],
                'input' => 'text',
                'store' => 'field_config',
                'tab'   => 'general',
            ], $setting );

            if ( ! in_array( $setting['input'], self::INPUTS, true ) ) {
                $setting['input'] = 'text';
            }

            if ( ! in_array( $setting['store'], self::STORES, true ) ) {
                $setting['store'] = 'field_config';
            }

            if ( ! in_array( $setting['tab'], self::TABS, true ) ) {
                $setting['tab'] = 'general';
            }

            if ( isset( $setting['legacy_store'] ) && ! in_array( $setting['legacy_store'], self::STORES, true ) ) {
                unset( $setting['legacy_store'] );
            }

            if ( 'select' === $setting['input'] ) {
                $setting['options'] = self::normalizeOptions( $setting['options'] ?? [] );
            }

            $settings[] = $setting;
        }

        return $settings;
    }

    /**
     * Gets the settings schema for a field's type.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<array<string, mixed>> The settings.
     */
    public static function schema( FormField $field ): array
    {
        return self::resolveOptionSources( self::normalizeSchema( FieldTypes::handler( $field->type )->definition()['settings'] ?? [] ) );
    }

    /**
     * Gets the settings for a field's type that belong in one builder tab.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  string  $tab  The tab.
     *
     * @return list<array<string, mixed>> The settings.
     */
    public static function forTab( FormField $field, string $tab ): array
    {
        return array_values( array_filter(
            self::schema( $field ),
            fn ( array $setting ): bool => $tab === $setting['tab'],
        ) );
    }

    /**
     * Finds one of a field's settings by key.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  string  $key  The setting key.
     *
     * @return array<string, mixed>|null The setting, or null when the type has no such setting.
     */
    public static function find( FormField $field, string $key ): ?array
    {
        foreach ( self::schema( $field ) as $setting ) {
            if ( $key === $setting['key'] ) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * Gets a setting's stored value, preferring an unmigrated legacy copy.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  array<string, mixed>  $setting  The setting.
     *
     * @return mixed The stored value, or null when unset.
     */
    public static function value( FormField $field, array $setting ): mixed
    {
        $legacyStore = $setting['legacy_store'] ?? null;

        if ( is_string( $legacyStore ) && null !== ( $legacy = data_get( $field->{$legacyStore}, $setting['key'] ) ) ) {
            return $legacy;
        }

        return data_get( $field->{$setting['store']}, $setting['key'] );
    }

    /**
     * Gets a setting's value as the builder input shows it.
     *
     * Toggles give a boolean and selects fall back to the setting's default;
     * every other input gives the stored value as a string, or `''` when unset
     * so the input shows its placeholder.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  array<string, mixed>  $setting  The setting.
     *
     * @return bool|string The input value.
     */
    public static function inputValue( FormField $field, array $setting ): bool|string
    {
        $value = self::value( $field, $setting );

        return match ( $setting['input'] ) {
            'toggle' => null === $value ? (bool) ( $setting['default'] ?? false ) : filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
            'select' => is_scalar( $value ) ? (string) $value : (string) ( $setting['default'] ?? '' ),
            'list'   => is_array( $value )
                ? implode( ', ', array_filter( $value, 'is_string' ) )
                : ( is_string( $value ) ? $value : '' ),
            default  => is_scalar( $value ) && ! is_bool( $value ) ? (string) $value : '',
        };
    }

    /**
     * Parses a raw builder input into the value to store.
     *
     * Returns null when the input is blank or invalid, which clears the setting
     * so the renderer falls back to its default.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $setting  The setting.
     * @param  mixed  $raw  The raw input value.
     *
     * @return mixed The value to store, or null to clear it.
     */
    public static function parse( array $setting, mixed $raw ): mixed
    {
        if ( 'toggle' === $setting['input'] ) {
            return true === filter_var( $raw, FILTER_VALIDATE_BOOLEAN ) ? true : null;
        }

        if ( 'list' === $setting['input'] ) {
            $items = is_array( $raw ) ? $raw : explode( ',', is_scalar( $raw ) ? (string) $raw : '' );
            $items = array_values( array_filter(
                array_map( fn ( mixed $item ): string => is_scalar( $item ) ? trim( (string) $item ) : '', $items ),
                fn ( string $item ): bool => '' !== $item,
            ) );

            return [] === $items ? null : $items;
        }

        if ( ! is_scalar( $raw ) || is_bool( $raw ) ) {
            return null;
        }

        $raw = trim( (string) $raw );

        if ( '' === $raw ) {
            return null;
        }

        return match ( $setting['input'] ) {
            'integer' => self::parseInteger( $setting, $raw ),
            'number'  => self::parseNumber( $setting, $raw ),
            'select'  => in_array( $raw, array_column( $setting['options'] ?? [], 'value' ), true ) ? $raw : null,
            'date'    => self::parseDate( $raw ),
            'time'    => 1 === preg_match( FormField::TIME_FORMAT_PATTERN, $raw ) ? FormField::normalizeTimeValue( $raw ) : null,
            default   => isset( $setting['max_length'] ) ? mb_substr( $raw, 0, (int) $setting['max_length'] ) : $raw,
        };
    }

    /**
     * Builds the attribute update that stores a raw builder input for a setting.
     *
     * Writes the parsed value to the setting's column (or removes it when the
     * input is blank or invalid) and drops any legacy copy so it no longer
     * shadows the new value.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  array<string, mixed>  $setting  The setting.
     * @param  mixed  $raw  The raw input value.
     *
     * @return array<string, array<string, mixed>> The attributes to update.
     */
    public static function update( FormField $field, array $setting, mixed $raw ): array
    {
        $key    = $setting['key'];
        $store  = $setting['store'];
        $parsed = self::parse( $setting, $raw );
        $data   = [$store => $field->{$store} ?? []];

        if ( null === $parsed ) {
            unset( $data[ $store ][ $key ] );
        } else {
            $data[ $store ][ $key ] = $parsed;
        }

        $legacyStore = $setting['legacy_store'] ?? null;

        if ( is_string( $legacyStore ) && $legacyStore !== $store ) {
            $data[ $legacyStore ] = $field->{$legacyStore} ?? [];

            unset( $data[ $legacyStore ][ $key ] );
        }

        return $data;
    }

    /**
     * Fills in the options of settings that take them from elsewhere (`options_from`).
     *
     * @since 1.6.0
     *
     * @param  list<array<string, mixed>>  $settings  The normalized settings.
     *
     * @return list<array<string, mixed>> The settings, without any whose source has no options.
     */
    protected static function resolveOptionSources( array $settings ): array
    {
        $resolved = [];

        foreach ( $settings as $setting ) {
            if ( 'captcha_drivers' === ( $setting['options_from'] ?? null ) ) {
                $setting['options'] = array_merge( $setting['options'] ?? [], app( CaptchaManager::class )->options() );
            }

            if ( 'choice_sources' === ( $setting['options_from'] ?? null ) ) {
                $sources = app( ChoiceSources::class )->options();

                if ( [] === $sources ) {
                    continue;
                }

                $setting['options'] = array_merge( $setting['options'] ?? [], $sources );
            }

            unset( $setting['options_from'] );

            $resolved[] = $setting;
        }

        return $resolved;
    }

    /**
     * Normalizes select options to a list of value/label pairs.
     *
     * @since 1.6.0
     *
     * @param  mixed  $options  The options.
     *
     * @return list<array{value: string, label: string}> The options.
     */
    protected static function normalizeOptions( mixed $options ): array
    {
        if ( ! is_array( $options ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $options as $key => $option ) {
            if ( is_array( $option ) && isset( $option['value'] ) && is_scalar( $option['value'] ) ) {
                $normalized[] = [
                    'value' => (string) $option['value'],
                    'label' => (string) ( $option['label'] ?? $option['value'] ),
                ];
            } elseif ( is_scalar( $option ) ) {
                $normalized[] = [
                    'value' => is_string( $key ) ? $key : (string) $option,
                    'label' => (string) $option,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Parses a whole number within the setting's bounds.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $setting  The setting.
     * @param  string  $raw  The trimmed input.
     *
     * @return int|null The number, or null when invalid.
     */
    protected static function parseInteger( array $setting, string $raw ): ?int
    {
        if ( 1 !== preg_match( '/^-?\d+$/', $raw ) ) {
            return null;
        }

        $value = (int) $raw;

        return self::withinBounds( $setting, $value ) ? $value : null;
    }

    /**
     * Parses a number within the setting's bounds, keeping whole numbers as integers.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $setting  The setting.
     * @param  string  $raw  The trimmed input.
     *
     * @return float|int|null The number, or null when invalid.
     */
    protected static function parseNumber( array $setting, string $raw ): int|float|null
    {
        if ( ! is_numeric( $raw ) ) {
            return null;
        }

        $value = (float) $raw;

        if ( ! is_finite( $value ) || ! self::withinBounds( $setting, $value ) ) {
            return null;
        }

        return floor( $value ) === $value && abs( $value ) < PHP_INT_MAX ? (int) $value : $value;
    }

    /**
     * Parses a `Y-m-d` date.
     *
     * @since 1.6.0
     *
     * @param  string  $raw  The trimmed input.
     *
     * @return string|null The date, or null when invalid.
     */
    protected static function parseDate( string $raw ): ?string
    {
        if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches ) ) {
            return null;
        }

        return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $raw : null;
    }

    /**
     * Checks a number against the setting's `min` and `max`.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $setting  The setting.
     * @param  float|int  $value  The number.
     *
     * @return bool True when within bounds.
     */
    protected static function withinBounds( array $setting, int|float $value ): bool
    {
        if ( isset( $setting['min'] ) && is_numeric( $setting['min'] ) && $value < $setting['min'] ) {
            return false;
        }

        return ! ( isset( $setting['max'] ) && is_numeric( $setting['max'] ) && $value > $setting['max'] );
    }
}
