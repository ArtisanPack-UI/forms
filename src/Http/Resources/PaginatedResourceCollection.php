<?php

/**
 * Paginated resource collection.
 *
 * Provides a consistent paginated wrapper for API resource collections.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Paginated resource collection class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class PaginatedResourceCollection extends ResourceCollection
{
    /**
     * The resource class to use for each item.
     *
     * @since 1.1.0
     *
     * @var string
     */
    public $collects;

    /**
     * Creates a new paginated resource collection instance.
     *
     * @since 1.1.0
     *
     * @param mixed  $resource The paginated resource data.
     * @param string $collects The resource class to use for each item.
     */
    public function __construct( mixed $resource, string $collects )
    {
        $this->collects = $collects;

        parent::__construct( $resource );
    }

    /**
     * Customizes the pagination information for the resource.
     *
     * @since 1.1.0
     *
     * @param Request            $request    The incoming request.
     * @param array<string, mixed> $paginated  The paginated data.
     * @param array<string, mixed> $default    The default pagination information.
     *
     * @return array<string, mixed> The customized pagination information.
     */
    public function paginationInformation( Request $request, array $paginated, array $default ): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'from'         => $paginated['from'],
                'last_page'    => $paginated['last_page'],
                'per_page'     => $paginated['per_page'],
                'to'           => $paginated['to'],
                'total'        => $paginated['total'],
            ],
            'links' => [
                'first' => $paginated['first_page_url'],
                'last'  => $paginated['last_page_url'],
                'prev'  => $paginated['prev_page_url'],
                'next'  => $paginated['next_page_url'],
            ],
        ];
    }
}
