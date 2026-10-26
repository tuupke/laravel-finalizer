<?php namespace Tuupke\Finalizer;

use Illuminate\Support\ServiceProvider;

class FinalizerServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->app->singleton(Finalizer::class, function () {
            return new Finalizer;
        });
        $this->app->alias(Finalizer::class, 'Finalizer');
    }

}
