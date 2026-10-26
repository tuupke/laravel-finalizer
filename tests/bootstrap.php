<?php

/*
 * Minimal Laravel container setup shared by the CLI and HTTP tests. Everything
 * the finalizer does is recorded to a file so the test runner can inspect it.
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Tuupke\Finalizer\FinalizerServiceProvider;

function record(string $line) {
    file_put_contents(sys_get_temp_dir() . '/finalizer-test.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

class RecordingLogger {
    public function error($message, array $context = []) {
        record('log:' . $message);
    }
}

$app = new Container;
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance('log', new RecordingLogger);

$provider = new FinalizerServiceProvider($app);
$provider->register();
