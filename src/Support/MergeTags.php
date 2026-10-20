<?php

/**
 * Default value merge tags.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Replaces merge tags in a field's default value.
 *
 * `{user:email}`, `{user:name}`, `{date}` and `{ip}` come from the server.
 * `{embed_url}`, `{referer}` and `{query:name}` describe the page and follow
 * the context's page tag mode (see PopulationContext). Any other text in
 * braces is left alone. `resources/js/shared/dynamicPopulation.ts` replaces
 * the page tags the API leaves in place.
 *
 * @since 1.6.0
 */
class MergeTags
{
    /**
     * Matches a supported merge tag.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const PATTERN = '/\{(user:email|user:name|date|ip|embed_url|referer|query:[A-Za-z0-9_.\-]+)\}/';

    /**
     * Gets the merge tags the builders list beside the Default Value input.
     *
     * @since 1.6.0
     *
     * @return list<string> The tags.
     */
    public static function available(): array
    {
        return ['{user:email}', '{user:name}', '{date}', '{embed_url}', '{referer}', '{ip}', '{query:utm_source}'];
    }

    /**
     * Replaces the merge tags in a value.
     *
     * Replacements are sanitized (see DynamicPopulation::sanitize()), since
     * the page URL, referer and query string come from the visitor.
     *
     * @since 1.6.0
     *
     * @param  string  $text  The text.
     * @param  PopulationContext  $context  The values to replace tags with.
     *
     * @return string The text with tags replaced.
     */
    public static function resolve( string $text, PopulationContext $context ): string
    {
        if ( ! str_contains( $text, '{' ) ) {
            return $text;
        }

        return (string) preg_replace_callback(
            self::PATTERN,
            fn ( array $matches ): string => self::replacement( $matches[1], $matches[0], $context ),
            $text,
        );
    }

    /**
     * Gets the replacement for one tag.
     *
     * @since 1.6.0
     *
     * @param  string  $tag  The tag without braces.
     * @param  string  $original  The tag as written, kept when page tags are left for the client.
     * @param  PopulationContext  $context  The context.
     *
     * @return string The replacement.
     */
    protected static function replacement( string $tag, string $original, PopulationContext $context ): string
    {
        $isPageTag = in_array( $tag, ['embed_url', 'referer'], true ) || str_starts_with( $tag, 'query:' );

        if ( $isPageTag && PopulationContext::PAGE_TAGS_KEEP === $context->pageTags ) {
            return $original;
        }

        if ( $isPageTag && PopulationContext::PAGE_TAGS_BLANK === $context->pageTags ) {
            return '';
        }

        $value = match ( true ) {
            'user:email' === $tag => $context->user?->email ?? '',
            'user:name' === $tag  => $context->user?->name ?? '',
            'date' === $tag       => now()->toDateString(),
            'ip' === $tag         => $context->ipAddress ?? '',
            'embed_url' === $tag  => $context->embedUrl ?? '',
            'referer' === $tag    => $context->referer ?? '',
            default               => $context->queryValue( substr( $tag, 6 ) ),
        };

        if ( is_array( $value ) ) {
            $value = implode( ',', $value );
        }

        return DynamicPopulation::sanitize( is_scalar( $value ) ? (string) $value : '' );
    }
}
