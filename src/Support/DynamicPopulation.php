<?php

/**
 * Dynamic field population.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\FieldTypes\DateTimeFieldType;
use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Works out the value a field starts with in the renderer.
 *
 * A field with dynamic population on takes its value, in order of precedence,
 * from the renderer's `prefill`, the page's query string, the
 * `ap.forms.fieldValue.{parameter}` filter and finally its default value with
 * merge tags replaced. Every populated value is sanitized, and choice fields
 * only take values that match one of their options. A populated value is only
 * a starting value: it is validated like anything else the visitor submits,
 * so never rely on it for authorization.
 *
 * `resources/js/shared/dynamicPopulation.ts` mirrors this for the React and
 * Vue renderers.
 *
 * @since 1.6.0
 */
class DynamicPopulation
{
    /**
     * The longest populated value kept, in characters.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_LENGTH = 1000;

    /**
     * Gets the value a field starts with.
     *
     * Read-only fields start with their read-only value, which the client
     * cannot change, so they are not populated from the prefill or query
     * string. Choice fields start with the options marked as defaults, or
     * else their default value. Composite fields start with each enabled
     * sub-input's default, or the prefill, query string or filter value of
     * its population parameter when it has one (see compositeValue()).
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  PopulationContext  $context  The visitor and page.
     * @param  array<string, mixed>  $prefill  Values passed to the renderer, keyed by parameter name.
     *
     * @return mixed The value: a string, a boolean for a checkbox or toggle, a list for a multi-choice field
     *               or a map of sub-input keys for a composite field.
     */
    public static function initialValue( FormField $field, PopulationContext $context, array $prefill = [] ): mixed
    {
        $value = self::resolveInitialValue( $field, $context, $prefill );

        // A date and time input takes `YYYY-MM-DDTHH:MM` (since 1.6.0)
        if ( DateTimeFieldType::TYPE === $field->type && is_string( $value ) && '' !== $value ) {
            return DateTimeFieldType::toInputValue( $value );
        }

        // The rich text editors render their value as HTML (since 1.6.0)
        return $field->usesRichText() && is_string( $value ) ? RichText::sanitize( $value ) : $value;
    }

    /**
     * Makes a populated value safe to show and submit.
     *
     * Strips tags and control characters, trims, and caps the length.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The value.
     *
     * @return string The sanitized value.
     */
    public static function sanitize( string $value ): string
    {
        $value = strip_tags( $value );
        $value = (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );

        return mb_substr( trim( $value ), 0, self::MAX_LENGTH );
    }

    /**
     * Works out the value a field starts with, before a rich text value is sanitized.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  PopulationContext  $context  The visitor and page.
     * @param  array<string, mixed>  $prefill  Values passed to the renderer, keyed by parameter name.
     *
     * @return mixed The value.
     */
    protected static function resolveInitialValue( FormField $field, PopulationContext $context, array $prefill ): mixed
    {
        if ( $field->isReadOnly() ) {
            return $field->getReadOnlyValue();
        }

        if ( $field->isComposite() ) {
            return self::compositeValue( $field, $context, $prefill );
        }

        // A list starts with empty rows to fill in (since 1.6.0)
        if ( ListFieldType::TYPE === $field->type ) {
            return ListFieldType::initialRows( $field );
        }

        $default = self::defaultValue( $field, $context );

        if ( ! $field->allowsDynamicPopulation() ) {
            return $default ?? self::emptyValue( $field );
        }

        $parameter = (string) $field->getPopulationParameter();

        if ( array_key_exists( $parameter, $prefill ) ) {
            $raw = $prefill[ $parameter ];
        } elseif ( null !== $context->queryValue( $parameter ) ) {
            $raw = $context->queryValue( $parameter );
        } else {
            $raw = applyFilters( "ap.forms.fieldValue.{$parameter}", $default, $field, $context );
        }

        return self::coerce( $field, $raw, $default );
    }

    /**
     * Gets the value a composite field starts with.
     *
     * Each enabled sub-input starts with its default. One with a population
     * parameter takes, in order, the prefill, the query string, or the
     * `ap.forms.fieldValue.{parameter}` filter value (which receives the
     * default, the field, the context and the sub-input key). A select input
     * keeps a populated value only when it matches an option, by value or by
     * label ignoring case.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The composite field.
     * @param  PopulationContext  $context  The visitor and page.
     * @param  array<string, mixed>  $prefill  Values passed to the renderer, keyed by parameter name.
     *
     * @return array<string, string> The value, keyed by sub-input.
     */
    protected static function compositeValue( FormField $field, PopulationContext $context, array $prefill ): array
    {
        $value = [];

        foreach ( $field->getEnabledSubInputs() as $key => $subInput ) {
            $default   = (string) ( $subInput['default'] ?? '' );
            $parameter = $subInput['parameter'] ?? null;

            if ( null === $parameter ) {
                $value[ $key ] = $default;

                continue;
            }

            if ( array_key_exists( $parameter, $prefill ) ) {
                $raw = $prefill[ $parameter ];
            } elseif ( null !== $context->queryValue( $parameter ) ) {
                $raw = $context->queryValue( $parameter );
            } else {
                $raw = applyFilters( "ap.forms.fieldValue.{$parameter}", $default, $field, $context, $key );
            }

            $raw = is_scalar( $raw ) && ! is_bool( $raw ) ? self::sanitize( (string) $raw ) : '';

            if ( 'select' === $subInput['type'] && '' !== $raw ) {
                $raw = self::matchSubInputOption( $subInput['options'], $raw ) ?? $default;
            }

            $value[ $key ] = '' === $raw ? $default : $raw;
        }

        return $value;
    }

