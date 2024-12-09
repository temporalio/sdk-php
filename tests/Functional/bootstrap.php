<?php

declare(strict_types=1);

use Temporal\Testing\Environment;
use Temporal\Tests\SearchAttributeTestInvoker;
use Temporal\Worker\FeatureFlags;

chdir(__DIR__ . '/../..');
require_once __DIR__ . '/../../vendor/autoload.php';

$sysInfo = \Temporal\Testing\SystemInfo::detect();

$environment = Environment::create();
$environment->startTemporalTestServer();
(new SearchAttributeTestInvoker)();
$environment->startRoadRunner(
    rrCommand: sprintf('%s serve -c .rr.silent.yaml -w tests/Functional', $sysInfo->rrExecutable),
);
register_shutdown_function(fn() => $environment->stop());

// Default feature flags
FeatureFlags::$warnOnWorkflowUnfinishedHandlers = false;
