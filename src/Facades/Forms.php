<?php

namespace ArtisanPackUI\Forms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ArtisanPackUI\Forms\A11y
 */
class Forms extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'forms';
	}
}
