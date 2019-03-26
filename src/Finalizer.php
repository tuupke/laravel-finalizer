<?php namespace Tuupke\Finalizer;

class Finalizer {

    private $closures   = [];
    private $syncOnExit = false;

    /**
     * Finalizer constructor. Registers the shutdown function
     */
    public final function __construct() {
        register_shutdown_function(function ($obj) {
            if (!$this->syncOnExit && function_exists("fastcgi_finish_request")) {
                fastcgi_finish_request();
            }

            $closurePriorities = $obj->closures;

            // Sort by priorities
            ksort($closurePriorities);

            foreach ($closurePriorities as $priority => $closures) {
                foreach ($closures as $closure) {
                    $closure();
                }
            }
        }, $this);
    }

    /**
     * Register a closure to execute after Call has finished.
     *
     * @param \Closure $cb The closure to call.
     * @param int      $priority The priority of execution.
     */
    public final function register(\Closure $cb, int $priority = 100) {
        // running from console/daemon
        if (php_sapi_name() === 'cli') {
            $cb();
        } else {
            $this->closures[$priority][] = $cb;
        }
    }

    public final function inSyncOnExit(bool $sync = true) {
        $this->syncOnExit = $sync;
    }
}
