<?php

/**
 * Forms facade.
 *
 * Provides a static interface to the Forms service registered in the container.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\Forms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Forms facade class.
 *
 * Provides static access to the Forms service for convenient usage
 * throughout the application without dependency injection.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 * @see \ArtisanPackUI\Forms\Forms
 */
class Forms extends Facade
{
    /**
     * Gets the registered name of the component.
     *
     * Returns the container binding key for the Forms service.
     *
     * @since 1.0.0
     *
     * @return string The facade accessor key.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'forms';
    }
}
