<?php
namespace Frozenshadow\LaravelOWM;

use Illuminate\Support\ServiceProvider;

class LaravelOWMServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{

		$this->publishes([
			__DIR__ . '/config' => config_path()
		]);

	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('Frozenshadow\LaravelOWM\LaravelOWM', function(){

			return new \Frozenshadow\LaravelOWM\LaravelOWM();

		});
	}

        // Loading routes file
        if (config('laravel-owm.routes_enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        }
}
