<?php

/**
 * Section grouping support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\FieldTypes\SectionFieldType;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Works out which fields each section groups.
 *
 * A section groups the fields after it, in sort order, up to the next
 * section or the end of its step; fields before the first section belong to
 * none. `resources/js/shared/sections.ts` mirrors this for React and Vue.
 *
 * @since 1.6.0
 */
class Sections
{
    /**
     * Gets the names of the fields each section groups.
     *
     * Fields are grouped by step and ordered by `sort_order`, so the
     * collection may come in any order.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form's fields.
     *
     * @return array<string, list<string>> Member field names keyed by section name.
     */
    public static function members( iterable $fields ): array
    {
        $steps = [];

        foreach ( $fields as $field ) {
            $steps[ (string) ( $field->step_id ?? '' ) ][] = $field;
        }

        $members = [];

        foreach ( $steps as $stepFields ) {
            usort(
                $stepFields,
                fn ( FormField $a, FormField $b ): int => [(int) $a->sort_order, (int) $a->getKey()] <=> [(int) $b->sort_order, (int) $b->getKey()],
            );

            foreach ( self::segments( $stepFields ) as $segment ) {
                if ( null !== $segment['section'] ) {
                    $members[ $segment['section']->name ] = array_map( fn ( FormField $field ): string => $field->name, $segment['fields'] );
                }
            }
        }

        return $members;
    }

    /**
     * Splits fields, already in display order, into the runs each section groups.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The fields of one step, in order.
     *
     * @return list<array{section: FormField|null, fields: list<FormField>}> The runs; fields before the first section have no section.
     */
    public static function segments( iterable $fields ): array
    {
        $segments = [];
        $current  = ['section' => null, 'fields' => []];

        foreach ( $fields as $field ) {
            if ( SectionFieldType::TYPE === $field->type ) {
                if ( null !== $current['section'] || [] !== $current['fields'] ) {
                    $segments[] = $current;
                }

                $current = ['section' => $field, 'fields' => []];

                continue;
            }

            $current['fields'][] = $field;
        }

        if ( null !== $current['section'] || [] !== $current['fields'] ) {
            $segments[] = $current;
        }

        return $segments;
    }

    /**
     * Hides the fields of each hidden section.
     *
     * A CAPTCHA stays shown, as conditional logic never hides one (see
     * ConditionalLogicService::evaluateField()).
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form's fields.
     * @param  array<string, bool>  $visibility  Field visibility keyed by name (true = visible).
     *
     * @return array<string, bool> The visibility with each hidden section's fields hidden.
     */
    public static function applyVisibility( iterable $fields, array $visibility ): array
    {
        $types = [];

        foreach ( $fields as $field ) {
            $types[ $field->name ] = $field->type;
        }

        foreach ( self::members( $fields ) as $section => $names ) {
            if ( $visibility[ $section ] ?? true ) {
                continue;
            }

            foreach ( $names as $name ) {
                if ( 'captcha' !== ( $types[ $name ] ?? null ) ) {
                    $visibility[ $name ] = false;
                }
            }
        }

        return $visibility;
    }
}
