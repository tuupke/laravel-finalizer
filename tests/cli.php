<?php

/*
 * Runs in the CLI SAPI: checks container wiring, the immediate execution of
 * closures in CLI, and the shutdown handler (priority order, error logging).
 */

require __DIR__ . '/bootstrap.php';

use Tuupke\Finalizer\Finalizer;
use Tuupke\Finalizer\FinalizerFacade;

function check(string $name, bool $ok) {
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        exit(1);
    }
}

error_reporting(E_ALL);
set_error_handler(function ($no, $message, $file, $line) {
    if (strpos(realpath($file), '/pkg/src/') === 0) {
        check("no PHP notices from the package: $message ($file:$line)", false);
    }
    return true;
});

@unlink(sys_get_temp_dir() . '/finalizer-test.log');

$instance = $app->make(Finalizer::class);
check('Finalizer is a singleton', $instance === $app->make(Finalizer::class));
check('facade resolves the singleton', FinalizerFacade::getFacadeRoot() === $instance);

$ran = false;
FinalizerFacade::register(function () use (&$ran) {
    $ran = true;
});
check('closure runs immediately in CLI', $ran);

// The CLI runs closures immediately, so queue them directly to exercise the shutdown handler.
$closures = new ReflectionProperty($instance, 'closures');
if (PHP_VERSION_ID < 80100) {
    $closures->setAccessible(true);
}
$closures->setValue($instance, [
    200 => [function () { record('200'); }],
    10  => [function () { record('10'); throw new RuntimeException('boom'); }],
]);

// Registered after the finalizer's own shutdown function, so it runs after it.
register_shutdown_function(function () {
    $lines = file(sys_get_temp_dir() . '/finalizer-test.log', FILE_IGNORE_NEW_LINES);
    check('shutdown runs closures by priority and logs throwables',
        $lines === ['10', 'log:Throwable caught in finalizer', '200']);
});
