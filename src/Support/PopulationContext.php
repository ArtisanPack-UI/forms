<?php

/**
 * Dynamic population context.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * What merge tags and dynamic population read: the visitor, the page the form
 * is shown on and its query string.
 *
 * Page tags (`{embed_url}`, `{referer}`, `{query:…}`) describe the page the
 * visitor sees. On a Livewire page request the server knows them; when the
 * React or Vue renderer fetches the form from the API it does not, so they
 * are kept for the client to fill in. Read-only fields blank them, since the
 * client controls them and a read-only value must not be.
 *
 * @since 1.6.0
 */
class PopulationContext
{
    /**
     * Replace page tags with the values in this context.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const PAGE_TAGS_RESOLVE = 'resolve';

    /**
     * Leave page tags in place for the client to replace.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const PAGE_TAGS_KEEP = 'keep';

    /**
     * Replace page tags with an empty string.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const PAGE_TAGS_BLANK = 'blank';

    /**
     * Creates a context.
     *
     * @since 1.6.0
     *
     * @param  Authenticatable|null  $user  The visitor, if signed in.
     * @param  string|null  $ipAddress  The visitor's IP address, as the privacy settings allow it to be kept.
     * @param  string|null  $embedUrl  The URL of the page the form is shown on.
     * @param  string|null  $referer  The page the visitor came from.
     * @param  array<string, mixed>  $query  The page's query string.
     * @param  string  $pageTags  How page tags are handled: one of the PAGE_TAGS_* constants.
     */
    public function __construct(
        public readonly ?Authenticatable $user = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $embedUrl = null,
        public readonly ?string $referer = null,
        public readonly array $query = [],
        public readonly string $pageTags = self::PAGE_TAGS_RESOLVE,
    ) {
    }

    /**
     * Builds the context of a page request, such as the one that mounts the Livewire renderer.
     *
     * @since 1.6.0
     *
     * @param  Request  $request  The request.
     *
     * @return self The context.
     */
    public static function fromPageRequest( Request $request ): self
    {
        return new self(
            user: $request->user(),
            ipAddress: self::ipAddress( $request ),
            embedUrl: $request->fullUrl(),
            referer: $request->header( 'referer' ),
            query: $request->query(),
        );
    }

    /**
     * Builds the context of an API request, which knows the visitor but not the page.
     *
     * @since 1.6.0
     *
     * @param  Request  $request  The request.
     *
     * @return self The context, keeping page tags for the client.
     */
    public static function fromApiRequest( Request $request ): self
    {
        return new self(
            user: $request->user(),
            ipAddress: self::ipAddress( $request ),
            pageTags: self::PAGE_TAGS_KEEP,
        );
    }

    /**
     * Builds the context for a read-only value: the visitor only, with page tags blanked.
     *
     * @since 1.6.0
     *
     * @param  Request|null  $request  The request, or null for the current one.
     *
     * @return self The context.
     */
    public static function trusted( ?Request $request = null ): self
    {
        $request ??= request();

        return new self(
            user: $request->user(),
            ipAddress: self::ipAddress( $request ),
            pageTags: self::PAGE_TAGS_BLANK,
        );
    }

    /**
     * Gets a query string value.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The query parameter.
     *
     * @return mixed The value: a scalar, a list of scalars or null.
     */
    public function queryValue( string $key ): mixed
    {
        if ( ! array_key_exists( $key, $this->query ) ) {
            return null;
        }

        $value = $this->query[ $key ];

        if ( is_array( $value ) ) {
            return array_values( array_filter( $value, 'is_scalar' ) );
        }

        return is_scalar( $value ) ? $value : null;
    }

    /**
     * Gets the visitor's IP address as the privacy settings allow it to be kept.
     *
     * @since 1.6.0
     *
     * @param  Request  $request  The request.
     *
     * @return string|null The address, possibly anonymized, or null when not collected.
     */
    protected static function ipAddress( Request $request ): ?string
    {
        return app( SubmissionService::class )->getRequestMetadata( $request )['ip_address'] ?? null;
    }
}
