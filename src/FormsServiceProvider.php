<?php

namespace ArtisanPackUI\Forms;

use Illuminate\Support\ServiceProvider;

class FormsServiceProvider extends ServiceProvider
{

	public function register(): void
	{
		$this->app->singleton( 'forms', function ( $app ) {
			return new Forms();
		} );
	}
}
