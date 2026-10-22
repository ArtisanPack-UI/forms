<?php

/**
 * Dynamic choice sources.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sources a choice field can load its options from at render time.
 *
 * Register a source through the `ap.forms.choiceSources` filter, keyed by a
 * name, as `['label' => …, 'resolver' => callable]`. The resolver receives
 * the field and returns its choices as `['value' => …, 'label' => …]`
 * entries (an entry may set `'default' => true`), a `value => label` map or
 * a list of strings, for example:
 *
 * ```php
 * addFilter( 'ap.forms.choiceSources', function ( array $sources ): array {
 *     $sources['locations'] = [
 *         'label'    => __( 'Store locations' ),
 *         'resolver' => fn ( FormField $field ): array => Location::query()->orderBy( 'name' )->pluck( 'name', 'id' )->all(),
 *     ];
 *
 *     return $sources;
 * } );
 * ```
 *
 * A field picks a source with its `choices_source` setting. Its options are
 * then the resolved choices wherever they are read (rendering, validation,
 * display, conditional logic), and submitted values must be one of them.
 * Resolved choices are kept for the rest of the request, per source and
 * field, since a submission or export reads them many times. A resolver that
 * throws gives no choices and is logged.
 *
 * @since 1.6.0
 */
class ChoiceSources
{
    /**
     * Resolved choices for the current request, by source key, then field ID.
     *
     * @since 1.6.0
     *
     * @var array<string, array<string, list<array{value: string, label: string, default?: bool}>>>
     */
    protected array $resolved = [];

    /**
     * The registered sources, read once per request.
     *
     * @since 1.6.0
     *
     * @var array<string, array{label: string, resolver: callable}>|null
     */
    protected ?array $sources = null;

    /**
     * Gets the registered sources, after the `ap.forms.choiceSources` filter.
     *
     * Entries without a label or a callable resolver are dropped.
     *
     * @since 1.6.0
     *
     * @return array<string, array{label: string, resolver: callable}> The sources by key.
     */
    public function all(): array
    {
        if ( null !== $this->sources ) {
            return $this->sources;
        }

        $sources = applyFilters( 'ap.forms.choiceSources', [] );
        $valid   = [];

        foreach ( is_array( $sources ) ? $sources : [] as $key => $source ) {
            if ( ! is_string( $key ) || '' === $key || ! is_array( $source ) || ! is_callable( $source['resolver'] ?? null ) ) {
                continue;
            }

            $label = $source['label'] ?? null;

            $valid[ $key ] = [
                'label'    => is_scalar( $label ) && '' !== trim( (string) $label ) ? (string) $label : $key,
                'resolver' => $source['resolver'],
            ];
        }

        return $this->sources = $valid;
    }

    /**
     * Checks whether a source is registered.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The source key.
     *
     * @return bool True when registered.
     */
    public function has( string $key ): bool
    {
        return isset( $this->all()[ $key ] );
    }

    /**
     * Gets the sources as select options for the builders' `choices_source` setting.
     *
     * @since 1.6.0
     *
     * @return list<array{value: string, label: string}> The options.
     */
    public function options(): array
    {
        $options = [];

        foreach ( $this->all() as $key => $source ) {
            $options[] = ['value' => $key, 'label' => $source['label']];
        }

        return $options;
    }

    /**
     * Gets a field's choices from a source.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The source key.
     * @param  FormField  $field  The field.
     *
     * @return list<array{value: string, label: string, default?: bool}> The choices, or an empty list when the source is unknown or fails.
     */
    public function resolve( string $key, FormField $field ): array
    {
        $fieldKey = (string) ( $field->getKey() ?? spl_object_id( $field ) );

        if ( isset( $this->resolved[ $key ][ $fieldKey ] ) ) {
            return $this->resolved[ $key ][ $fieldKey ];
        }

        $source = $this->all()[ $key ] ?? null;

        if ( null === $source ) {
            return [];
        }

        try {
            $choices = PredefinedChoices::normalizeChoices( call_user_func( $source['resolver'], $field ) );
        } catch ( Throwable $exception ) {
            Log::warning( 'ArtisanPackUI Forms: choice source failed', [
                'source'   => $key,
                'field_id' => $field->getKey(),
                'error'    => $exception->getMessage(),
            ] );

            $choices = [];
        }

        return $this->resolved[ $key ][ $fieldKey ] = $choices;
    }

    /**
     * Forgets the sources and resolved choices, so the next read runs the filter and resolvers again.
     *
     * @since 1.6.0
     */
    public function flush(): void
    {
        $this->resolved = [];
        $this->sources  = null;
    }
}
