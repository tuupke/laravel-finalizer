<?php namespace Tuupke\Finalizer;

class Finalizer {

    private  $callbacks = [];

    public function __construct() {
        $callbacks = &$this->callbacks;
        register_shutdown_function(function() use ($callbacks) {
            ksort($callbacks);

            foreach($callbacks as $prio => $cbs)
                foreach($cbs as $cb)
                    $cb();
        });
    }

    public function register(Callback $cb, int $priority = 0){
        $this->callbacks[$priority][] = $cb;
    }
}
