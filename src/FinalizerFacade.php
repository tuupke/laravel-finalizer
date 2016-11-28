<?php namespace Tuupke\Finalizer;

use Illuminate\Support\Facades\Facade;

class FinalizerFacade extends Facade
{
    protected static function getFacadeAccessor() {
        return 'Finalizer';
    }
}
