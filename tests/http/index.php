<?php

/*
 * Served by PHP-FPM behind nginx. Registers a slow closure; in async mode the
 * response must arrive before it runs, in sync mode only after.
 */

require __DIR__ . '/../bootstrap.php';

use Tuupke\Finalizer\FinalizerFacade;

// Readiness probe for the test runner
if (!isset($_GET['id'])) {
    exit("ready\n");
}

$id = $_GET['id'];

if (($_GET['mode'] ?? 'async') === 'sync') {
    FinalizerFacade::inSyncOnExit();
}

FinalizerFacade::register(function () use ($id) {
    record("$id:200");
}, 200);

FinalizerFacade::register(function () use ($id) {
    sleep(2);
    record("$id:10");
    throw new RuntimeException('boom');
}, 10);

record("$id:response");
echo "response for $id\n";
