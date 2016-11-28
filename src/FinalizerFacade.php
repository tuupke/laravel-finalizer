<?php namespace Tuupke\Finalizer;

use Illuminate\Support\Facades\Facade;

class FinalizerFacade extends Facade {

    /**
     * Facade accessor
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'Finalizer';
    }
}
