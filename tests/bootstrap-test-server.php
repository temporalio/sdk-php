<?php

declare(strict_types=1);

use Temporal\Testing\Environment;
use Temporal\Tests\SearchAttributeTestInvoker;

chdir(__DIR__ . '/..');
require __DIR__ . '/../vendor/autoload.php';

$environment = Environment::create();
$environment->startTemporalTestServer();
(new SearchAttributeTestInvoker)();
$environment->startRoadRunner('./rr serve -c .rr.silent.yaml -w tests');
register_shutdown_function(fn() => $environment->stop());