    /**
     * Finds the option of a select sub-input a populated value selects.
     *
     * @since 1.6.0
     *
     * @param  list<array{value: string, label: string}>  $options  The sub-input's options.
     * @param  string  $value  The value or label.
     *
     * @return string|null The option's value, or null when none matches.
     */
    protected static function matchSubInputOption( array $options, string $value ): ?string
    {
        foreach ( $options as $option ) {
            if ( $option['value'] === $value ) {
                return $option['value'];
            }
        }

        foreach ( $options as $option ) {
            if ( 0 === strcasecmp( $option['label'], $value ) ) {
                return $option['value'];
            }
        }

        return null;
    }

    /**
     * Gets the value a field starts with before it is populated.
     *
     * The options marked as defaults (see FormField::getDefaultOptionValues()),
     * as a list for a multi-choice field, or else the default value with merge
     * tags replaced.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  PopulationContext  $context  The visitor and page.
     *
     * @return array<int, string>|string|null The default, or null when there is none.
     */
    protected static function defaultValue( FormField $field, PopulationContext $context ): array|string|null
    {
        $optionDefaults = $field->getDefaultOptionValues();

        if ( [] !== $optionDefaults ) {
            return $field->isMultipleChoice() ? $optionDefaults : $optionDefaults[0];
        }

        return $field->getResolvedDefaultValue( $context );
    }

    /**
     * Fits a populated value to the field's type.
     *
     * Choice fields keep only values that match an option (by value, then by
     * label ignoring case) and fall back to the default otherwise. Checkbox
     * groups and multi-selects split comma-separated values. Checkboxes and
     * toggles read the value as on or off.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  mixed  $raw  The populated value.
     * @param  array<int, string>|string|null  $default  The default (see defaultValue()), used when the value does not fit.
     *
     * @return mixed The value.
     */
    protected static function coerce( FormField $field, mixed $raw, array|string|null $default ): mixed
    {
        $items = array_map(
            fn ( mixed $item ): string => self::sanitize( (string) $item ),
            array_filter( is_array( $raw ) ? $raw : [$raw], fn ( mixed $item ): bool => is_scalar( $item ) ),
        );

        if ( $field->isMultipleChoice() ) {
            $values = [];

            foreach ( $items as $item ) {
                foreach ( explode( ',', $item ) as $part ) {
                    $match = self::matchOption( $field, trim( $part ) );

                    if ( null !== $match ) {
                        $values[] = $match;
                    }
                }
            }

            if ( [] === $values && null !== $default && $raw !== $default ) {
                return self::coerce( $field, $default, null );
            }

            return array_values( array_unique( $values ) );
        }

        $value = (string) ( array_values( $items )[0] ?? '' );

        if ( in_array( $field->type, FormField::ACCEPTANCE_TYPES, true ) ) {
            return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE )
                ?? filter_var( $default, FILTER_VALIDATE_BOOLEAN );
        }

        if ( in_array( $field->type, ['select', 'radio'], true ) ) {
            return self::matchOption( $field, $value ) ?? $default ?? '';
        }

        return $value;
    }

    /**
     * Finds the option a populated value selects.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The choice field.
     * @param  string  $value  The value or label.
     *
     * @return string|null The option's value, or null when none matches.
     */
    protected static function matchOption( FormField $field, string $value ): ?string
    {
        if ( '' === $value ) {
            return null;
        }

        $byLabel = null;

        foreach ( $field->options as $key => $option ) {
            [$optionValue, $optionLabel] = is_array( $option )
                ? [$option['value'] ?? null, $option['label'] ?? null]
                : [is_string( $key ) ? $key : $option, $option];

            if ( ! is_scalar( $optionValue ) ) {
                continue;
            }

            if ( (string) $optionValue === $value ) {
                return (string) $optionValue;
            }

            if ( null === $byLabel && is_scalar( $optionLabel ) && 0 === strcasecmp( (string) $optionLabel, $value ) ) {
                $byLabel = (string) $optionValue;
            }
        }

        return $byLabel;
    }

    /**
     * Gets the value a field with no default starts with.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array<int|string, string>|string An empty list for multi-choice fields, a blank string per
     *                                          enabled sub-input for composite fields, otherwise an empty string.
     */
    protected static function emptyValue( FormField $field ): array|string
    {
        if ( $field->isComposite() ) {
            return array_fill_keys( array_keys( $field->getEnabledSubInputs() ), '' );
        }

        return $field->isMultipleChoice() ? [] : '';
    }
}
