<?php

declare(strict_types=1);

use Temporal\Testing\Environment;

require __DIR__ . '/../vendor/autoload.php';

$environment = Environment::create();
$environment->start('./rr serve -c .rr.silent.yaml -w tests');
register_shutdown_function(fn () => $environment->stop());
