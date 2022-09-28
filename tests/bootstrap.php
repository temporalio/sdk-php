<?php

declare(strict_types=1);

use Temporal\Testing\Environment;
use Temporal\Tests\SearchAttributeTestInvoker;

require __DIR__ . '/../vendor/autoload.php';

if (getenv('RUN_TEMPORAL_TEST_SERVER') !== false) {
    $environment = Environment::create();
    $environment->startTemporalTestServer();
    (new SearchAttributeTestInvoker)();
    $environment->startRoadRunner('./rr serve -c .rr.silent.yaml -w tests');
    register_shutdown_function(fn() => $environment->stop());
}

