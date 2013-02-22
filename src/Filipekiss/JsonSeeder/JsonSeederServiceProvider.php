<?php

namespace Filipekiss\JsonSeeder;

use Illuminate\Support\ServiceProvider;

class JsonSeederServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('filipekiss/json-seeder');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['jsonseeder'] = $this->app->share(function($app)
		{
			return new Commands\JsonSeeder($app);
		});
		$this->commands(
			'jsonseeder'
		);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}