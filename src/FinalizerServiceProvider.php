<?php namespace Tuupke\Finalizer;

use \Illuminate\Support\ServiceProvider;

class SwaggerServiceProvider extends ServiceProvider {

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
    public function boot() {
        $this->loadViewsFrom(__DIR__.'/views', 'package-swagger');

        $this->publishes([
            __DIR__.'/swagger.php' => config_path('swagger.php'),
        ]);

        $this->publishes([
            __DIR__.'/../swagger-public' => public_path('vendor/swagger'),
        ], 'public');

    }
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/swagger.php', 'swagger'
        );

        require_once __DIR__ .'/routes.php';
    }

}
